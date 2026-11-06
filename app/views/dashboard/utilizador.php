<?php
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

obrigarLogin();

$realPerfil = $_SESSION['usuario_perfil'] ?? 'utilizador';
$isAdminOrSuper = in_array($realPerfil, ['admin', 'superadmin'], true);

if ($isAdminOrSuper) {
    $idUsuario = (int)($_GET['id_usuario'] ?? 0);
    if ($idUsuario <= 0) {
        $resFallback = $mysqli->query("
            SELECT u.id, u.nome 
            FROM usuarios u 
            JOIN projetos p ON p.criado_por = u.id 
            ORDER BY p.criado_em DESC LIMIT 1
        ");
        $fallback = $resFallback ? $resFallback->fetch_assoc() : null;
        $idUsuario = $fallback ? (int)$fallback['id'] : 0;
    }

    $stmtU = $mysqli->prepare("SELECT nome FROM usuarios WHERE id = ?");
    if ($stmtU) {
        $stmtU->bind_param('i', $idUsuario);
        $stmtU->execute();
        $uData = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();
        $nome = $uData['nome'] ?? 'Estudante';
    } else {
        $nome = 'Estudante';
    }

    $nomeUsuario = $nome;
    $perfil = 'utilizador';
} else {
    $idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
    $nome = $_SESSION['usuario_nome'] ?? 'Utilizador';
}

/* ── 1. PROJETO MAIS RECENTE DO ESTUDANTE ── */
$stmt = $mysqli->prepare("
    SELECT *
    FROM projetos
    WHERE criado_por = ?
    ORDER BY criado_em DESC LIMIT 1
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$ultimoProjeto = $stmt->get_result()->fetch_assoc();
$stmt->close();

$labelsEstado = [
    'submetido'          => 'Submetido (Em Triagem)',
    'em_avaliacao'       => 'Em Avaliação Técnica',
    'aprovado'           => 'Aprovado para Incubação',
    'rejeitado'          => 'Rejeitado',
    'incubado'           => 'Em Incubação',
    'fundo_investimento' => 'Pronto p/ Investimento',
    'concluido'          => 'Graduado',
];

$labelsFase = [
    'ideacao'            => 'Ideação',
    'validacao'          => 'Validação',
    'mvp'                => 'MVP',
    'tracao'             => 'Tração',
    'mercado'            => 'Mercado',
    'fundo_investimento' => 'Financiado',
];

$mentorAtivo        = null;
$termoProjeto       = null;
$ultimaAvaliacao    = null;
$metasFase          = [];
$minhasTarefas      = [];
$minhasReunioes     = [];
$ultimaSessao       = null;
$metasValidadasCount = 0;
$totalMetasFase     = 0;
$metaPrincipal      = null;
$metaEmAvaliacao    = null;

if ($ultimoProjeto) {
    $idProj     = (int)$ultimoProjeto['id'];
    $estadoProj = (string)($ultimoProjeto['estado'] ?? '');
    $faseActual = (string)($ultimoProjeto['fase'] ?? 'ideacao');

    /* ── 2. MENTOR ATRIBUÍDO (MENTORIA ATIVA) ── */
    $stmtMent = $mysqli->prepare("
        SELECT m.id AS id_mentoria, u.nome AS mentor_nome, u.email AS mentor_email, mt.especialidade
        FROM mentorias m
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u ON u.id = mt.id_usuario
        WHERE m.id_projeto = ? AND m.estado = 'activa'
        ORDER BY m.id DESC LIMIT 1
    ");
    if ($stmtMent) {
        $stmtMent->bind_param('i', $idProj);
        $stmtMent->execute();
        $mentorAtivo = $stmtMent->get_result()->fetch_assoc();
        $stmtMent->close();
    }

    /* ── 3. TERMO DE INCUBAÇÃO ── */
    $stmtTermo = $mysqli->prepare("
        SELECT id, codigo_termo, tipo_contrato, duracao_meses, estado, assinado_em, criado_em
        FROM termos_incubacao
        WHERE id_projeto = ? AND estado IN ('gerado', 'pendente_assinatura', 'assinado')
        ORDER BY criado_em DESC LIMIT 1
    ");
    if ($stmtTermo) {
        $stmtTermo->bind_param('i', $idProj);
        $stmtTermo->execute();
        $termoProjeto = $stmtTermo->get_result()->fetch_assoc();
        $stmtTermo->close();
    }

    /* ── 4. ÚLTIMA AVALIAÇÃO OFICIAL (para estado aprovado / rejeitado / em_avaliacao) ── */
    $stmtAv = $mysqli->prepare("
        SELECT pontuacao_total, decisao, observacoes, avaliado_em
        FROM avaliacoes
        WHERE id_projeto = ?
        ORDER BY avaliado_em DESC LIMIT 1
    ");
    if ($stmtAv) {
        $stmtAv->bind_param('i', $idProj);
        $stmtAv->execute();
        $ultimaAvaliacao = $stmtAv->get_result()->fetch_assoc();
        $stmtAv->close();
    }

    /* ── 5. METAS DA FASE ATUAL (apenas quando incubado/fundo_investimento/concluido) ── */
    if (in_array($estadoProj, ['incubado', 'fundo_investimento', 'concluido'], true)) {
        $stmtM = $mysqli->prepare("
            SELECT mp.*, mpd.numero, mpd.titulo AS meta_titulo, mpd.descricao AS meta_descricao,
                   mpd.evidencia_tipo, mpd.evidencia_desc, mpd.peso_percentual, mpd.prazo_dias
            FROM metas_projeto mp
            JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
            WHERE mp.id_projeto = ? AND mpd.fase = ?
            ORDER BY mpd.numero ASC
        ");
        if ($stmtM) {
            $stmtM->bind_param('is', $idProj, $faseActual);
            $stmtM->execute();
            $metasFase = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtM->close();
        }

        $totalMetasFase = count($metasFase);
        foreach ($metasFase as $m) {
            $estM = (string)($m['estado'] ?? 'inactiva');
            if ($estM === 'concluida') {
                $metasValidadasCount++;
            } elseif (($estM === 'reprovada' || $estM === 'activa') && $metaPrincipal === null) {
                $metaPrincipal = $m;
            } elseif ($estM === 'em_avaliacao' && $metaEmAvaliacao === null) {
                $metaEmAvaliacao = $m;
            }
        }
    }

    /* ── 6. TAREFAS DO MENTOR ── */
    $stmtT = $mysqli->prepare("
        SELECT id, titulo, descricao, data_limite, status, validada_mentor
        FROM tarefas
        WHERE id_projeto = ?
        ORDER BY status ASC, data_limite ASC
    ");
    if ($stmtT) {
        $stmtT->bind_param('i', $idProj);
        $stmtT->execute();
        $minhasTarefas = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtT->close();
    }

    /* ── 7. PRÓXIMAS REUNIÕES AGENDADAS ── */
    $stmtR = $mysqli->prepare("
        SELECT r.id, r.titulo, r.data_reuniao, r.duracao_min, r.local_link, u.nome AS mentor_nome
        FROM reunioes r
        JOIN usuarios u ON u.id = r.id_mentor
        WHERE r.id_projeto = ? AND r.data_reuniao >= NOW() AND r.status = 'agendada'
        ORDER BY r.data_reuniao ASC LIMIT 2
    ");
    if ($stmtR) {
        $stmtR->bind_param('i', $idProj);
        $stmtR->execute();
        $minhasReunioes = $stmtR->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtR->close();
    }

    /* ── 8. ÚLTIMA SESSÃO DE MENTORIA REALIZADA ── */
    $stmtS = $mysqli->prepare("
        SELECT s.data_sessao, s.topicos, s.proximos_passos, u.nome AS mentor_nome
        FROM sessoes_mentoria s
        JOIN mentorias m ON m.id = s.id_mentoria
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u ON u.id = mt.id_usuario
        WHERE m.id_projeto = ?
        ORDER BY s.data_sessao DESC LIMIT 1
    ");
    if ($stmtS) {
        $stmtS->bind_param('i', $idProj);
        $stmtS->execute();
        $ultimaSessao = $stmtS->get_result()->fetch_assoc();
        $stmtS->close();
    }
}

/* ── 9. RESERVAS DE ESPAÇO DO UTILIZADOR ── */
$minhasReservasPainel = [];
$stmtRes = $mysqli->prepare("
    SELECT r.id, r.data_reserva, r.hora_inicio, r.hora_fim, r.status, e.nome AS espaco_nome
    FROM reservas_espaco r
    JOIN espacos e ON e.id = r.id_espaco
    WHERE r.id_usuario = ?
    ORDER BY r.data_reserva DESC, r.hora_inicio DESC
    LIMIT 4
");
if ($stmtRes) {
    $stmtRes->bind_param('i', $idUsuario);
    $stmtRes->execute();
    $minhasReservasPainel = $stmtRes->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtRes->close();
}

$reservasConfirmadas = array_values(array_filter(
    $minhasReservasPainel,
    fn($r) => ($r['status'] ?? '') === 'confirmada'
));
$totalReservasConfirmadas = count($reservasConfirmadas);

/* ── DETERMINAÇÃO DO ESTADO DA MÁQUINA DE ESTADOS (E0 a E5 + E_FALLBACK) ── */
$uiState = 'E0';
if ($ultimoProjeto) {
    $estP = (string)($ultimoProjeto['estado'] ?? '');
    if (in_array($estP, ['submetido', 'em_avaliacao'], true)) {
        $uiState = 'E1';
    } elseif ($estP === 'aprovado') {
        $uiState = 'E2';
    } elseif (in_array($estP, ['incubado', 'fundo_investimento'], true)) {
        if ($totalMetasFase > 0 && $metasValidadasCount === $totalMetasFase) {
            $uiState = 'E4';
        } else {
            $uiState = 'E3';
        }
    } elseif ($estP === 'concluido') {
        $uiState = 'E4';
    } elseif ($estP === 'rejeitado') {
        $uiState = 'E5';
    } else {
        $uiState = 'E_FALLBACK';
    }
}

$tituloPagina = 'Meu Painel';
$paginaActiva = 'dashboard';
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<div class="std-painel">

    <?php if ($flashOk): ?>
        <div class="alert-custom alert-success" role="status">
            <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
            <span><?= htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>
    <?php if ($flashErro): ?>
        <div class="alert-custom alert-danger" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span><?= htmlspecialchars($flashErro, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <?php if ($isAdminOrSuper): ?>
        <div class="sp-read-bar" role="region" aria-label="Modo de pré-visualização de administrador">
            <div>
                <i class="fa-solid fa-eye me-2" aria-hidden="true"></i>
                Modo de leitura: a pré-visualizar o painel de <strong><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></strong>.
            </div>
            <form method="GET" class="m-0">
                <label for="selectEstudanteImpersonate" class="visually-hidden">Selecionar estudante</label>
                <select id="selectEstudanteImpersonate" name="id_usuario" class="sp-read-select" onchange="this.form.submit()">
                    <?php
                    $estudantesList = $mysqli->query("
                        SELECT DISTINCT u.id, u.nome, p.titulo
                        FROM usuarios u
                        JOIN projetos p ON p.criado_por = u.id
                        ORDER BY u.nome
                    ");
                    if ($estudantesList):
                        while ($est = $estudantesList->fetch_assoc()):
                            $sel = ((int)$est['id'] === $idUsuario) ? 'selected' : '';
                    ?>
                        <option value="<?= (int)$est['id'] ?>" <?= $sel ?>>
                            <?= htmlspecialchars($est['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($est['titulo'], ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php
                        endwhile;
                    endif;
                    ?>
                </select>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($uiState === 'E0'): ?>
        <!-- ══ ESTADO E0: SEM PROJETO SUBMETIDO ══ -->
        <header class="sp-header">
            <div class="sp-kicker">Incubadora Académica do ISPSN</div>
            <h1 class="sp-title">Submissão de Projecto</h1>
            <div class="sp-meta-row">
                <span>Utilizador: <strong><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></strong></span>
                <span class="sp-chip sp-chip-neutral">Sem projecto submetido</span>
            </div>
        </header>

        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="sp-section-label">O que fazer a seguir</div>
                <section class="sp-surface-primary" aria-labelledby="e0Heading">
                    <h2 id="e0Heading" class="fw-bold mb-2" style="font-size: var(--sp-fs-lg);">Ainda não submeteu um projecto para incubação</h2>
                    <p class="mb-3" style="color: var(--sp-text-sec);">
                        Submeta a proposta da sua iniciativa para apreciação técnica. O formulário solicita a identificação do problema, a solução proposta e a descrição da ideia.
                    </p>
                    <?php if (!$isAdminOrSuper): ?>
                        <button type="button" class="sp-btn-primary" data-bs-toggle="modal" data-bs-target="#modalProjeto">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            <span>Submeter Primeira Ideia</span>
                        </button>
                    <?php else: ?>
                        <div class="sp-empty-line border-0 p-0">Conta de estudante sem projecto registado.</div>
                    <?php endif; ?>
                </section>
            </div>

            <div class="col-lg-4">
                <div class="sp-section-label">Apoio e Espaços</div>
                <aside class="sp-surface-aside" aria-label="Recursos e espaços">
                    <div class="sp-aside-block">
                        <div class="sp-kicker">Mentoria</div>
                        <div style="font-size: var(--sp-fs-sm); color: var(--sp-text-sec);">
                            A atribuição de mentor ocorre após a aprovação e formalização do projecto.
                        </div>
                    </div>
                    <div class="sp-aside-block">
                        <div class="sp-kicker">Reservas de Espaço</div>
                        <div class="sp-num mb-1" style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                            <?= $totalReservasConfirmadas > 0
                                ? $totalReservasConfirmadas . ' reserva' . ($totalReservasConfirmadas > 1 ? 's' : '') . ' confirmada' . ($totalReservasConfirmadas > 1 ? 's' : '')
                                : 'Sem reservas confirmadas' ?>
                        </div>
                        <a href="/incubadora_ispsn/app/views/utilizador/reservas.php" class="sp-link">
                            <span>Reservas de Espaço</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </aside>
            </div>
        </div>

    <?php else: ?>
        <?php
        $estadoRaw   = (string)($ultimoProjeto['estado'] ?? '');
        $faseKey     = (string)($ultimoProjeto['fase'] ?? 'ideacao');
        $faseLabel   = $labelsFase[$faseKey] ?? strtoupper(str_replace('_', ' ', $faseKey));
        $estadoLabel = $labelsEstado[$estadoRaw] ?? $estadoRaw;
        $pontos      = (int)($ultimoProjeto['pontos'] ?? 0);
        $dataCriacao = !empty($ultimoProjeto['criado_em'])
            ? date('d/m/Y', strtotime((string)$ultimoProjeto['criado_em']))
            : '';

        $chipClass = 'sp-chip-neutral';
        if (in_array($estadoRaw, ['aprovado', 'incubado', 'fundo_investimento', 'concluido'], true)) {
            $chipClass = 'sp-chip-ok';
        } elseif (in_array($estadoRaw, ['submetido', 'em_avaliacao'], true)) {
            $chipClass = 'sp-chip-warn';
        } elseif ($estadoRaw === 'rejeitado') {
            $chipClass = 'sp-chip-err';
        }

        $mostrarStepperESp = in_array($uiState, ['E3', 'E4'], true);
        ?>

        <!-- ══ 1. CABEÇALHO EDITORIAL ("ONDE ESTOU?") ══ -->
        <header class="sp-header">
            <div class="sp-kicker">
                Estado da Incubação<?php if ($mostrarStepperESp): ?> · Fase Actual: <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
            </div>
            <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-3">
                <h1 class="sp-title"><?= htmlspecialchars((string)$ultimoProjeto['titulo'], ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if ($mostrarStepperESp): ?>
                    <div class="sp-meta-row m-0">
                        <span class="sp-num fw-semibold" style="color: var(--sp-text);">
                            <?= (int)$metasValidadasCount ?> de <?= (int)$totalMetasFase ?> metas validadas
                        </span>
                        <?php if ($pontos > 0): ?>
                            <span aria-hidden="true">·</span>
                            <span class="sp-code fw-semibold" style="color: var(--sp-accent);"><?= (int)$pontos ?> SP</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sp-meta-row">
                <span class="sp-chip <?= $chipClass ?>">
                    <?= htmlspecialchars($estadoLabel, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if ($dataCriacao !== ''): ?>
                    <span class="sp-num">Submetido em <?= htmlspecialchars($dataCriacao, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if (!empty($ultimoProjeto['area_tematica'])): ?>
                    <span aria-hidden="true">·</span>
                    <span>Área: <?= htmlspecialchars(ucfirst((string)$ultimoProjeto['area_tematica']), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <?php if ($mostrarStepperESp): ?>
                <?php
                $steps = [
                    ['id' => 'ideacao',            'label' => 'Ideação'],
                    ['id' => 'validacao',          'label' => 'Validação'],
                    ['id' => 'mvp',                'label' => 'MVP'],
                    ['id' => 'tracao',             'label' => 'Tração'],
                    ['id' => 'mercado',            'label' => 'Mercado'],
                    ['id' => 'fundo_investimento', 'label' => 'Financiado'],
                ];
                $currIdx = 0;
                foreach ($steps as $idx => $st) {
                    if ($faseKey === $st['id']) {
                        $currIdx = $idx;
                        break;
                    }
                }
                ?>
                <ol class="sp-stepper" aria-label="Fases de maturidade na incubadora">
                    <?php foreach ($steps as $idx => $st):
                        $isDone = ($idx < $currIdx) || ($estadoRaw === 'concluido');
                        $isCurr = ($idx === $currIdx) && ($estadoRaw !== 'concluido');
                    ?>
                        <li class="sp-step <?= $isDone ? 'is-done' : '' ?>" <?= $isCurr ? 'aria-current="step"' : '' ?>>
                            <span class="sp-step-dot" aria-hidden="true"></span>
                            <span><?= htmlspecialchars($st['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </header>

        <!-- ══ GRELHA PRINCIPAL (COLUNA DE FOCO + COLUNA DE APOIO) ══ -->
        <div class="row g-4 align-items-start">

            <!-- COLUNA PRINCIPAL: (2) O QUE TENHO DE FAZER A SEGUIR? -->
            <div class="col-lg-8">

                <?php if ($uiState === 'E1'): ?>
                    <!-- ESTADO E1: SUBMETIDO / EM AVALIAÇÃO TÉCNICA (SEM BOTÃO PRIMÁRIO) -->
                    <div class="sp-section-label">Situação do Processo</div>
                    <section class="sp-surface-primary" aria-labelledby="e1Heading">
                        <h2 id="e1Heading" class="fw-bold mb-2" style="font-size: var(--sp-fs-lg);">
                            Proposta em apreciação técnica
                        </h2>
                        <p class="mb-3" style="color: var(--sp-text-sec);">
                            O seu projecto encontra-se registado com o estado <strong><?= htmlspecialchars($estadoLabel, ENT_QUOTES, 'UTF-8') ?></strong>. Não existem ações pendentes da sua parte neste momento.
                        </p>
                        <?php if (!empty($ultimaAvaliacao['observacoes'])): ?>
                            <div class="p-3 mb-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm);">
                                <div class="sp-kicker mb-1">Observação registada</div>
                                <div><?= htmlspecialchars((string)$ultimaAvaliacao['observacoes'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>
                        <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php" class="sp-link">
                            <span>Consultar ficha do projecto</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </section>

                <?php elseif ($uiState === 'E2'): ?>
                    <!-- ESTADO E2: APROVADO A AGUARDAR TERMO DE INCUBAÇÃO -->
                    <div class="sp-section-label">Formalização da Incubação</div>
                    <section class="sp-surface-primary" aria-labelledby="e2Heading">
                        <h2 id="e2Heading" class="fw-bold mb-2" style="font-size: var(--sp-fs-lg);">
                            Projecto aprovado para incubação
                        </h2>
                        <p class="mb-3" style="color: var(--sp-text-sec);">
                            A entrada em incubação e a abertura das metas da fase de Ideação ocorrem após a assinatura do Termo de Incubação pela Direção.
                        </p>
                        <?php if ($termoProjeto): ?>
                            <div class="p-3 mb-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm);">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <span>Código do Termo: <strong class="sp-code"><?= htmlspecialchars((string)$termoProjeto['codigo_termo'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                                    <span class="sp-chip sp-chip-warn">
                                        <?= htmlspecialchars((string)$termoProjeto['estado'] === 'pendente_assinatura' ? 'Pendente de assinatura' : ucfirst((string)$termoProjeto['estado']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="sp-empty-line mb-3">Termo de Incubação a aguardar emissão pela coordenação.</div>
                        <?php endif; ?>

                        <?php if (!empty($ultimaAvaliacao['observacoes'])): ?>
                            <div class="p-3 mb-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm);">
                                <div class="sp-kicker mb-1">
                                    Parecer da avaliação<?php if (isset($ultimaAvaliacao['pontuacao_total'])): ?> (<span class="sp-num"><?= (int)$ultimaAvaliacao['pontuacao_total'] ?></span> pts)<?php endif; ?>
                                </div>
                                <div><?= htmlspecialchars((string)$ultimaAvaliacao['observacoes'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>

                        <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php" class="sp-link">
                            <span>Consultar ficha do projecto</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </section>

                <?php elseif ($uiState === 'E3' || $uiState === 'E4'): ?>
                    <!-- ESTADO E3 / E4: INCUBADO EM FASE X OU FASE CONCLUÍDA -->
                    <div class="sp-section-label">O que fazer a seguir</div>

                    <?php if ($uiState === 'E4'): ?>
                        <section class="sp-surface-primary" aria-labelledby="e4Heading">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <h2 id="e4Heading" class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">
                                    <?= $estadoRaw === 'concluido'
                                        ? 'Ciclo de incubação concluído'
                                        : 'Todas as metas da fase de ' . htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') . ' foram validadas' ?>
                                </h2>
                                <span class="sp-chip sp-chip-ok sp-num"><?= (int)$metasValidadasCount ?> de <?= (int)$totalMetasFase ?> validadas</span>
                            </div>
                            <p class="mb-3" style="color: var(--sp-text-sec);">
                                <?= $estadoRaw === 'concluido'
                                    ? 'O seu projecto concluiu o percurso de incubação.'
                                    : 'Os entregáveis desta fase estão validados pelo mentor. O avanço para a fase seguinte é efetuado pela coordenação.' ?>
                            </p>
                            <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php" class="sp-link">
                                <span>Consultar ficha do projecto</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </section>

                    <?php elseif ($metaPrincipal !== null): ?>
                        <?php
                        $estMetaP   = (string)($metaPrincipal['estado'] ?? 'activa');
                        $prazoMetaP = !empty($metaPrincipal['data_limite']) ? date('d/m/Y', strtotime((string)$metaPrincipal['data_limite'])) : '';
                        $atrasadaP  = !empty($metaPrincipal['data_limite']) && (strtotime((string)$metaPrincipal['data_limite']) < strtotime(date('Y-m-d')));
                        $payloadMeta = json_encode([
                            'id'     => (int)$metaPrincipal['id'],
                            'titulo' => (string)$metaPrincipal['meta_titulo'],
                            'tipo'   => (string)$metaPrincipal['evidencia_tipo'],
                            'desc'   => (string)$metaPrincipal['evidencia_desc'],
                            'texto'  => (string)($metaPrincipal['evidencia_texto'] ?? ''),
                            'link'   => (string)($metaPrincipal['evidencia_link'] ?? ''),
                            'path'   => (string)($metaPrincipal['evidencia_path'] ?? ''),
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                        <section class="sp-surface-primary" aria-labelledby="e3MetaHeading">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <div>
                                    <span class="sp-code fw-bold me-2" style="color: var(--sp-text-muted);">#<?= (int)$metaPrincipal['numero'] ?></span>
                                    <h2 id="e3MetaHeading" class="fw-bold d-inline m-0" style="font-size: var(--sp-fs-lg);">
                                        <?= htmlspecialchars((string)$metaPrincipal['meta_titulo'], ENT_QUOTES, 'UTF-8') ?>
                                    </h2>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($estMetaP === 'reprovada'): ?>
                                        <span class="sp-chip sp-chip-err">Devolvida</span>
                                    <?php else: ?>
                                        <span class="sp-chip sp-chip-warn">Activa</span>
                                    <?php endif; ?>
                                    <?php if ($prazoMetaP !== ''): ?>
                                        <span class="sp-chip <?= $atrasadaP ? 'sp-chip-err' : 'sp-chip-neutral' ?> sp-num">
                                            Prazo: <?= htmlspecialchars($prazoMetaP, ENT_QUOTES, 'UTF-8') ?><?= $atrasadaP ? ' (Atrasado)' : '' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="mb-3" style="color: var(--sp-text-sec);">
                                <?= htmlspecialchars((string)$metaPrincipal['meta_descricao'], ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <div class="p-3 mb-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm);">
                                <strong>Entregável esperado:</strong>
                                <?= htmlspecialchars((string)$metaPrincipal['evidencia_desc'], ENT_QUOTES, 'UTF-8') ?>
                                (Tipo: <?= htmlspecialchars(ucfirst((string)$metaPrincipal['evidencia_tipo']), ENT_QUOTES, 'UTF-8') ?>)
                            </div>

                            <?php if (!empty($metaPrincipal['feedback_mentor'])): ?>
                                <div class="p-3 mb-3" style="background: var(--sp-err-bg); border: 1px solid var(--sp-err-border); color: var(--sp-err-text); border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm);">
                                    <strong>Feedback de correção do mentor:</strong>
                                    <?= htmlspecialchars((string)$metaPrincipal['feedback_mentor'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <?php if (!$isAdminOrSuper): ?>
                                    <button type="button" class="sp-btn-primary" onclick="abrirSubmeterEvidencia(<?= htmlspecialchars((string)$payloadMeta, ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                        <span>Submeter Evidência</span>
                                    </button>
                                <?php endif; ?>
                                <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php" class="sp-link">
                                    <span>Ver ficha do projecto</span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </section>

                    <?php elseif ($metaEmAvaliacao !== null): ?>
                        <?php
                        $dataSubEv = !empty($metaEmAvaliacao['evidencia_em'])
                            ? date('d/m/Y H:i', strtotime((string)$metaEmAvaliacao['evidencia_em']))
                            : '';
                        ?>
                        <section class="sp-surface-primary" aria-labelledby="e3WaitHeading">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <h2 id="e3WaitHeading" class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">
                                    <span class="sp-code me-1">#<?= (int)$metaEmAvaliacao['numero'] ?></span>
                                    <?= htmlspecialchars((string)$metaEmAvaliacao['meta_titulo'], ENT_QUOTES, 'UTF-8') ?>
                                </h2>
                                <span class="sp-chip sp-chip-warn">A avaliar pelo mentor</span>
                            </div>
                            <p class="mb-3" style="color: var(--sp-text-sec);">
                                Evidência submetida<?= $dataSubEv !== '' ? ' em <span class="sp-num">' . htmlspecialchars($dataSubEv, ENT_QUOTES, 'UTF-8') . '</span>' : '' ?>. Aguarda validação do mentor atribuído.
                            </p>
                            <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php" class="sp-link">
                                <span>Consultar ficha do projecto</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </section>

                    <?php else: ?>
                        <section class="sp-surface-primary" aria-labelledby="e3NoActiveHeading">
                            <h2 id="e3NoActiveHeading" class="fw-bold mb-2" style="font-size: var(--sp-fs-lg);">
                                Fase Actual: <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?>
                            </h2>
                            <p class="mb-3" style="color: var(--sp-text-sec);">
                                <?= $totalMetasFase === 0
                                    ? 'Ainda não existem metas inicializadas para esta fase.'
                                    : 'As metas desta fase encontram-se bloqueadas a aguardar ativação pela administração.' ?>
                            </p>
                            <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php" class="sp-link">
                                <span>Consultar ficha do projecto</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </section>
                    <?php endif; ?>

                    <!-- LISTA EDITORIAL DE METAS DA FASE (SEM CARDS) -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="sp-section-label m-0">Jornada de Metas: <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?></div>
                        <span class="sp-num" style="font-size: var(--sp-fs-xs); color: var(--sp-text-muted);">
                            <?= (int)$metasValidadasCount ?> de <?= (int)$totalMetasFase ?> metas validadas
                        </span>
                    </div>

                    <?php if (empty($metasFase)): ?>
                        <div class="sp-empty-line mb-4">Sem metas definidas para a fase de <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?>.</div>
                    <?php else: ?>
                        <ul class="sp-list">
                            <?php foreach ($metasFase as $m):
                                $estM = (string)($m['estado'] ?? 'inactiva');
                                $chipM = 'sp-chip-neutral';
                                $lblM  = 'Bloqueada';
                                if ($estM === 'concluida') {
                                    $chipM = 'sp-chip-ok';
                                    $lblM  = 'Concluída';
                                } elseif ($estM === 'activa') {
                                    $chipM = 'sp-chip-warn';
                                    $lblM  = 'Activa';
                                } elseif ($estM === 'em_avaliacao') {
                                    $chipM = 'sp-chip-warn';
                                    $lblM  = 'A avaliar';
                                } elseif ($estM === 'reprovada') {
                                    $chipM = 'sp-chip-err';
                                    $lblM  = 'Devolvida';
                                }
                                $prazoM = !empty($m['data_limite']) ? date('d/m/Y', strtotime((string)$m['data_limite'])) : '';
                                $isSecondaryActionable = (!$isAdminOrSuper)
                                    && ($estM === 'activa' || $estM === 'reprovada')
                                    && ($metaPrincipal === null || (int)$m['id'] !== (int)$metaPrincipal['id']);
                            ?>
                                <li class="sp-list-row">
                                    <div>
                                        <span class="sp-code fw-semibold me-2" style="color: var(--sp-text-muted);">#<?= (int)$m['numero'] ?></span>
                                        <strong style="color: var(--sp-text);"><?= htmlspecialchars((string)$m['meta_titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if ($prazoM !== ''): ?>
                                            <span class="sp-num ms-2" style="font-size: var(--sp-fs-xs); color: var(--sp-text-muted);">
                                                · Prazo: <?= htmlspecialchars($prazoM, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($isSecondaryActionable):
                                            $rowPayload = json_encode([
                                                'id'     => (int)$m['id'],
                                                'titulo' => (string)$m['meta_titulo'],
                                                'tipo'   => (string)$m['evidencia_tipo'],
                                                'desc'   => (string)$m['evidencia_desc'],
                                                'texto'  => (string)($m['evidencia_texto'] ?? ''),
                                                'link'   => (string)($m['evidencia_link'] ?? ''),
                                                'path'   => (string)($m['evidencia_path'] ?? ''),
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                        ?>
                                            <button type="button" class="sp-btn-secondary" onclick="abrirSubmeterEvidencia(<?= htmlspecialchars((string)$rowPayload, ENT_QUOTES, 'UTF-8') ?>)">
                                                <span>Submeter Evidência</span>
                                            </button>
                                        <?php endif; ?>
                                        <span class="sp-chip <?= $chipM ?>"><?= htmlspecialchars($lblM, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <!-- LISTA EDITORIAL DE TAREFAS DO MENTOR -->
                    <div class="sp-section-label">Tarefas de Acompanhamento</div>
                    <?php if (empty($minhasTarefas)): ?>
                        <div class="sp-empty-line">Sem tarefas de acompanhamento atribuídas pelo mentor.</div>
                    <?php else: ?>
                        <ul class="sp-list">
                            <?php foreach ($minhasTarefas as $t):
                                $statusT  = (string)($t['status'] ?? '');
                                $valMent  = (int)($t['validada_mentor'] ?? 0) === 1;
                                $conclT   = $valMent || $statusT === 'concluida' || $statusT === 'concluid';
                                $prazoT   = !empty($t['data_limite']) ? date('d/m/Y', strtotime((string)$t['data_limite'])) : '';
                                $chipTar  = $valMent ? 'sp-chip-ok' : ($conclT ? 'sp-chip-warn' : 'sp-chip-neutral');
                                $lblTar   = $valMent ? 'Validada' : ($conclT ? 'Aguardando Validação' : 'Pendente');
                            ?>
                                <li class="sp-list-row">
                                    <div>
                                        <strong style="color: var(--sp-text); <?= $conclT ? 'text-decoration: line-through; opacity: 0.75;' : '' ?>">
                                            <?= htmlspecialchars((string)$t['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                        </strong>
                                        <?php if (!empty($t['descricao'])): ?>
                                            <div style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec);">
                                                <?= htmlspecialchars((string)$t['descricao'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($prazoT !== ''): ?>
                                            <div class="sp-num" style="font-size: var(--sp-fs-xs); color: var(--sp-text-muted);">
                                                Prazo: <?= htmlspecialchars($prazoT, ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="sp-chip <?= $chipTar ?>"><?= htmlspecialchars($lblTar, ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                <?php elseif ($uiState === 'E5'): ?>
                    <!-- ESTADO E5: PROJETO REJEITADO -->
                    <div class="sp-section-label">Decisão Registada</div>
                    <section class="sp-surface-primary" aria-labelledby="e5Heading">
                        <h2 id="e5Heading" class="fw-bold mb-2" style="font-size: var(--sp-fs-lg);">
                            Proposta não aprovada nesta submissão
                        </h2>
                        <?php if (!empty($ultimaAvaliacao['observacoes'])): ?>
                            <div class="p-3 mb-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm);">
                                <div class="sp-kicker mb-1">Observações registadas na avaliação</div>
                                <div><?= htmlspecialchars((string)$ultimaAvaliacao['observacoes'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php else: ?>
                            <p class="mb-3" style="color: var(--sp-text-sec);">
                                A proposta submetida foi encerrada com o estado Rejeitado. Pode submeter uma nova proposta revista.
                            </p>
                        <?php endif; ?>

                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <?php if (!$isAdminOrSuper): ?>
                                <button type="button" class="sp-btn-primary" data-bs-toggle="modal" data-bs-target="#modalProjeto">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                    <span>Submeter Ideia</span>
                                </button>
                            <?php endif; ?>
                            <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php" class="sp-link">
                                <span>Consultar ficha do projecto</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </section>

                <?php else: ?>
                    <!-- ESTADO DE SALVAGUARDA (E_FALLBACK): QUALQUER ESTADO DESCONHECIDO -->
                    <div class="sp-section-label">Situação do Projecto</div>
                    <section class="sp-surface-primary" aria-labelledby="eFallbackHeading">
                        <h2 id="eFallbackHeading" class="fw-bold mb-2" style="font-size: var(--sp-fs-lg);">
                            <?= htmlspecialchars((string)$ultimoProjeto['titulo'], ENT_QUOTES, 'UTF-8') ?>
                        </h2>
                        <p class="mb-3" style="color: var(--sp-text-sec);">
                            Estado registado no sistema: <span class="sp-code fw-bold"><?= htmlspecialchars($estadoRaw, ENT_QUOTES, 'UTF-8') ?></span>.
                        </p>
                        <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php" class="sp-link">
                            <span>Consultar ficha do projecto</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </section>
                <?php endif; ?>

            </div>

            <!-- COLUNA LATERAL: (3) QUEM ME AJUDA E QUANDO? (SUPERFÍCIE #2) -->
            <div class="col-lg-4">
                <div class="sp-section-label">Quem acompanha e agenda</div>
                <aside class="sp-surface-aside" aria-label="Mentoria, termo e reservas">

                    <!-- BLOCO 1: MENTOR ATRIBUÍDO -->
                    <div class="sp-aside-block">
                        <div class="sp-kicker">Mentor Atribuído</div>
                        <?php if ($mentorAtivo): ?>
                            <div class="fw-bold" style="color: var(--sp-text);">
                                <?= htmlspecialchars((string)$mentorAtivo['mentor_nome'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if (!empty($mentorAtivo['especialidade'])): ?>
                                <div style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec);">
                                    <?= htmlspecialchars((string)$mentorAtivo['especialidade'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($mentorAtivo['mentor_email'])): ?>
                                <div class="sp-code mt-1" style="color: var(--sp-text-muted);">
                                    <?= htmlspecialchars((string)$mentorAtivo['mentor_email'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="font-size: var(--sp-fs-sm); color: var(--sp-text-muted);">
                                Sem mentor ativo atribuído neste momento.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- BLOCO 2: REUNIÕES E SESSÕES -->
                    <div class="sp-aside-block">
                        <div class="sp-kicker">Agenda de Mentoria</div>
                        <?php if (!empty($minhasReunioes)):
                            $proxReuniao = $minhasReunioes[0];
                            $dataReuniao = date('d/m/Y H:i', strtotime((string)$proxReuniao['data_reuniao']));
                        ?>
                            <div class="fw-semibold" style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                                <?= htmlspecialchars((string)$proxReuniao['titulo'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="sp-num" style="font-size: var(--sp-fs-xs); color: var(--sp-accent);">
                                Próxima reunião: <?= htmlspecialchars($dataReuniao, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if (!empty($proxReuniao['local_link'])): ?>
                                <div style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec);">
                                    Local/Link: <?= htmlspecialchars((string)$proxReuniao['local_link'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($ultimaSessao):
                            $dataUltSessao = date('d/m/Y', strtotime((string)$ultimaSessao['data_sessao']));
                        ?>
                            <div class="sp-num" style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec);">
                                Última sessão: <?= htmlspecialchars($dataUltSessao, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if (!empty($ultimaSessao['proximos_passos'])): ?>
                                <div class="mt-1" style="font-size: var(--sp-fs-xs); color: var(--sp-text);">
                                    <strong>Próximos passos:</strong> <?= htmlspecialchars((string)$ultimaSessao['proximos_passos'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="font-size: var(--sp-fs-sm); color: var(--sp-text-muted);">
                                Sem reuniões agendadas.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- BLOCO 3: TERMO DE INCUBAÇÃO -->
                    <div class="sp-aside-block">
                        <div class="sp-kicker">Termo de Incubação</div>
                        <?php if ($termoProjeto):
                            $estTermo = (string)($termoProjeto['estado'] ?? '');
                            $dataAssinatura = !empty($termoProjeto['assinado_em'])
                                ? date('d/m/Y', strtotime((string)$termoProjeto['assinado_em']))
                                : '';
                        ?>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span class="sp-code fw-bold"><?= htmlspecialchars((string)$termoProjeto['codigo_termo'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="sp-chip <?= $estTermo === 'assinado' ? 'sp-chip-ok' : 'sp-chip-warn' ?>">
                                    <?= htmlspecialchars($estTermo === 'assinado' ? 'Assinado' : ($estTermo === 'pendente_assinatura' ? 'Pendente' : ucfirst($estTermo)), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <?php if ($dataAssinatura !== ''): ?>
                                <div class="sp-num mt-1" style="font-size: var(--sp-fs-xs); color: var(--sp-text-muted);">
                                    Assinado em <?= htmlspecialchars($dataAssinatura, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="font-size: var(--sp-fs-sm); color: var(--sp-text-muted);">
                                Sem termo emitido para este projecto.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- BLOCO 4: RESERVAS DE ESPAÇO -->
                    <div class="sp-aside-block">
                        <div class="sp-kicker">Reservas de Espaço</div>
                        <div class="sp-num mb-1" style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                            <?= $totalReservasConfirmadas > 0
                                ? $totalReservasConfirmadas . ' reserva' . ($totalReservasConfirmadas > 1 ? 's' : '') . ' ativa' . ($totalReservasConfirmadas > 1 ? 's' : '')
                                : 'Sem reservas ativas' ?>
                        </div>
                        <a href="/incubadora_ispsn/app/views/utilizador/reservas.php" class="sp-link">
                            <span>Gerir reservas</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>

                </aside>
            </div>

        </div>
    <?php endif; ?>

</div>

<?php if (!$isAdminOrSuper): ?>
<!-- ══ MODAL SUBMETER PROJETO ══ -->
<div class="modal fade" id="modalProjeto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="criar_projeto">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/public/index.php">
                <div class="modal-header-custom">
                    <h5 class="fw-bold m-0"><i class="fa-solid fa-rocket me-2" aria-hidden="true"></i>Submeter Ideia de Startup</h5>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Nome da Startup *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: AppAngola — Transporte Local">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Tipo *</label>
                            <select name="tipo" class="form-control-custom" required>
                                <option value="startup_tecnologica">Startup Tecnológica</option>
                                <option value="negocio_tradicional">Negócio Tradicional</option>
                                <option value="impacto_social">Impacto Social</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Área</label>
                            <select name="area" class="form-control-custom">
                                <option value="tecnologia">Tecnologia</option>
                                <option value="saude">Saúde</option>
                                <option value="educacao">Educação</option>
                                <option value="agro">Agronegócio</option>
                                <option value="financas">Finanças</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Descrição *</label>
                        <textarea name="descricao" class="form-control-custom" rows="3" required placeholder="Descreva o projecto..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Problema que resolve</label>
                        <textarea name="problema" class="form-control-custom" rows="2" placeholder="Qual o problema identificado?"></textarea>
                    </div>
                    <div>
                        <label class="form-label-custom">Solução Proposta</label>
                        <textarea name="solucao" class="form-control-custom" rows="2" placeholder="Como a sua startup resolve o problema?"></textarea>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i>Submeter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ MODAL SUBMETER EVIDÊNCIA ══ -->
<div class="modal fade" id="modalSubmeterEvidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/metas_action.php" method="POST" enctype="multipart/form-data" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="submeter_evidencia">
                <input type="hidden" name="id_meta_projeto" id="submeterMetaId">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">

                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold m-0" id="submeterTituloMeta">Submeter Evidência</h5>
                </div>

                <div class="modal-body-custom">
                    <div class="alert-custom alert-warning py-2 px-3 mb-3">
                        <div><strong>Entregável esperado:</strong> <span id="submeterDescEvidencia"></span></div>
                    </div>

                    <div class="mb-3 text-start">
                        <label class="form-label-custom" for="submeterTextoInput">Relatório/Descrição da Execução *</label>
                        <textarea name="evidencia_texto" id="submeterTextoInput" class="form-control-custom" rows="4" required placeholder="Especifique em detalhe as tarefas que realizou..."></textarea>
                    </div>

                    <div class="mb-3 text-start" id="submeterLinkContainer">
                        <label class="form-label-custom" for="submeterLinkInput">Link Externo (URL) *</label>
                        <input type="url" name="evidencia_link" id="submeterLinkInput" class="form-control-custom" placeholder="https://exemplo.com/doc-ou-drive">
                    </div>

                    <div class="mb-0 text-start" id="submeterFicheiroContainer">
                        <label class="form-label-custom" for="submeterFicheiroInput">Ficheiro Comprovativo (PDF, Zip, Imagem) *</label>
                        <input type="file" name="evidencia_ficheiro" id="submeterFicheiroInput" class="form-control-custom">
                        <div id="submeterFicheiroAtual" class="small text-muted mt-1"></div>
                    </div>
                </div>

                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Enviar Evidência</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirSubmeterEvidencia(meta) {
    document.getElementById('submeterMetaId').value = meta.id || '';
    document.getElementById('submeterTituloMeta').textContent = 'Submeter: ' + (meta.titulo || '');
    document.getElementById('submeterDescEvidencia').textContent = (meta.desc || '') + ' (Tipo: ' + String(meta.tipo || '').toUpperCase() + ')';
    document.getElementById('submeterTextoInput').value = meta.texto || '';

    const linkContainer = document.getElementById('submeterLinkContainer');
    const linkInput     = document.getElementById('submeterLinkInput');
    const fileContainer = document.getElementById('submeterFicheiroContainer');
    const fileInput     = document.getElementById('submeterFicheiroInput');
    const fileAtualDiv  = document.getElementById('submeterFicheiroAtual');

    linkInput.value = meta.link || '';
    fileInput.value = '';
    fileAtualDiv.textContent = '';

    if (meta.tipo === 'link') {
        linkContainer.style.display = 'block';
        linkInput.required = true;
        fileContainer.style.display = 'none';
        fileInput.required = false;
    } else if (meta.tipo === 'ficheiro') {
        linkContainer.style.display = 'block';
        linkInput.required = false;
        fileContainer.style.display = 'block';

        if (meta.path) {
            fileInput.required = false;
            fileAtualDiv.textContent = 'Já existe um comprovativo submetido anteriormente.';
        } else {
            fileInput.required = true;
        }
    } else {
        linkContainer.style.display = 'none';
        linkInput.required = false;
        fileContainer.style.display = 'none';
        fileInput.required = false;
    }

    new bootstrap.Modal(document.getElementById('modalSubmeterEvidencia')).show();
}
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/../partials/_layout_end.php';
?>
