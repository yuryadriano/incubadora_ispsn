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

$qsPreview        = ($isAdminOrSuper && $idUsuario > 0) ? ('?id_usuario=' . (int)$idUsuario) : '';
$hrefFichaProjeto = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php' . $qsPreview;
$hrefMetasProjeto = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php'
    . ($qsPreview !== '' ? ($qsPreview . '&tab=metas') : '?tab=metas');
$hrefReservas     = '/incubadora_ispsn/app/views/utilizador/reservas.php' . $qsPreview;

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

$labelsTipo = [
    'startup_tecnologica' => 'Startup Tecnológica',
    'negocio_tradicional' => 'Negócio Tradicional',
    'impacto_social'      => 'Impacto Social',
    'outro'               => 'Outro',
];

$mentorAtivo         = null;
$termoProjeto        = null;
$ultimaAvaliacao     = null;
$ultimoComentario    = null;
$metasFase           = [];
$tarefasPendentes    = [];
$minhasReunioes      = [];
$ultimaSessao        = null;
$metasValidadasCount = 0;
$totalMetasFase      = 0;
$metaPrincipal       = null;
$metaEmAvaliacao     = null;
$numMembros          = 0;
$numDocumentos       = 0;
$numComentarios      = 0;

if ($ultimoProjeto) {
    $idProj     = (int)$ultimoProjeto['id'];
    $estadoProj = (string)($ultimoProjeto['estado'] ?? '');
    $faseActual = (string)($ultimoProjeto['fase'] ?? 'ideacao');

    /* ── CONTAGENS REAIS DO DOSSIÊ (Equipa, Documentos, Comentários) ── */
    $stmtCnt = $mysqli->prepare("
        SELECT
            (SELECT COUNT(*) FROM membros_projeto WHERE id_projeto = ?) AS n_membros,
            (SELECT COUNT(*) FROM ficheiros_projeto WHERE id_projeto = ?) AS n_docs,
            (SELECT COUNT(*) FROM comentarios_projetos WHERE id_projeto = ?) AS n_com
    ");
    if ($stmtCnt) {
        $stmtCnt->bind_param('iii', $idProj, $idProj, $idProj);
        $stmtCnt->execute();
        $cntRow = $stmtCnt->get_result()->fetch_assoc();
        $stmtCnt->close();
        $numMembros     = max(1, (int)($cntRow['n_membros'] ?? 0));
        $numDocumentos  = (int)($cntRow['n_docs'] ?? 0) + (!empty($ultimoProjeto['pitch_path']) ? 1 : 0);
        $numComentarios = (int)($cntRow['n_com'] ?? 0);
    }

    /* ── ÚLTIMO COMENTÁRIO OFICIAL ── */
    $stmtCom = $mysqli->prepare("
        SELECT c.comentario, c.criado_em, u.nome AS autor_nome
        FROM comentarios_projetos c
        JOIN usuarios u ON u.id = c.id_usuario
        WHERE c.id_projeto = ?
        ORDER BY c.criado_em DESC LIMIT 1
    ");
    if ($stmtCom) {
        $stmtCom->bind_param('i', $idProj);
        $stmtCom->execute();
        $ultimoComentario = $stmtCom->get_result()->fetch_assoc();
        $stmtCom->close();
    }

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
        SELECT *
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

    /* ── 4. ÚLTIMA AVALIAÇÃO OFICIAL ── */
    $stmtAv = $mysqli->prepare("
        SELECT pontuacao_total, observacoes, avaliado_em
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

    /* ── 6. TAREFAS PENDENTES DO MENTOR ── */
    $stmtT = $mysqli->prepare("
        SELECT *
        FROM tarefas
        WHERE id_projeto = ? AND COALESCE(validada_mentor, 0) = 0
        ORDER BY data_limite ASC, id DESC
        LIMIT 4
    ");
    if ($stmtT) {
        $stmtT->bind_param('i', $idProj);
        $stmtT->execute();
        $tarefasPendentes = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtT->close();
    }

    /* ── 7. PRÓXIMAS REUNIÕES AGENDADAS ── */
    $stmtR = $mysqli->prepare("
        SELECT r.*, u.nome AS mentor_nome
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
        SELECT s.*, u.nome AS mentor_nome
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
    SELECT r.*, e.nome AS espaco_nome, e.tipo AS espaco_tipo
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

$tituloPagina = 'Painel Geral';
$paginaActiva = 'dashboard';
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<div class="std-painel">

    <?php if ($flashOk): ?>
        <div class="alert-custom alert-success mb-3" role="status">
            <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
            <span><?= htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>
    <?php if ($flashErro): ?>
        <div class="alert-custom alert-danger mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span><?= htmlspecialchars($flashErro, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <?php if ($uiState === 'E0'): ?>
        <!-- ══ ESTADO E0: SEM PROJETO SUBMETIDO ══ -->
        <header class="sp-header">
            <div class="sp-header-top">
                <div class="sp-kicker">Painel Geral · Empreendedor</div>
                <span class="sp-chip sp-chip-neutral">Sem projecto submetido</span>
            </div>
            <h1 class="sp-title"><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></h1>
        </header>

        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <section class="sp-surface-primary" aria-labelledby="e0Heading">
                    <div class="sp-card-head">
                        <div class="sp-card-head-title">
                            <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-rocket"></i></span>
                            <div>
                                <div class="sp-kicker">Próximo Passo</div>
                                <h2 id="e0Heading" class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Submeter proposta de projecto</h2>
                            </div>
                        </div>
                    </div>
                    <p class="mb-4" style="color: var(--sp-text-sec);">
                        Registe a proposta da sua startup para apreciação pela coordenação da incubadora.
                    </p>
                    <?php if (!$isAdminOrSuper): ?>
                        <button type="button" class="sp-btn-primary" data-bs-toggle="modal" data-bs-target="#modalProjeto">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            <span>Submeter Primeira Ideia</span>
                        </button>
                    <?php else: ?>
                        <div class="sp-empty-line">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            <span>Esta conta de estudante ainda não submeteu nenhum projecto.</span>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <div class="col-lg-4">
                <aside class="sp-surface-aside" aria-label="Recursos disponíveis">
                    <div class="sp-aside-block">
                        <span class="sp-aside-icon" aria-hidden="true"><i class="fa-solid fa-bookmark"></i></span>
                        <div class="sp-aside-body">
                            <div class="sp-kicker">Reservas de Espaço</div>
                            <div class="sp-num mb-1 fw-semibold" style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                                <?= $totalReservasConfirmadas > 0
                                    ? $totalReservasConfirmadas . ' reserva' . ($totalReservasConfirmadas > 1 ? 's' : '') . ' confirmada' . ($totalReservasConfirmadas > 1 ? 's' : '')
                                    : 'Sem reservas confirmadas' ?>
                            </div>
                            <a href="<?= htmlspecialchars($hrefReservas, ENT_QUOTES, 'UTF-8') ?>" class="sp-link">
                                <span>Reservas de Espaço</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
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
        $tipoKey     = (string)($ultimoProjeto['tipo'] ?? '');
        $tipoLabel   = $labelsTipo[$tipoKey] ?? ($tipoKey !== '' ? ucfirst(str_replace('_', ' ', $tipoKey)) : '');
        $pontos      = (int)($ultimoProjeto['pontos'] ?? 0);
        $dataCriacao = !empty($ultimoProjeto['criado_em'])
            ? date('d/m/Y', strtotime((string)$ultimoProjeto['criado_em']))
            : '';

        $chipClass = 'sp-chip-neutral';
        $chipIcon  = 'fa-circle-info';
        if (in_array($estadoRaw, ['aprovado', 'incubado', 'fundo_investimento', 'concluido'], true)) {
            $chipClass = 'sp-chip-ok';
            $chipIcon  = ($estadoRaw === 'incubado') ? 'fa-rocket' : 'fa-circle-check';
        } elseif (in_array($estadoRaw, ['submetido', 'em_avaliacao'], true)) {
            $chipClass = 'sp-chip-warn';
            $chipIcon  = 'fa-magnifying-glass';
        } elseif ($estadoRaw === 'rejeitado') {
            $chipClass = 'sp-chip-err';
            $chipIcon  = 'fa-circle-xmark';
        }

        $mostrarStepperESp = in_array($uiState, ['E3', 'E4'], true);
        ?>

        <!-- ══ 1. CABEÇALHO DO PROJECTO ══ -->
        <header class="sp-header">
            <div class="sp-header-top">
                <div class="sp-kicker">
                    Painel Geral<?php if ($mostrarStepperESp): ?> · Fase Actual: <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                </div>
                <span class="sp-chip <?= $chipClass ?>">
                    <i class="fa-solid <?= $chipIcon ?>" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($estadoLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h1 class="sp-title"><?= htmlspecialchars((string)$ultimoProjeto['titulo'], ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if ($mostrarStepperESp): ?>
                    <div class="sp-metrics-box">
                        <span class="sp-num fw-semibold" style="color: var(--sp-text);">
                            <i class="fa-solid fa-list-check me-1" style="color: var(--sp-accent);" aria-hidden="true"></i>
                            <?= (int)$metasValidadasCount ?> de <?= (int)$totalMetasFase ?> metas validadas
                        </span>
                        <?php if ($pontos > 0): ?>
                            <span style="color: var(--sp-border-strong);" aria-hidden="true">|</span>
                            <span class="sp-code fw-bold" style="color: var(--sp-accent);">
                                <i class="fa-solid fa-trophy me-1" aria-hidden="true"></i><?= (int)$pontos ?> SP
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sp-meta-row">
                <?php if ($dataCriacao !== ''): ?>
                    <span class="sp-meta-pill sp-num">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <span>Submetido em <?= htmlspecialchars($dataCriacao, ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                <?php endif; ?>
                <?php if (!empty($ultimoProjeto['area_tematica'])): ?>
                    <span class="sp-meta-pill">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <span>Área: <?= htmlspecialchars(ucfirst((string)$ultimoProjeto['area_tematica']), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                <?php endif; ?>
                <?php if ($tipoLabel !== ''): ?>
                    <span class="sp-meta-pill">
                        <i class="fa-solid fa-tag" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($tipoLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
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
                            <span class="sp-step-dot" aria-hidden="true">
                                <?php if ($isDone): ?>
                                    <i class="fa-solid fa-check"></i>
                                <?php else: ?>
                                    <?= (int)($idx + 1) ?>
                                <?php endif; ?>
                            </span>
                            <span class="text-truncate"><?= htmlspecialchars($st['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </header>

        <!-- ══ GRELHA PRINCIPAL ══ -->
        <div class="row g-4 align-items-start">

            <!-- COLUNA PRINCIPAL: PRÓXIMA AÇÃO REAL -->
            <div class="col-lg-8">

                <?php if ($uiState === 'E1'): ?>
                    <!-- ESTADO E1: SUBMETIDO / EM AVALIAÇÃO TÉCNICA -->
                    <section class="sp-surface-primary" aria-labelledby="e1Heading">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-magnifying-glass-chart"></i></span>
                                <div>
                                    <div class="sp-kicker">Estado da Candidatura</div>
                                    <h2 id="e1Heading" class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">
                                        <?= htmlspecialchars($estadoLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </h2>
                                </div>
                            </div>
                            <span class="sp-chip sp-chip-warn">Em apreciação</span>
                        </div>

                        <p class="mb-3" style="color: var(--sp-text-sec);">
                            A proposta foi recebida<?= $dataCriacao !== '' ? ' em <strong>' . htmlspecialchars($dataCriacao, ENT_QUOTES, 'UTF-8') . '</strong>' : '' ?> e encontra-se em apreciação técnica. Enquanto decorre a análise, pode consultar ou complementar a equipa e os documentos na página <strong>Minha Startup</strong>.
                        </p>

                        <?php if (!empty($ultimaAvaliacao['observacoes'])): ?>
                            <div class="p-3 mb-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm);">
                                <div class="sp-kicker mb-1">Observação da Avaliação</div>
                                <div><?= htmlspecialchars((string)$ultimaAvaliacao['observacoes'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php elseif ($ultimoComentario): ?>
                            <div class="p-3 mb-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm);">
                                <div class="sp-kicker mb-1">
                                    Último feedback · <?= htmlspecialchars((string)$ultimoComentario['autor_nome'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div><?= htmlspecialchars((string)$ultimoComentario['comentario'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>

                        <a href="<?= htmlspecialchars($hrefFichaProjeto, ENT_QUOTES, 'UTF-8') ?>" class="sp-btn-secondary">
                            <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                            <span>Abrir Minha Startup</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </section>

                <?php elseif ($uiState === 'E2'): ?>
                    <!-- ESTADO E2: APROVADO A AGUARDAR TERMO DE INCUBAÇÃO -->
                    <section class="sp-surface-primary" aria-labelledby="e2Heading">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-file-contract"></i></span>
                                <div>
                                    <div class="sp-kicker">Formalização da Incubação</div>
                                    <h2 id="e2Heading" class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">
                                        Projecto aprovado para incubação
                                    </h2>
                                </div>
                            </div>
                            <span class="sp-chip sp-chip-ok">Aprovado</span>
                        </div>

                        <p class="mb-3" style="color: var(--sp-text-sec);">
                            A abertura das metas da fase de Ideação ocorre após a assinatura do Termo de Incubação pela Direção.
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
                        <?php endif; ?>

                        <?php if (!empty($ultimaAvaliacao['observacoes'])): ?>
                            <div class="p-3 mb-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm);">
                                <div class="sp-kicker mb-1">
                                    Parecer da avaliação<?php if (isset($ultimaAvaliacao['pontuacao_total'])): ?> (<span class="sp-num"><?= (int)$ultimaAvaliacao['pontuacao_total'] ?></span> pts)<?php endif; ?>
                                </div>
                                <div><?= htmlspecialchars((string)$ultimaAvaliacao['observacoes'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>

                        <a href="<?= htmlspecialchars($hrefFichaProjeto, ENT_QUOTES, 'UTF-8') ?>" class="sp-btn-secondary">
                            <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                            <span>Abrir Minha Startup</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </section>

                <?php elseif ($uiState === 'E3' || $uiState === 'E4'): ?>
                    <!-- ESTADO E3 / E4: INCUBADO EM FASE X OU FASE CONCLUÍDA -->
                    <?php if ($uiState === 'E4'): ?>
                        <section class="sp-surface-primary" aria-labelledby="e4Heading">
                            <div class="sp-card-head">
                                <div class="sp-card-head-title">
                                    <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-award"></i></span>
                                    <div>
                                        <div class="sp-kicker">Estado da Fase</div>
                                        <h2 id="e4Heading" class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">
                                            <?= $estadoRaw === 'concluido'
                                                ? 'Ciclo de incubação concluído'
                                                : 'Todas as metas da fase de ' . htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') . ' foram validadas' ?>
                                        </h2>
                                    </div>
                                </div>
                                <span class="sp-chip sp-chip-ok sp-num"><?= (int)$metasValidadasCount ?> de <?= (int)$totalMetasFase ?> validadas</span>
                            </div>
                            <p class="mb-3" style="color: var(--sp-text-sec);">
                                <?= $estadoRaw === 'concluido'
                                    ? 'O seu projecto concluiu o percurso de incubação.'
                                    : 'Os entregáveis desta fase estão validados pelo mentor. O avanço para a fase seguinte é efetuado pela coordenação.' ?>
                            </p>
                            <a href="<?= htmlspecialchars($hrefMetasProjeto, ENT_QUOTES, 'UTF-8') ?>" class="sp-btn-secondary">
                                <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                                <span>Ver histórico de metas em Minha Startup</span>
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
                            <div class="sp-card-head">
                                <div class="sp-card-head-title">
                                    <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-bullseye"></i></span>
                                    <div>
                                        <div class="sp-kicker">Próxima Entrega · Meta <span class="sp-code">#<?= (int)$metaPrincipal['numero'] ?></span></div>
                                        <h2 id="e3MetaHeading" class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">
                                            <?= htmlspecialchars((string)$metaPrincipal['meta_titulo'], ENT_QUOTES, 'UTF-8') ?>
                                        </h2>
                                    </div>
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

                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pt-1">
                                <?php if (!$isAdminOrSuper): ?>
                                    <button type="button" class="sp-btn-primary" onclick="abrirSubmeterEvidencia(<?= htmlspecialchars((string)$payloadMeta, ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                        <span>Submeter Evidência</span>
                                    </button>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars($hrefMetasProjeto, ENT_QUOTES, 'UTF-8') ?>" class="sp-link">
                                    <span>Ver todas as metas da fase (<?= (int)$metasValidadasCount ?>/<?= (int)$totalMetasFase ?>)</span>
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
                            <div class="sp-card-head">
                                <div class="sp-card-head-title">
                                    <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span>
                                    <div>
                                        <div class="sp-kicker">Meta <span class="sp-code">#<?= (int)$metaEmAvaliacao['numero'] ?></span></div>
                                        <h2 id="e3WaitHeading" class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">
                                            <?= htmlspecialchars((string)$metaEmAvaliacao['meta_titulo'], ENT_QUOTES, 'UTF-8') ?>
                                        </h2>
                                    </div>
                                </div>
                                <span class="sp-chip sp-chip-warn">A avaliar pelo mentor</span>
                            </div>
                            <p class="mb-3" style="color: var(--sp-text-sec);">
                                Evidência submetida<?= $dataSubEv !== '' ? ' em <span class="sp-num">' . htmlspecialchars($dataSubEv, ENT_QUOTES, 'UTF-8') . '</span>' : '' ?>. Aguarda validação do mentor atribuído.
                            </p>
                            <a href="<?= htmlspecialchars($hrefMetasProjeto, ENT_QUOTES, 'UTF-8') ?>" class="sp-btn-secondary">
                                <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                                <span>Ver metas e entregáveis em Minha Startup</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </section>

                    <?php else: ?>
                        <section class="sp-surface-primary" aria-labelledby="e3NoActiveHeading">
                            <div class="sp-card-head">
                                <div class="sp-card-head-title">
                                    <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
                                    <div>
                                        <div class="sp-kicker">Incubação Activa</div>
                                        <h2 id="e3NoActiveHeading" class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">
                                            Fase Actual: <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </h2>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-3" style="color: var(--sp-text-sec);">
                                <?= $totalMetasFase === 0
                                    ? 'Ainda não existem metas inicializadas para esta fase.'
                                    : 'As metas desta fase encontram-se a aguardar ativação pela administração.' ?>
                            </p>
                            <a href="<?= htmlspecialchars($hrefFichaProjeto, ENT_QUOTES, 'UTF-8') ?>" class="sp-btn-secondary">
                                <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                                <span>Abrir Minha Startup</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </section>
                    <?php endif; ?>

                    <!-- TAREFAS PENDENTES DO MENTOR (SÓ APARECE SE EXISTIREM TAREFAS PENDENTES) -->
                    <?php if (!empty($tarefasPendentes)): ?>
                        <div class="sp-section-label">
                            <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                            <span>Tarefas de Mentoria Pendentes</span>
                        </div>
                        <ul class="sp-list">
                            <?php foreach ($tarefasPendentes as $t):
                                $statusT = (string)($t['status'] ?? '');
                                $conclT  = ($statusT === 'concluida' || $statusT === 'concluid');
                                $prazoT  = !empty($t['data_limite']) ? date('d/m/Y', strtotime((string)$t['data_limite'])) : '';
                                $chipTar = $conclT ? 'sp-chip-warn' : 'sp-chip-neutral';
                                $lblTar  = $conclT ? 'Aguardando Validação' : 'Pendente';
                            ?>
                                <li class="sp-list-row">
                                    <div>
                                        <strong style="color: var(--sp-text);">
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
                    <section class="sp-surface-primary" aria-labelledby="e5Heading">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge" style="background: var(--sp-err-bg); color: var(--sp-err-text);" aria-hidden="true">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </span>
                                <div>
                                    <div class="sp-kicker">Decisão Registada</div>
                                    <h2 id="e5Heading" class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">
                                        Proposta não aprovada nesta submissão
                                    </h2>
                                </div>
                            </div>
                            <span class="sp-chip sp-chip-err">Rejeitado</span>
                        </div>
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
                            <a href="<?= htmlspecialchars($hrefFichaProjeto, ENT_QUOTES, 'UTF-8') ?>" class="sp-btn-secondary">
                                <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                                <span>Ver Dossiê Submetido</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </section>

                <?php else: ?>
                    <!-- ESTADO DE SALVAGUARDA (E_FALLBACK) -->
                    <section class="sp-surface-primary" aria-labelledby="eFallbackHeading">
                        <h2 id="eFallbackHeading" class="fw-bold mb-2" style="font-size: var(--sp-fs-lg);">
                            <?= htmlspecialchars((string)$ultimoProjeto['titulo'], ENT_QUOTES, 'UTF-8') ?>
                        </h2>
                        <p class="mb-3" style="color: var(--sp-text-sec);">
                            Estado registado no sistema: <span class="sp-code fw-bold"><?= htmlspecialchars($estadoRaw, ENT_QUOTES, 'UTF-8') ?></span>.
                        </p>
                        <a href="<?= htmlspecialchars($hrefFichaProjeto, ENT_QUOTES, 'UTF-8') ?>" class="sp-btn-secondary">
                            <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                            <span>Abrir Minha Startup</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </section>
                <?php endif; ?>

            </div>

            <!-- COLUNA LATERAL: DINÂMICA POR ESTADO (SEM BLOCOS VAZIOS FORA DE TEMPO) -->
            <div class="col-lg-4">
                <aside class="sp-surface-aside" aria-label="Resumo e acompanhamento">

                    <?php if (in_array($uiState, ['E2', 'E3', 'E4'], true)): ?>
                        <!-- MENTOR ATRIBUÍDO (apenas quando aprovado/incubado) -->
                        <div class="sp-aside-block">
                            <span class="sp-aside-icon" aria-hidden="true"><i class="fa-solid fa-user-tie"></i></span>
                            <div class="sp-aside-body">
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
                                        A aguardar designação pela coordenação.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- AGENDA DE MENTORIA (apenas quando aprovado/incubado) -->
                        <div class="sp-aside-block">
                            <span class="sp-aside-icon" aria-hidden="true"><i class="fa-solid fa-calendar-check"></i></span>
                            <div class="sp-aside-body">
                                <div class="sp-kicker">Agenda de Mentoria</div>
                                <?php if (!empty($minhasReunioes)):
                                    $proxReuniao = $minhasReunioes[0];
                                    $dataReuniao = date('d/m/Y H:i', strtotime((string)$proxReuniao['data_reuniao']));
                                ?>
                                    <div class="fw-semibold" style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                                        <?= htmlspecialchars((string)$proxReuniao['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="sp-num" style="font-size: var(--sp-fs-xs); color: var(--sp-accent);">
                                        <?= htmlspecialchars($dataReuniao, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php
                                    $localOuLink = trim((string)($proxReuniao['local'] ?? ($proxReuniao['link_reuniao'] ?? '')));
                                    if ($localOuLink !== ''):
                                    ?>
                                        <div style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec);">
                                            <?= htmlspecialchars($localOuLink, ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($ultimaSessao):
                                    $dataUltSessao = date('d/m/Y', strtotime((string)$ultimaSessao['data_sessao']));
                                ?>
                                    <div class="sp-num" style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec);">
                                        Última sessão: <?= htmlspecialchars($dataUltSessao, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: var(--sp-fs-sm); color: var(--sp-text-muted);">
                                        Sem reuniões agendadas.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- TERMO DE INCUBAÇÃO (apenas quando emitido) -->
                        <?php if ($termoProjeto):
                            $estTermo = (string)($termoProjeto['estado'] ?? '');
                        ?>
                            <div class="sp-aside-block">
                                <span class="sp-aside-icon" aria-hidden="true"><i class="fa-solid fa-file-contract"></i></span>
                                <div class="sp-aside-body">
                                    <div class="sp-kicker">Termo de Incubação</div>
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-1">
                                        <span class="sp-code fw-bold"><?= htmlspecialchars((string)$termoProjeto['codigo_termo'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="sp-chip <?= $estTermo === 'assinado' ? 'sp-chip-ok' : 'sp-chip-warn' ?>">
                                            <?= htmlspecialchars($estTermo === 'assinado' ? 'Assinado' : ($estTermo === 'pendente_assinatura' ? 'Pendente' : ucfirst($estTermo)), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- RESUMO DO DOSSIÊ DA STARTUP (sempre relevante e com contagens reais) -->
                    <div class="sp-aside-block">
                        <span class="sp-aside-icon" aria-hidden="true"><i class="fa-solid fa-folder-tree"></i></span>
                        <div class="sp-aside-body">
                            <div class="sp-kicker">Dossiê da Startup</div>
                            <div class="sp-num fw-semibold mb-1" style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                                <?= (int)$numMembros ?> membro<?= $numMembros !== 1 ? 's' : '' ?> · <?= (int)$numDocumentos ?> documento<?= $numDocumentos !== 1 ? 's' : '' ?>
                            </div>
                            <?php if ($numComentarios > 0): ?>
                                <div class="sp-num mb-1" style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec);">
                                    <?= (int)$numComentarios ?> comentário<?= $numComentarios !== 1 ? 's' : '' ?> registado<?= $numComentarios !== 1 ? 's' : '' ?>
                                </div>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($hrefFichaProjeto, ENT_QUOTES, 'UTF-8') ?>" class="sp-link">
                                <span>Gerir em Minha Startup</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>

                    <!-- RESERVAS DE ESPAÇO -->
                    <div class="sp-aside-block">
                        <span class="sp-aside-icon" aria-hidden="true"><i class="fa-solid fa-building"></i></span>
                        <div class="sp-aside-body">
                            <div class="sp-kicker">Reservas de Espaço</div>
                            <div class="sp-num mb-1 fw-semibold" style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                                <?= $totalReservasConfirmadas > 0
                                    ? $totalReservasConfirmadas . ' reserva' . ($totalReservasConfirmadas > 1 ? 's' : '') . ' ativa' . ($totalReservasConfirmadas > 1 ? 's' : '')
                                    : 'Sem reservas ativas' ?>
                            </div>
                            <a href="<?= htmlspecialchars($hrefReservas, ENT_QUOTES, 'UTF-8') ?>" class="sp-link">
                                <span>Gerir reservas</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
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
