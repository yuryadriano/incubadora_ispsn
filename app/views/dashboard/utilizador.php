<?php
// app/views/dashboard/utilizador.php
// Painel Geral do Empreendedor — Orientado ao Fluxo Real da Incubadora ISPSN

require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$realPerfil     = $_SESSION['usuario_perfil'] ?? 'utilizador';
$isAdminOrSuper = in_array($realPerfil, ['admin', 'superadmin'], true);

if ($isAdminOrSuper && !empty($_GET['id_usuario'])) {
    $idUsuario = (int)$_GET['id_usuario'];
} elseif (!$isAdminOrSuper) {
    $idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
} else {
    $resFirst = $mysqli->query("
        SELECT u.id
        FROM usuarios u
        LEFT JOIN projetos p ON p.criado_por = u.id
        WHERE u.perfil = 'utilizador' AND u.activo = 1
        GROUP BY u.id
        ORDER BY COUNT(p.id) DESC, u.id ASC
        LIMIT 1
    ");
    $rowFirst  = $resFirst ? $resFirst->fetch_assoc() : null;
    $idUsuario = $rowFirst ? (int)$rowFirst['id'] : (int)($_SESSION['usuario_id'] ?? 0);
}

$stmtUser = $mysqli->prepare("SELECT id, nome, email, tipo_utilizador FROM usuarios WHERE id = ? LIMIT 1");
$stmtUser->bind_param('i', $idUsuario);
$stmtUser->execute();
$userInfo = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$nomeEstudante = $userInfo['nome'] ?? ($_SESSION['usuario_nome'] ?? 'Empreendedor');
if ($isAdminOrSuper) {
    $nomeUsuario = $nomeEstudante;
    $perfil      = 'utilizador';
}

$tituloPagina = 'Painel Geral';
$paginaActiva = 'meu-painel';

$userParam = $isAdminOrSuper ? ('?id_usuario=' . (int)$idUsuario) : '';
$userAmp   = $isAdminOrSuper ? ('&id_usuario=' . (int)$idUsuario) : '';

// ── 1. Projetos do estudante (criador ou membro) ─────────────────────────
$stmtLista = $mysqli->prepare("
    SELECT DISTINCT p.id, p.titulo, p.estado, p.fase, p.pontos, p.criado_em
    FROM projetos p
    LEFT JOIN membros_projeto mp ON mp.id_projeto = p.id
    WHERE p.criado_por = ? OR mp.id_usuario = ?
    ORDER BY p.criado_em DESC
");
$stmtLista->bind_param('ii', $idUsuario, $idUsuario);
$stmtLista->execute();
$listaProjetos = $stmtLista->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLista->close();

$idProjSel = isset($_GET['id_projeto']) ? (int)$_GET['id_projeto'] : 0;

// ── 2. Projeto selecionado ───────────────────────────────────────────────
$meuProjeto = null;
if ($idProjSel > 0) {
    $stmtP = $mysqli->prepare("
        SELECT p.*
        FROM projetos p
        LEFT JOIN membros_projeto mp ON mp.id_projeto = p.id
        WHERE p.id = ? AND (p.criado_por = ? OR mp.id_usuario = ?)
        LIMIT 1
    ");
    $stmtP->bind_param('iii', $idProjSel, $idUsuario, $idUsuario);
} else {
    $stmtP = $mysqli->prepare("
        SELECT p.*
        FROM projetos p
        LEFT JOIN membros_projeto mp ON mp.id_projeto = p.id
        WHERE p.criado_por = ? OR mp.id_usuario = ?
        ORDER BY p.criado_em DESC
        LIMIT 1
    ");
    $stmtP->bind_param('ii', $idUsuario, $idUsuario);
}
$stmtP->execute();
$meuProjeto = $stmtP->get_result()->fetch_assoc();
$stmtP->close();

// ── 3. Dados específicos do estado atual do projeto ──────────────────────
$mentorAtivo     = null;
$termoAtivo      = null;
$numAvalAtrib    = 0;
$numAvalConcl    = 0;
$metasFase       = [];
$tarefasAbertas  = [];
$proximaReuniao  = null;
$ultimosFeedback = [];
$numMembros      = 0;
$numDocs         = 0;

if ($meuProjeto) {
    $idP       = (int)$meuProjeto['id'];
    $faseAtual = (string)($meuProjeto['fase'] ?? 'ideacao');

    // Mentor ativo (apenas se existir mentoria ativa)
    $stmtMent = $mysqli->prepare("
        SELECT u.nome AS mentor_nome, u.email AS mentor_email, mt.especialidade
        FROM mentorias m
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u ON u.id = mt.id_usuario
        WHERE m.id_projeto = ? AND m.estado = 'activa'
        ORDER BY m.id DESC
        LIMIT 1
    ");
    if ($stmtMent) {
        $stmtMent->bind_param('i', $idP);
        $stmtMent->execute();
        $mentorAtivo = $stmtMent->get_result()->fetch_assoc();
        $stmtMent->close();
    }

    // Termo de incubação
    $stmtTermo = $mysqli->prepare("
        SELECT id, codigo_termo, estado, criado_em, assinado_em
        FROM termos_incubacao
        WHERE id_projeto = ? AND estado IN ('gerado', 'pendente_assinatura', 'assinado')
        ORDER BY criado_em DESC
        LIMIT 1
    ");
    if ($stmtTermo) {
        $stmtTermo->bind_param('i', $idP);
        $stmtTermo->execute();
        $termoAtivo = $stmtTermo->get_result()->fetch_assoc();
        $stmtTermo->close();
    }

    // Progresso da Comissão de Avaliação (3 avaliadores)
    $stmtAv = $mysqli->prepare("
        SELECT
            COUNT(*) AS total_atribuidos,
            COALESCE(SUM(CASE WHEN estado = 'concluido' THEN 1 ELSE 0 END), 0) AS total_concluidos
        FROM avaliacoes_atribuicao
        WHERE projeto_id = ?
    ");
    if ($stmtAv) {
        $stmtAv->bind_param('i', $idP);
        $stmtAv->execute();
        $avRow        = $stmtAv->get_result()->fetch_assoc();
        $numAvalAtrib = (int)($avRow['total_atribuidos'] ?? 0);
        $numAvalConcl = (int)($avRow['total_concluidos'] ?? 0);
        $stmtAv->close();
    }

    // Metas da fase atual (usadas quando o projeto está incubado)
    $stmtM = $mysqli->prepare("
        SELECT mp.*, mpd.numero, mpd.titulo AS meta_titulo, mpd.descricao AS meta_descricao,
               mpd.evidencia_tipo, mpd.evidencia_desc, mpd.peso_percentual
        FROM metas_projeto mp
        JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
        WHERE mp.id_projeto = ? AND mpd.fase = ?
        ORDER BY mpd.numero ASC
    ");
    if ($stmtM) {
        $stmtM->bind_param('is', $idP, $faseAtual);
        $stmtM->execute();
        $metasFase = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtM->close();
    }

    // Tarefas do mentor pendentes / em curso
    $stmtT = $mysqli->prepare("
        SELECT *
        FROM tarefas
        WHERE id_projeto = ? AND COALESCE(validada_mentor, 0) = 0
        ORDER BY data_limite ASC
        LIMIT 5
    ");
    if ($stmtT) {
        $stmtT->bind_param('i', $idP);
        $stmtT->execute();
        $tarefasAbertas = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtT->close();
    }

    // Comentários / Pareceres recentes da Comissão ou Mentor
    $stmtCom = $mysqli->prepare("
        SELECT c.comentario, c.criado_em, u.nome, u.perfil
        FROM comentarios_projetos c
        JOIN usuarios u ON u.id = c.id_usuario
        WHERE c.id_projeto = ?
        ORDER BY c.criado_em DESC
        LIMIT 3
    ");
    if ($stmtCom) {
        $stmtCom->bind_param('i', $idP);
        $stmtCom->execute();
        $ultimosFeedback = $stmtCom->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtCom->close();
    }

    // Contagem rápida de equipa e documentos para o dossiê
    $stmtCntEq = $mysqli->prepare("SELECT COUNT(*) AS n FROM membros_projeto WHERE id_projeto = ?");
    if ($stmtCntEq) {
        $stmtCntEq->bind_param('i', $idP);
        $stmtCntEq->execute();
        $numMembros = (int)($stmtCntEq->get_result()->fetch_assoc()['n'] ?? 0);
        $stmtCntEq->close();
    }

    $stmtCntDoc = $mysqli->prepare("SELECT COUNT(*) AS n FROM ficheiros_projeto WHERE id_projeto = ?");
    if ($stmtCntDoc) {
        $stmtCntDoc->bind_param('i', $idP);
        $stmtCntDoc->execute();
        $numDocs = (int)($stmtCntDoc->get_result()->fetch_assoc()['n'] ?? 0);
        $stmtCntDoc->close();
    }
}

// Reservas ativas do estudante
$numReservas = 0;
$stmtRes = $mysqli->prepare("
    SELECT COUNT(*) AS n
    FROM reservas_espaco
    WHERE id_usuario = ? AND status IN ('pendente', 'confirmada') AND data_reserva >= CURDATE()
");
if ($stmtRes) {
    $stmtRes->bind_param('i', $idUsuario);
    $stmtRes->execute();
    $numReservas = (int)($stmtRes->get_result()->fetch_assoc()['n'] ?? 0);
    $stmtRes->close();
}

$labelsEstado = [
    'submetido'          => ['txt' => 'Submetido (Em Triagem)',    'chip' => 'sp-chip-warn'],
    'em_avaliacao'       => ['txt' => 'Em Avaliação Técnica',      'chip' => 'sp-chip-warn'],
    'em_revisao'         => ['txt' => 'Revisão Solicitada',        'chip' => 'sp-chip-err'],
    'aprovado'           => ['txt' => 'Aprovado para Incubação',   'chip' => 'sp-chip-ok'],
    'rejeitado'          => ['txt' => 'Não Selecionado',           'chip' => 'sp-chip-err'],
    'incubado'           => ['txt' => 'Em Incubação Ativa',        'chip' => 'sp-chip-ok'],
    'fundo_investimento' => ['txt' => 'Pronto p/ Investimento',    'chip' => 'sp-chip-ok'],
    'concluido'          => ['txt' => 'Startup Graduada',          'chip' => 'sp-chip-ok'],
];

$labelsFase = [
    'ideacao'   => 'Fase 1 · Ideação',
    'validacao' => 'Fase 2 · Validação',
    'mvp'       => 'Fase 3 · MVP',
    'tracao'    => 'Fase 4 · Tração',
    'mercado'   => 'Fase 5 · Mercado',
];

$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<div class="std-painel">

    <?php if ($flashOk): ?>
        <div class="alert-custom alert-success mb-3" role="status">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span><?= htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>
    <?php if ($flashErro): ?>
        <div class="alert-custom alert-danger mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span><?= htmlspecialchars($flashErro, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$meuProjeto): ?>
        <!-- ═══════════════════════════════════════════════════
             ETAPA 0: SEM PROJETO SUBMETIDO (FOCO ÚNICO: SUBMETER)
        ═══════════════════════════════════════════════════ -->
        <section class="std-command-hero">
            <div class="row g-4 align-items-center position-relative" style="z-index: 2;">
                <div class="col-lg-8">
                    <span class="std-live-pill mb-3" style="background: rgba(245,158,11,0.2); color: #FBBF24; border: 1px solid rgba(245,158,11,0.4);">
                        <span class="std-live-dot" style="background: #F59E0B;"></span>
                        <span>Passo 1 do Fluxo · Submissão de Ideia</span>
                    </span>
                    <h1 style="font-size: 1.7rem; font-weight: 800; color: #FFFFFF; margin-bottom: 10px;">
                        Bem-vindo à Incubadora do ISPSN, <?= htmlspecialchars(explode(' ', $nomeEstudante)[0], ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <p style="color: #CBD5E1; max-width: 60ch; margin-bottom: 20px; font-size: 0.94rem;">
                        Para iniciar o seu percurso, submeta a proposta da sua startup. A Comissão Técnica (3 avaliadores) analisará o projeto para aprovação e atribuição de mentor.
                    </p>
                    <button type="button" class="std-hero-btn std-hero-btn-amber" data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
                        <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                        <span>Submeter Proposta de Startup</span>
                    </button>
                </div>
                <div class="col-lg-4">
                    <div class="std-hero-glass" style="font-size: 0.82rem; color: #E2E8F0;">
                        <div class="fw-bold mb-2" style="color: #FBBF24; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em;">
                            Etapas do Ciclo ISPSN
                        </div>
                        <div class="mb-1"><strong>1. Submissão:</strong> Problema, solução e Pitch Deck.</div>
                        <div class="mb-1"><strong>2. Avaliação Técnica:</strong> 3 pareceres da comissão.</div>
                        <div class="mb-1"><strong>3. Termo &amp; Mentoria:</strong> Assinatura e designação de mentor.</div>
                        <div><strong>4. Execução por Fases:</strong> Metas validadas e Startup Points.</div>
                    </div>
                </div>
            </div>
        </section>

    <?php else:
        $idP       = (int)$meuProjeto['id'];
        $estKey    = (string)($meuProjeto['estado'] ?? 'submetido');
        $faseKey   = (string)($meuProjeto['fase'] ?? 'ideacao');
        $estInfo   = $labelsEstado[$estKey] ?? ['txt' => ucfirst($estKey), 'chip' => 'sp-chip-neutral'];
        $faseTexto = $labelsFase[$faseKey] ?? ucfirst($faseKey);
        $pontosSP  = (int)($meuProjeto['pontos'] ?? 0);

        // Classificar em qual macro-etapa do fluxo o projeto está
        $isEmTriagem  = in_array($estKey, ['submetido', 'em_avaliacao', 'em_revisao', 'rejeitado'], true);
        $isAprovado   = ($estKey === 'aprovado');
        $isIncubado   = in_array($estKey, ['incubado', 'fundo_investimento', 'concluido'], true);

        // Contagem de metas da fase atual (só relevante quando incubado)
        $metasConcluidas = count(array_filter($metasFase, fn($m) => ($m['estado'] ?? '') === 'concluida'));
        $metasAtivas     = array_values(array_filter($metasFase, fn($m) => in_array($m['estado'] ?? '', ['activa', 'reprovada', 'em_avaliacao'], true)));
        $totalMetasFase  = count($metasFase);

        // Verificação simples do dossiê de candidatura (relevante na triagem)
        $temProblemaSol = (strlen(trim((string)($meuProjeto['problema'] ?? ''))) >= 15 && strlen(trim((string)($meuProjeto['solucao'] ?? ''))) >= 15);
        $temPitch       = (!empty($meuProjeto['pitch_path']) || $numDocs > 0);
        $linkDossie     = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=' . $idP . $userAmp;
    ?>

        <!-- ═══════════════════════════════════════════════════
             1. CABEÇALHO LIMPO COM LINHA DO FLUXO REAL (4 MARCOS)
        ═══════════════════════════════════════════════════ -->
        <section class="std-command-hero">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 position-relative" style="z-index: 2;">
                <div>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                        <span class="std-live-pill" style="background: rgba(245,158,11,0.2); color: #FCD34D; border: 1px solid rgba(245,158,11,0.45);">
                            <span class="std-live-dot" style="background: #FBBF24;"></span>
                            <span><?= htmlspecialchars($estInfo['txt'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <?php if ($isIncubado): ?>
                            <span class="std-live-pill" style="background: rgba(16,185,129,0.2); color: #6EE7B7; border: 1px solid rgba(16,185,129,0.4);">
                                <i class="fa-solid fa-layer-group"></i>
                                <span><?= htmlspecialchars($faseTexto, ENT_QUOTES, 'UTF-8') ?> · <?= $pontosSP ?> SP</span>
                            </span>
                        <?php endif; ?>
                        <?php if (count($listaProjetos) > 1): ?>
                            <form method="GET" class="m-0 d-inline-block">
                                <?php if ($isAdminOrSuper): ?>
                                    <input type="hidden" name="id_usuario" value="<?= (int)$idUsuario ?>">
                                <?php endif; ?>
                                <select name="id_projeto" class="form-select form-select-sm" style="background:#1E293B; color:#F8FAFC; border-color:rgba(255,255,255,0.2); font-size:0.75rem; border-radius:999px; padding:2px 26px 2px 10px;" onchange="this.form.submit()">
                                    <?php foreach ($listaProjetos as $lp): ?>
                                        <option value="<?= (int)$lp['id'] ?>" <?= ((int)$lp['id'] === $idP) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string)$lp['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </div>

                    <h1 style="font-size: 1.65rem; font-weight: 800; color: #FFFFFF; margin: 0 0 6px;">
                        <?= htmlspecialchars((string)$meuProjeto['titulo'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <div style="font-size: 0.82rem; color: #CBD5E1;">
                        Área: <strong><?= htmlspecialchars(ucfirst((string)($meuProjeto['area_tematica'] ?? 'Tecnologia')), ENT_QUOTES, 'UTF-8') ?></strong>
                        · Submetido em <?= date('d/m/Y', strtotime((string)$meuProjeto['criado_em'])) ?>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= htmlspecialchars($linkDossie, ENT_QUOTES, 'UTF-8') ?>" class="std-hero-btn std-hero-btn-amber">
                        <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                        <span>Abrir Dossiê da Startup</span>
                    </a>
                    <button type="button" class="std-hero-btn" data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>Nova Proposta</span>
                    </button>
                </div>
            </div>

            <!-- Barra linear simples dos 4 Marcos do Ciclo de Vida -->
            <?php
            $marcosFluxo = [
                ['lbl' => '1. Submissão da Proposta',   'done' => true,                                  'active' => ($estKey === 'submetido')],
                ['lbl' => '2. Avaliação (3 Avaliadores)', 'done' => ($isAprovado || $isIncubado),          'active' => in_array($estKey, ['em_avaliacao', 'em_revisao'], true)],
                ['lbl' => '3. Termo de Incubação',      'done' => $isIncubado,                           'active' => $isAprovado],
                ['lbl' => '4. Incubação & Metas',       'done' => ($estKey === 'concluido'),             'active' => in_array($estKey, ['incubado', 'fundo_investimento'], true)],
            ];
            ?>
            <div class="std-pipeline" style="border-top-color: rgba(255,255,255,0.12); margin-top: 18px; padding-top: 16px;">
                <?php foreach ($marcosFluxo as $mf):
                    $bgM = $mf['done'] ? 'rgba(16,185,129,0.16)' : ($mf['active'] ? 'rgba(245,158,11,0.22)' : 'rgba(255,255,255,0.05)');
                    $bdM = $mf['done'] ? 'rgba(16,185,129,0.45)' : ($mf['active'] ? '#F59E0B' : 'rgba(255,255,255,0.1)');
                    $icM = $mf['done'] ? '#10B981' : ($mf['active'] ? '#F59E0B' : 'rgba(255,255,255,0.15)');
                ?>
                    <div style="display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:10px; background:<?= $bgM ?>; border:1px solid <?= $bdM ?>;">
                        <span style="width:24px; height:24px; border-radius:50%; background:<?= $icM ?>; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:800; flex-shrink:0;">
                            <i class="fa-solid <?= $mf['done'] ? 'fa-check' : ($mf['active'] ? 'fa-bolt' : 'fa-circle') ?>"></i>
                        </span>
                        <span style="font-size:0.78rem; font-weight:700; color:#F8FAFC;"><?= htmlspecialchars($mf['lbl'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════════════
             2. CONTEÚDO PRINCIPAL (ADAPTADO À ETAPA ATUAL DO FLUXO)
        ═══════════════════════════════════════════════════ -->
        <div class="row g-4 align-items-start">

            <!-- COLUNA CENTRAL (8 COLUNAS): FOCO NA ETAPA ATUAL -->
            <div class="col-lg-8">

                <?php if ($isEmTriagem): ?>
                    <!-- ── MODO A: PROJETO EM TRIAGEM / AVALIAÇÃO TÉCNICA ── -->
                    <section class="sp-surface-primary">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge"><i class="fa-solid fa-magnifying-glass-chart"></i></span>
                                <div>
                                    <div class="sp-kicker">Etapa Atual · Triagem &amp; Comissão Técnica</div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Acompanhamento da Avaliação</h2>
                                </div>
                            </div>
                            <span class="sp-chip sp-chip-warn sp-num">
                                <?= $numAvalConcl ?> de 3 pareceres concluídos
                            </span>
                        </div>

                        <?php if ($estKey === 'rejeitado' && !empty($meuProjeto['motivo_rejeicao'])): ?>
                            <div class="p-3 rounded-3 mb-3" style="background: var(--sp-err-bg); border: 1px solid var(--sp-err-border); color: var(--sp-err-text);">
                                <div class="fw-bold mb-1"><i class="fa-solid fa-circle-xmark me-1"></i> Motivo registado pela Comissão:</div>
                                <div style="font-size: 0.86rem;"><?= nl2br(htmlspecialchars((string)$meuProjeto['motivo_rejeicao'], ENT_QUOTES, 'UTF-8')) ?></div>
                            </div>
                        <?php endif; ?>

                        <p style="font-size: 0.88rem; color: var(--sp-text-sec); margin-bottom: 16px;">
                            A sua proposta encontra-se na fase de apreciação pela Comissão Técnica (composta por <strong>3 avaliadores</strong>). Assim que os 3 pareceres forem submetidos, o sistema calcula automaticamente a média final (aprovação com nota ≥ 7.0/10).
                        </p>

                        <!-- Barra visual simples dos 3 Avaliadores -->
                        <div class="p-3 rounded-3 mb-4" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border);">
                            <div class="d-flex justify-content-between align-items-center mb-2" style="font-size: 0.82rem;">
                                <span class="fw-bold">Progresso da Comissão Avaliadora</span>
                                <span class="sp-num fw-bold" style="color: var(--sp-accent);"><?= $numAvalAtrib ?>/3 atribuídos · <?= $numAvalConcl ?>/3 avaliados</span>
                            </div>
                            <div style="height: 10px; background: var(--sp-border); border-radius: 999px; overflow: hidden;">
                                <div style="width: <?= max(5, (int)round(($numAvalConcl / 3) * 100)) ?>%; height: 100%; background: linear-gradient(90deg, #F59E0B, #10B981); border-radius: 999px;"></div>
                            </div>
                        </div>

                        <!-- O que o estudante pode fazer enquanto aguarda -->
                        <div class="sp-kicker mb-2">Verificação do Dossiê Submetido</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-3 rounded-3 h-100" style="background: <?= $temProblemaSol ? 'var(--sp-ok-bg)' : 'var(--sp-warn-bg)' ?>; border: 1px solid <?= $temProblemaSol ? 'var(--sp-ok-border)' : 'var(--sp-warn-border)' ?>;">
                                    <div class="fw-bold" style="font-size: 0.84rem;">
                                        <i class="fa-solid <?= $temProblemaSol ? 'fa-check-circle text-success' : 'fa-circle-exclamation text-warning' ?> me-1"></i>
                                        Problema &amp; Solução
                                    </div>
                                    <div style="font-size: 0.76rem; color: var(--sp-text-sec); margin-top: 4px;">
                                        <?= $temProblemaSol ? 'Preenchidos no dossiê.' : 'Recomendado detalhar melhor.' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded-3 h-100" style="background: <?= $temPitch ? 'var(--sp-ok-bg)' : 'var(--sp-warn-bg)' ?>; border: 1px solid <?= $temPitch ? 'var(--sp-ok-border)' : 'var(--sp-warn-border)' ?>;">
                                    <div class="fw-bold" style="font-size: 0.84rem;">
                                        <i class="fa-solid <?= $temPitch ? 'fa-check-circle text-success' : 'fa-circle-exclamation text-warning' ?> me-1"></i>
                                        Pitch Deck / Anexos
                                    </div>
                                    <div style="font-size: 0.76rem; color: var(--sp-text-sec); margin-top: 4px;">
                                        <?= $temPitch ? 'Documento anexado.' : 'Anexe o seu Pitch Deck.' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded-3 h-100" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border);">
                                    <div class="fw-bold" style="font-size: 0.84rem;">
                                        <i class="fa-solid fa-users me-1" style="color: var(--sp-accent);"></i>
                                        Equipa (<?= $numMembros ?>)
                                    </div>
                                    <div style="font-size: 0.76rem; color: var(--sp-text-sec); margin-top: 4px;">
                                        Gerir membros no dossiê.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-color: var(--sp-border) !important;">
                            <span style="font-size: 0.8rem; color: var(--sp-text-muted);">
                                Precisa de atualizar a descrição, o Pitch Deck ou a equipa?
                            </span>
                            <a href="<?= htmlspecialchars($linkDossie, ENT_QUOTES, 'UTF-8') ?>" class="sp-btn-primary">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span>Gerir Proposta em Minha Startup</span>
                            </a>
                        </div>
                    </section>

                <?php elseif ($isAprovado): ?>
                    <!-- ── MODO B: PROJETO APROVADO (AGUARDA ASSINATURA DO TERMO DE INCUBAÇÃO) ── -->
                    <section class="sp-surface-primary">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge" style="background: var(--sp-ok-bg); color: var(--sp-ok-text);"><i class="fa-solid fa-award"></i></span>
                                <div>
                                    <div class="sp-kicker">Proposta Aprovada pela Comissão Técnica</div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Formalização do Termo de Incubação</h2>
                                </div>
                            </div>
                            <?php if (!empty($meuProjeto['media_final'])): ?>
                                <span class="sp-chip sp-chip-ok sp-num">Média Final: <?= number_format((float)$meuProjeto['media_final'], 1) ?>/10</span>
                            <?php endif; ?>
                        </div>

                        <p style="font-size: 0.9rem; color: var(--sp-text-sec);">
                            Parabéns! O seu projeto foi aprovado pela Comissão Técnica. O próximo passo oficial do fluxo é a emissão e assinatura digital do <strong>Termo de Incubação</strong> pela Direção para desbloquear a <strong>Fase 1 (Ideação)</strong> e as respetivas metas.
                        </p>

                        <?php if ($termoAtivo): ?>
                            <div class="p-3 rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: var(--sp-warn-bg); border: 1px solid var(--sp-warn-border);">
                                <div>
                                    <div class="fw-bold" style="font-size: 0.9rem;">Termo Ref. <?= htmlspecialchars((string)$termoAtivo['codigo_termo'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div style="font-size: 0.78rem; color: var(--sp-text-sec);">Estado: <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$termoAtivo['estado'])), ENT_QUOTES, 'UTF-8') ?></strong></div>
                                </div>
                                <a href="/incubadora_ispsn/app/views/admin/termo_incubacao.php?id=<?= (int)$termoAtivo['id'] ?>" class="sp-btn-primary">
                                    <i class="fa-solid fa-file-contract"></i>
                                    <span>Consultar Termo</span>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="sp-empty-line">
                                <i class="fa-solid fa-hourglass-half"></i>
                                <span>A Coordenação está a preparar a emissão do Termo de Incubação para este projeto.</span>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php else: ?>
                    <!-- ── MODO C: STARTUP EM INCUBAÇÃO ATIVA (FOCO NAS METAS DA FASE & TAREFAS DO MENTOR) ── -->
                    <section class="sp-surface-primary">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge"><i class="fa-solid fa-bullseye"></i></span>
                                <div>
                                    <div class="sp-kicker"><?= htmlspecialchars($faseTexto, ENT_QUOTES, 'UTF-8') ?></div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Metas Ativas &amp; Entregas da Fase</h2>
                                </div>
                            </div>
                            <span class="sp-chip sp-chip-ok sp-num">
                                <?= $metasConcluidas ?>/<?= $totalMetasFase ?> metas validadas
                            </span>
                        </div>

                        <?php if (empty($metasFase)): ?>
                            <div class="sp-empty-line mb-3">
                                <i class="fa-regular fa-clock"></i>
                                <span>A aguardar ativação das metas desta fase pela Coordenação / Mentor.</span>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2 mb-3">
                                <?php foreach ($metasFase as $mf):
                                    $estM = (string)($mf['estado'] ?? 'inactiva');
                                    $chipM = $estM === 'concluida' ? 'sp-chip-ok' : ($estM === 'reprovada' ? 'sp-chip-err' : ($estM === 'activa' || $estM === 'em_avaliacao' ? 'sp-chip-warn' : 'sp-chip-neutral'));
                                    $lblM  = $estM === 'concluida' ? 'Validada ✓' : ($estM === 'reprovada' ? 'Devolvida pelo Mentor' : ($estM === 'em_avaliacao' ? 'Em Revisão pelo Mentor' : ($estM === 'activa' ? 'Ativa p/ Entrega' : 'Aguarda Ativação')));
                                ?>
                                    <div class="p-3 rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border);">
                                        <div style="max-width: 72%;">
                                            <div class="fw-bold" style="font-size: 0.88rem;">
                                                <span class="sp-code me-1" style="color:var(--sp-accent);">#<?= (int)$mf['numero'] ?></span>
                                                <?= htmlspecialchars((string)$mf['meta_titulo'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div style="font-size: 0.78rem; color: var(--sp-text-sec);">
                                                <?= htmlspecialchars((string)$mf['meta_descricao'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <?php if (!empty($mf['feedback_mentor'])): ?>
                                                <div class="mt-1" style="font-size: 0.76rem; color: var(--sp-err-text);">
                                                    <strong>Nota do Mentor:</strong> <?= htmlspecialchars((string)$mf['feedback_mentor'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="sp-chip <?= $chipM ?>"><?= $lblM ?></span>
                                            <?php if ($estM === 'activa' || $estM === 'reprovada'): ?>
                                                <a href="<?= htmlspecialchars($linkDossie . '&tab=metas', ENT_QUOTES, 'UTF-8') ?>" class="sp-btn-primary py-1 px-3" style="min-height: 34px; font-size: 0.78rem;">
                                                    Submeter Evidência →
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($tarefasAbertas)): ?>
                            <div class="pt-3 border-top" style="border-color: var(--sp-border) !important;">
                                <div class="sp-kicker mb-2">Tarefas Atribuídas pelo Mentor (<?= count($tarefasAbertas) ?>)</div>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($tarefasAbertas as $t): ?>
                                        <div class="p-2 px-3 rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); font-size: 0.84rem;">
                                            <div>
                                                <strong><?= htmlspecialchars((string)$t['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?php if (!empty($t['data_limite'])): ?>
                                                    <span class="ms-2" style="font-size: 0.75rem; color: var(--sp-text-muted);">Prazo: <?= date('d/m/Y', strtotime((string)$t['data_limite'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="<?= htmlspecialchars($linkDossie . '&tab=metas', ENT_QUOTES, 'UTF-8') ?>" class="sp-link" style="font-size: 0.78rem;">Ver tarefa →</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <!-- PARECERES OFICIAIS RECENTES (SÓ APARECE SE EXISTIREM COMENTÁRIOS) -->
                <?php if (!empty($ultimosFeedback)): ?>
                    <section class="sp-surface-primary mb-0">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge"><i class="fa-solid fa-comments"></i></span>
                                <div>
                                    <div class="sp-kicker">Comunicação Oficial</div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-md);">Últimos Pareceres da Comissão / Mentor</h2>
                                </div>
                            </div>
                            <a href="<?= htmlspecialchars($linkDossie . '&tab=comunicacao', ENT_QUOTES, 'UTF-8') ?>" class="sp-link">Ver todos →</a>
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($ultimosFeedback as $fb): ?>
                                <div class="p-3 rounded-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border);">
                                    <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.78rem;">
                                        <strong><?= htmlspecialchars((string)$fb['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(ucfirst((string)$fb['perfil']), ENT_QUOTES, 'UTF-8') ?>)</strong>
                                        <span style="color: var(--sp-text-muted);"><?= date('d/m/Y H:i', strtotime((string)$fb['criado_em'])) ?></span>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--sp-text-sec);"><?= nl2br(htmlspecialchars((string)$fb['comentario'], ENT_QUOTES, 'UTF-8')) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

            </div>

            <!-- COLUNA LATERAL LIMPA (4 COLUNAS): APENAS RESUMO DE ORIENTAÇÃO -->
            <div class="col-lg-4">
                <aside class="sp-surface-aside">

                    <!-- Mentor -->
                    <div class="sp-aside-block">
                        <span class="sp-aside-icon"><i class="fa-solid fa-user-tie"></i></span>
                        <div class="sp-aside-body">
                            <div class="sp-kicker mb-1">Mentor da Startup</div>
                            <?php if ($mentorAtivo): ?>
                                <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars((string)$mentorAtivo['mentor_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="font-size: 0.76rem; color: var(--sp-text-sec);"><?= htmlspecialchars((string)($mentorAtivo['especialidade'] ?? $mentorAtivo['mentor_email']), ENT_QUOTES, 'UTF-8') ?></div>
                                <a href="<?= htmlspecialchars($linkDossie . '&tab=comunicacao', ENT_QUOTES, 'UTF-8') ?>" class="sp-link mt-1" style="font-size: 0.78rem;">Enviar mensagem ao mentor →</a>
                            <?php else: ?>
                                <div style="font-size: 0.8rem; color: var(--sp-text-muted);">
                                    <?= $isEmTriagem ? 'Atribuído após aprovação da proposta.' : 'A aguardar designação pela coordenação.' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Termo de Incubação -->
                    <div class="sp-aside-block">
                        <span class="sp-aside-icon"><i class="fa-solid fa-file-contract"></i></span>
                        <div class="sp-aside-body">
                            <div class="sp-kicker mb-1">Termo de Incubação</div>
                            <?php if ($termoAtivo): ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="sp-chip <?= ($termoAtivo['estado'] ?? '') === 'assinado' ? 'sp-chip-ok' : 'sp-chip-warn' ?>">
                                        <?= ($termoAtivo['estado'] ?? '') === 'assinado' ? 'Assinado' : 'Em assinatura' ?>
                                    </span>
                                    <a href="/incubadora_ispsn/app/views/admin/termo_incubacao.php?id=<?= (int)$termoAtivo['id'] ?>" class="sp-link" style="font-size: 0.78rem;">Ver PDF →</a>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.8rem; color: var(--sp-text-muted);">
                                    Disponível após aprovação técnica.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Reservas de Espaço -->
                    <div class="sp-aside-block">
                        <span class="sp-aside-icon"><i class="fa-solid fa-building-user"></i></span>
                        <div class="sp-aside-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="sp-kicker mb-1">Salas &amp; Coworking</div>
                                    <div class="fw-bold" style="font-size: 0.85rem;"><?= $numReservas ?> reserva(s) ativa(s)</div>
                                </div>
                                <a href="/incubadora_ispsn/app/views/utilizador/reservas.php<?= $userParam ?>" class="sp-link" style="font-size: 0.78rem;">Gerir →</a>
                            </div>
                        </div>
                    </div>

                </aside>
            </div>

        </div>
    <?php endif; ?>

    <!-- MODAL SUBMETER NOVA PROPOSTA -->
    <div class="modal fade" id="modalNovoProjeto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content modal-content-custom">
                <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="criar_projeto">
                    <input type="hidden" name="id_usuario_alvo" value="<?= (int)$idUsuario ?>">
                    <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php<?= $userParam ?>">

                    <div class="modal-header-custom">
                        <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-rocket me-2" style="color:var(--primary);"></i>Submeter Nova Proposta de Startup</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body-custom">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label-custom">Nome da Startup / Projeto *</label>
                                <input type="text" name="titulo" class="form-control-custom" placeholder="Ex: AgroSmart Huambo" required minlength="5">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Tipologia *</label>
                                <select name="tipo" class="form-select form-control-custom" required>
                                    <option value="startup_tecnologica">Startup Tecnológica</option>
                                    <option value="negocio_tradicional">Negócio Tradicional / Inovação</option>
                                    <option value="impacto_social">Impacto Social / Ambiental</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Área Temática *</label>
                                <select name="area" class="form-select form-control-custom" required>
                                    <option value="tecnologia">Tecnologia &amp; Sistemas</option>
                                    <option value="saude">Saúde &amp; Bem-Estar</option>
                                    <option value="educacao">Educação</option>
                                    <option value="agro">Agronegócio</option>
                                    <option value="financas">Finanças / Fintech</option>
                                    <option value="outro">Outra Área</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label-custom">Resumo Executivo (mín. 20 caracteres) *</label>
                                <textarea name="descricao" class="form-control-custom" rows="3" required minlength="20" placeholder="Descreva a proposta de valor da startup..."></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Problema que Resolve</label>
                                <textarea name="problema" class="form-control-custom" rows="2" placeholder="Qual é a dor real identificada?"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Solução Proposta</label>
                                <textarea name="solucao" class="form-control-custom" rows="2" placeholder="Como a sua ideia resolve esse problema?"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label-custom">Pitch Deck (PDF, PPT, PPTX ou ZIP — opcional)</label>
                                <input type="file" name="pitch_ficheiro" class="form-control form-control-custom" accept=".pdf,.ppt,.pptx,.zip">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer-custom">
                        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-paper-plane me-1"></i> Submeter para Avaliação</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
