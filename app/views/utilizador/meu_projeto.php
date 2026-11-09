<?php
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

obrigarLogin();

$realPerfil     = $_SESSION['usuario_perfil'] ?? 'utilizador';
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
        $fallback  = $resFallback ? $resFallback->fetch_assoc() : null;
        $idUsuario = $fallback ? (int)$fallback['id'] : 0;
    }

    $stmtU = $mysqli->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1");
    if ($stmtU) {
        $stmtU->bind_param('i', $idUsuario);
        $stmtU->execute();
        $uRow = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();
        $nome = $uRow['nome'] ?? 'Estudante';
    } else {
        $nome = 'Estudante';
    }
    $nomeUsuario = $nome;
    $perfil      = 'utilizador';
} else {
    $idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
    $nome      = $_SESSION['usuario_nome'] ?? 'Utilizador';
}

/* ── PROJETO DO ESTUDANTE ── */
$stmtP = $mysqli->prepare("
    SELECT p.*, u.nome AS autor_nome, u.email AS autor_email
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    WHERE p.criado_por = ?
    ORDER BY p.criado_em DESC LIMIT 1
");
$stmtP->bind_param('i', $idUsuario);
$stmtP->execute();
$projeto = $stmtP->get_result()->fetch_assoc();
$stmtP->close();

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

$membros        = [];
$mentorAtivo    = null;
$termoProjeto   = null;
$metasFase      = [];
$tarefasProjeto = [];
$documentos     = [];
$comentarios    = [];
$mensagensChat  = [];
$metasValidadasCount = 0;
$totalMetasFase      = 0;

if ($projeto) {
    $idProjeto  = (int)$projeto['id'];
    $estadoProj = (string)($projeto['estado'] ?? '');
    $faseActual = (string)($projeto['fase'] ?? 'ideacao');

    /* 1. Equipa */
    $stmtEq = $mysqli->prepare("
        SELECT u.nome, u.email, mp.papel, mp.id_usuario
        FROM membros_projeto mp
        JOIN usuarios u ON u.id = mp.id_usuario
        WHERE mp.id_projeto = ?
    ");
    if ($stmtEq) {
        $stmtEq->bind_param('i', $idProjeto);
        $stmtEq->execute();
        $membros = $stmtEq->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtEq->close();
    }

    /* 2. Mentor atribuído */
    $stmtMent = $mysqli->prepare("
        SELECT m.id AS id_mentoria, u.nome AS mentor_nome, u.email AS mentor_email, mt.especialidade
        FROM mentorias m
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u ON u.id = mt.id_usuario
        WHERE m.id_projeto = ? AND m.estado = 'activa'
        ORDER BY m.id DESC LIMIT 1
    ");
    if ($stmtMent) {
        $stmtMent->bind_param('i', $idProjeto);
        $stmtMent->execute();
        $mentorAtivo = $stmtMent->get_result()->fetch_assoc();
        $stmtMent->close();
    }

    /* 3. Termo de incubação */
    $stmtTermo = $mysqli->prepare("
        SELECT *
        FROM termos_incubacao
        WHERE id_projeto = ? AND estado IN ('gerado', 'pendente_assinatura', 'assinado')
        ORDER BY criado_em DESC LIMIT 1
    ");
    if ($stmtTermo) {
        $stmtTermo->bind_param('i', $idProjeto);
        $stmtTermo->execute();
        $termoProjeto = $stmtTermo->get_result()->fetch_assoc();
        $stmtTermo->close();
    }

    /* 4. Metas da fase atual */
    $stmtM = $mysqli->prepare("
        SELECT mp.*, mpd.numero, mpd.titulo AS meta_titulo, mpd.descricao AS meta_descricao,
               mpd.evidencia_tipo, mpd.evidencia_desc, mpd.peso_percentual, mpd.prazo_dias
        FROM metas_projeto mp
        JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
        WHERE mp.id_projeto = ? AND mpd.fase = ?
        ORDER BY mpd.numero ASC
    ");
    if ($stmtM) {
        $stmtM->bind_param('is', $idProjeto, $faseActual);
        $stmtM->execute();
        $metasFase = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtM->close();
    }
    $totalMetasFase = count($metasFase);
    foreach ($metasFase as $mf) {
        if (($mf['estado'] ?? '') === 'concluida') {
            $metasValidadasCount++;
        }
    }

    /* 5. Tarefas do mentor */
    $stmtT = $mysqli->prepare("
        SELECT *
        FROM tarefas
        WHERE id_projeto = ?
        ORDER BY COALESCE(validada_mentor, 0) ASC, data_limite ASC
    ");
    if ($stmtT) {
        $stmtT->bind_param('i', $idProjeto);
        $stmtT->execute();
        $tarefasProjeto = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtT->close();
    }

    /* 6. Documentos do projecto */
    $stmtDoc = $mysqli->prepare("
        SELECT f.*, u.nome AS quem_submeteu
        FROM ficheiros_projeto f
        LEFT JOIN usuarios u ON u.id = f.id_usuario_up
        WHERE f.id_projeto = ?
        ORDER BY f.id DESC
    ");
    if ($stmtDoc) {
        $stmtDoc->bind_param('i', $idProjeto);
        $stmtDoc->execute();
        $documentos = $stmtDoc->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtDoc->close();
    }

    /* 7. Comentários oficiais */
    $stmtCom = $mysqli->prepare("
        SELECT c.comentario, c.fase, c.criado_em, u.nome, u.perfil
        FROM comentarios_projetos c
        JOIN usuarios u ON u.id = c.id_usuario
        WHERE c.id_projeto = ?
        ORDER BY c.criado_em DESC
    ");
    if ($stmtCom) {
        $stmtCom->bind_param('i', $idProjeto);
        $stmtCom->execute();
        $comentarios = $stmtCom->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtCom->close();
    }

    /* 8. Mensagens do chat do projecto */
    $stmtMsg = $mysqli->prepare("
        SELECT m.*, u.nome, u.perfil
        FROM mensagens m
        JOIN usuarios u ON u.id = m.id_usuario
        WHERE m.id_projeto = ?
        ORDER BY m.criado_at ASC
    ");
    if ($stmtMsg) {
        $stmtMsg->bind_param('i', $idProjeto);
        $stmtMsg->execute();
        $mensagensChat = $stmtMsg->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMsg->close();
    }
}

$tabActiva = $_GET['tab'] ?? 'proposta';
if (!in_array($tabActiva, ['proposta', 'metas', 'documentos', 'comunicacao'], true)) {
    $tabActiva = 'proposta';
}

$tituloPagina = 'Minha Startup';
$paginaActiva = 'projetos';
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

    <?php if (!$projeto): ?>
        <header class="sp-header">
            <div class="sp-header-top">
                <div class="sp-kicker">Minha Startup · Dossiê do Projecto</div>
                <span class="sp-chip sp-chip-neutral">Sem projecto submetido</span>
            </div>
            <h1 class="sp-title">Ainda não tem nenhuma startup registada</h1>
        </header>

        <section class="sp-surface-primary">
            <p class="mb-3" style="color: var(--sp-text-sec);">
                Submeta a proposta da sua iniciativa no Painel Geral para aceder ao dossiê de acompanhamento, equipa e documentos.
            </p>
            <a href="<?= htmlspecialchars($hrefPainelEstudante ?? '/incubadora_ispsn/public/index.php', ENT_QUOTES, 'UTF-8') ?>" class="sp-btn-primary">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span>Ir para o Painel Geral</span>
            </a>
        </section>

    <?php else: ?>
        <?php
        $idProjeto   = (int)$projeto['id'];
        $estadoRaw   = (string)($projeto['estado'] ?? '');
        $faseKey     = (string)($projeto['fase'] ?? 'ideacao');
        $faseLabel   = $labelsFase[$faseKey] ?? strtoupper(str_replace('_', ' ', $faseKey));
        $estadoLabel = $labelsEstado[$estadoRaw] ?? $estadoRaw;
        $tipoKey     = (string)($projeto['tipo'] ?? '');
        $tipoLabel   = $labelsTipo[$tipoKey] ?? ($tipoKey !== '' ? ucfirst(str_replace('_', ' ', $tipoKey)) : '');
        $pontos      = (int)($projeto['pontos'] ?? 0);
        $dataCriacao = !empty($projeto['criado_em']) ? date('d/m/Y', strtotime((string)$projeto['criado_em'])) : '';

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

        $isIncubadoOuGrad = in_array($estadoRaw, ['incubado', 'fundo_investimento', 'concluido'], true);
        $selfRedirectBase = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php';
        ?>

        <!-- ══ CABEÇALHO DO DOSSIÊ DA STARTUP ══ -->
        <header class="std-hero-card">
            <div class="sp-header-top">
                <div class="sp-kicker">
                    <i class="fa-solid fa-rocket me-1" style="color:#D97706;"></i>
                    Minha Startup · Dossiê do Projecto<?php if ($isIncubadoOuGrad): ?> · Fase Actual: <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                </div>
                <span class="sp-chip <?= $chipClass ?>">
                    <i class="fa-solid <?= $chipIcon ?>" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($estadoLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h1 class="sp-title"><?= htmlspecialchars((string)$projeto['titulo'], ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <?php if ($isIncubadoOuGrad): ?>
                        <div class="sp-metrics-box">
                            <span class="sp-num fw-semibold">
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
                    <?php if (!$isAdminOrSuper): ?>
                        <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalEditarProposta">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            <span>Editar Proposta</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sp-meta-row">
                <?php if ($dataCriacao !== ''): ?>
                    <span class="sp-meta-pill sp-num">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <span>Submetido em <?= htmlspecialchars($dataCriacao, ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                <?php endif; ?>
                <?php if (!empty($projeto['area_tematica'])): ?>
                    <span class="sp-meta-pill">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <span>Área: <?= htmlspecialchars(ucfirst((string)$projeto['area_tematica']), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                <?php endif; ?>
                <?php if ($tipoLabel !== ''): ?>
                    <span class="sp-meta-pill">
                        <i class="fa-solid fa-tag" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($tipoLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                <?php endif; ?>
            </div>
        </header>

        <!-- ══ ABAS DE NAVEGAÇÃO DO DOSSIÊ ══ -->
        <nav class="sp-tabs" role="tablist" aria-label="Secções do dossiê da startup">
            <button type="button" class="sp-tab-btn <?= $tabActiva === 'proposta' ? 'active' : '' ?>" onclick="mudarAbaStartup('proposta', this)">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                <span>Proposta &amp; Equipa</span>
            </button>
            <button type="button" class="sp-tab-btn <?= $tabActiva === 'metas' ? 'active' : '' ?>" onclick="mudarAbaStartup('metas', this)">
                <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                <span>Metas &amp; Tarefas</span>
                <?php if ($totalMetasFase > 0): ?>
                    <span class="sp-code">(<?= (int)$metasValidadasCount ?>/<?= (int)$totalMetasFase ?>)</span>
                <?php endif; ?>
            </button>
            <button type="button" class="sp-tab-btn <?= $tabActiva === 'documentos' ? 'active' : '' ?>" onclick="mudarAbaStartup('documentos', this)">
                <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                <span>Documentos</span>
                <span class="sp-code">(<?= count($documentos) ?>)</span>
            </button>
            <button type="button" class="sp-tab-btn <?= $tabActiva === 'comunicacao' ? 'active' : '' ?>" onclick="mudarAbaStartup('comunicacao', this)">
                <i class="fa-solid fa-comments" aria-hidden="true"></i>
                <span>Feedback &amp; Chat</span>
                <?php if (count($comentarios) > 0): ?>
                    <span class="sp-code">(<?= count($comentarios) ?>)</span>
                <?php endif; ?>
            </button>
        </nav>

        <!-- ══════════════════════════════════════════════
             ABA 1: PROPOSTA & EQUIPA
             ══════════════════════════════════════════════ -->
        <div id="sp-pane-proposta" class="sp-tab-pane <?= $tabActiva === 'proposta' ? '' : 'd-none' ?>">
            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <section class="sp-surface-primary">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-lightbulb"></i></span>
                                <div>
                                    <div class="sp-kicker">Enquadramento da Iniciativa</div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Descrição da Proposta</h2>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4" style="color: var(--sp-text); white-space: pre-line;">
                            <?= htmlspecialchars((string)($projeto['descricao'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                        </div>

                        <?php if (!empty($projeto['problema'])): ?>
                            <div class="p-3 mb-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-left: 4px solid var(--sp-accent); border-radius: var(--sp-radius-sm);">
                                <div class="sp-kicker mb-1" style="color: var(--sp-accent);">Problema Identificado</div>
                                <div style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                                    <?= nl2br(htmlspecialchars((string)$projeto['problema'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($projeto['solucao'])): ?>
                            <div class="p-3 mb-0" style="background: var(--sp-ok-bg); border: 1px solid var(--sp-ok-border); border-left: 4px solid var(--sp-ok-text); border-radius: var(--sp-radius-sm);">
                                <div class="sp-kicker mb-1" style="color: var(--sp-ok-text);">Solução Proposta</div>
                                <div style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                                    <?= nl2br(htmlspecialchars((string)$projeto['solucao'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <div class="col-lg-4">
                    <!-- EQUIPA DO PROJECTO -->
                    <section class="sp-surface-primary mb-4">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
                                <div>
                                    <div class="sp-kicker">Membros</div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-md);">Equipa</h2>
                                </div>
                            </div>
                            <?php if (!$isAdminOrSuper): ?>
                                <button type="button" class="sp-btn-secondary py-1 px-2" style="min-height: 34px; font-size: var(--sp-fs-xs);" data-bs-toggle="modal" data-bs-target="#modalAddMembro">
                                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                                    <span>Adicionar</span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($membros)): ?>
                            <div style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                                <strong><?= htmlspecialchars((string)$projeto['autor_nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span class="sp-chip sp-chip-neutral ms-1">Líder</span>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($membros as $mb): ?>
                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <div class="fw-semibold" style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                                                <?= htmlspecialchars((string)$mb['nome'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div style="font-size: var(--sp-fs-xs); color: var(--sp-text-muted);">
                                                <?= htmlspecialchars((string)($mb['papel'] ?? 'Membro'), ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- ORIENTAÇÃO E TERMO (QUANDO EXISTIREM) -->
                    <?php if ($mentorAtivo || $termoProjeto): ?>
                        <aside class="sp-surface-aside">
                            <?php if ($mentorAtivo): ?>
                                <div class="sp-aside-block">
                                    <span class="sp-aside-icon" aria-hidden="true"><i class="fa-solid fa-user-tie"></i></span>
                                    <div class="sp-aside-body">
                                        <div class="sp-kicker">Mentor Atribuído</div>
                                        <div class="fw-bold" style="font-size: var(--sp-fs-sm);"><?= htmlspecialchars((string)$mentorAtivo['mentor_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($mentorAtivo['especialidade'])): ?>
                                            <div style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec);"><?= htmlspecialchars((string)$mentorAtivo['especialidade'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($termoProjeto):
                                $estT = (string)($termoProjeto['estado'] ?? '');
                            ?>
                                <div class="sp-aside-block">
                                    <span class="sp-aside-icon" aria-hidden="true"><i class="fa-solid fa-file-contract"></i></span>
                                    <div class="sp-aside-body">
                                        <div class="sp-kicker">Termo de Incubação</div>
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-1">
                                            <span class="sp-code fw-bold"><?= htmlspecialchars((string)$termoProjeto['codigo_termo'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="sp-chip <?= $estT === 'assinado' ? 'sp-chip-ok' : 'sp-chip-warn' ?>">
                                                <?= htmlspecialchars($estT === 'assinado' ? 'Assinado' : ($estT === 'pendente_assinatura' ? 'Pendente' : ucfirst($estT)), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </aside>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════
             ABA 2: METAS & TAREFAS
             ══════════════════════════════════════════════ -->
        <div id="sp-pane-metas" class="sp-tab-pane <?= $tabActiva === 'metas' ? '' : 'd-none' ?>">
            <?php if (!$isIncubadoOuGrad): ?>
                <div class="sp-empty-line">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <span>As metas por fase ficam disponíveis assim que o projecto entra no estado <strong>Em Incubação</strong>.</span>
                </div>
            <?php else: ?>
                <div class="sp-section-label">
                    <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                    <span>Metas da Fase Actual: <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?> (<?= (int)$metasValidadasCount ?> de <?= (int)$totalMetasFase ?> validadas)</span>
                </div>

                <?php if (empty($metasFase)): ?>
                    <div class="sp-empty-line mb-4">
                        <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                        <span>Nenhuma meta inicializada para a fase de <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?>.</span>
                    </div>
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
                            $canSubmit = (!$isAdminOrSuper) && ($estM === 'activa' || $estM === 'reprovada');
                            $payloadM = json_encode([
                                'id'     => (int)$m['id'],
                                'titulo' => (string)$m['meta_titulo'],
                                'tipo'   => (string)$m['evidencia_tipo'],
                                'desc'   => (string)$m['evidencia_desc'],
                                'texto'  => (string)($m['evidencia_texto'] ?? ''),
                                'link'   => (string)($m['evidencia_link'] ?? ''),
                                'path'   => (string)($m['evidencia_path'] ?? ''),
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                            <li class="sp-list-row">
                                <div style="max-width: 72%;">
                                    <div>
                                        <span class="sp-code fw-bold me-2" style="color: var(--sp-accent);">#<?= (int)$m['numero'] ?></span>
                                        <strong style="color: var(--sp-text);"><?= htmlspecialchars((string)$m['meta_titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if ($prazoM !== ''): ?>
                                            <span class="sp-num ms-2" style="font-size: var(--sp-fs-xs); color: var(--sp-text-muted);">
                                                · Prazo: <?= htmlspecialchars($prazoM, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec); margin-top: 2px;">
                                        <?= htmlspecialchars((string)$m['meta_descricao'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php if (!empty($m['feedback_mentor'])): ?>
                                        <div class="mt-1" style="font-size: var(--sp-fs-xs); color: var(--sp-err-text);">
                                            <strong>Feedback do mentor:</strong> <?= htmlspecialchars((string)$m['feedback_mentor'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($canSubmit): ?>
                                        <button type="button" class="sp-btn-primary py-1 px-3" style="min-height: 36px;" onclick="abrirSubmeterEvidencia(<?= htmlspecialchars((string)$payloadM, ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                            <span>Submeter Evidência</span>
                                        </button>
                                    <?php endif; ?>
                                    <span class="sp-chip <?= $chipM ?>"><?= htmlspecialchars($lblM, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="sp-section-label">
                    <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                    <span>Tarefas do Mentor</span>
                </div>
                <?php if (empty($tarefasProjeto)): ?>
                    <div class="sp-empty-line">
                        <i class="fa-regular fa-clipboard" aria-hidden="true"></i>
                        <span>Sem tarefas adicionais atribuídas pelo mentor.</span>
                    </div>
                <?php else: ?>
                    <ul class="sp-list">
                        <?php foreach ($tarefasProjeto as $t):
                            $valMent = (int)($t['validada_mentor'] ?? 0) === 1;
                            $statusT = (string)($t['status'] ?? '');
                            $conclT  = $valMent || $statusT === 'concluida' || $statusT === 'concluid';
                            $prazoT  = !empty($t['data_limite']) ? date('d/m/Y', strtotime((string)$t['data_limite'])) : '';
                            $chipTar = $valMent ? 'sp-chip-ok' : ($conclT ? 'sp-chip-warn' : 'sp-chip-neutral');
                            $lblTar  = $valMent ? 'Validada' : ($conclT ? 'Aguardando Validação' : 'Pendente');
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
            <?php endif; ?>
        </div>

        <!-- ══════════════════════════════════════════════
             ABA 3: DOCUMENTOS
             ══════════════════════════════════════════════ -->
        <div id="sp-pane-documentos" class="sp-tab-pane <?= $tabActiva === 'documentos' ? '' : 'd-none' ?>">
            <section class="sp-surface-primary">
                <div class="sp-card-head">
                    <div class="sp-card-head-title">
                        <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-folder-open"></i></span>
                        <div>
                            <div class="sp-kicker">Repositório Central</div>
                            <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Documentos do Projecto</h2>
                        </div>
                    </div>
                    <?php if (!$isAdminOrSuper): ?>
                        <button type="button" class="sp-btn-primary" data-bs-toggle="modal" data-bs-target="#modalDoc">
                            <i class="fa-solid fa-upload" aria-hidden="true"></i>
                            <span>Carregar Documento</span>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($documentos)): ?>
                    <div class="sp-empty-line">
                        <i class="fa-regular fa-file" aria-hidden="true"></i>
                        <span>Ainda não foram carregados documentos adicionais neste projecto.</span>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="sp-table">
                            <thead>
                                <tr>
                                    <th>Título do Documento</th>
                                    <th>Categoria</th>
                                    <th>Submetido por</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documentos as $doc): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars((string)$doc['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="sp-chip sp-chip-neutral"><?= htmlspecialchars((string)($doc['tipo'] ?? 'Outro'), ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td style="color: var(--sp-text-sec);"><?= htmlspecialchars((string)($doc['quem_submeteu'] ?? 'Utilizador'), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- ══════════════════════════════════════════════
             ABA 4: FEEDBACK & CHAT
             ══════════════════════════════════════════════ -->
        <div id="sp-pane-comunicacao" class="sp-tab-pane <?= $tabActiva === 'comunicacao' ? '' : 'd-none' ?>">
            <div class="row g-4 align-items-start">
                <!-- COMENTÁRIOS OFICIAIS -->
                <div class="col-lg-7">
                    <section class="sp-surface-primary">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-clipboard-list"></i></span>
                                <div>
                                    <div class="sp-kicker">Pareceres e Revisões</div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Feedback da Comissão e Mentores</h2>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($comentarios)): ?>
                            <div class="sp-empty-line">
                                <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
                                <span>Sem comentários oficiais registados neste projecto.</span>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($comentarios as $c): ?>
                                    <div class="p-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-radius: var(--sp-radius-sm);">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                                            <div>
                                                <strong style="font-size: var(--sp-fs-sm);"><?= htmlspecialchars((string)$c['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span class="sp-chip sp-chip-neutral ms-1"><?= htmlspecialchars(ucfirst((string)$c['perfil']), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <span class="sp-num" style="font-size: var(--sp-fs-xs); color: var(--sp-text-muted);">
                                                <?= date('d/m/Y H:i', strtotime((string)$c['criado_em'])) ?>
                                            </span>
                                        </div>
                                        <div style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                                            <?= nl2br(htmlspecialchars((string)$c['comentario'], ENT_QUOTES, 'UTF-8')) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- CHAT DO PROJECTO -->
                <div class="col-lg-5">
                    <section class="sp-surface-primary">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-comments"></i></span>
                                <div>
                                    <div class="sp-kicker">Canal Direto</div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-md);">Mensagens do Projecto</h2>
                                </div>
                            </div>
                        </div>

                        <div style="max-height: 280px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px;">
                            <?php if (empty($mensagensChat)): ?>
                                <div class="sp-empty-line">
                                    <span>Sem mensagens trocadas neste projecto.</span>
                                </div>
                            <?php else: ?>
                                <?php foreach ($mensagensChat as $mc):
                                    $isAutor = ((int)$mc['id_usuario'] === (int)$idUsuario);
                                ?>
                                    <div class="p-2 px-3" style="background: <?= $isAutor ? 'var(--sp-warn-bg)' : 'var(--sp-surface-muted)' ?>; border: 1px solid <?= $isAutor ? 'var(--sp-warn-border)' : 'var(--sp-border)' ?>; border-radius: var(--sp-radius-sm);">
                                        <div class="sp-kicker mb-1"><?= htmlspecialchars((string)$mc['nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div style="font-size: var(--sp-fs-sm); color: var(--sp-text);">
                                            <?= nl2br(htmlspecialchars((string)$mc['mensagem'], ENT_QUOTES, 'UTF-8')) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if (!$isAdminOrSuper): ?>
                            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" class="d-flex gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="enviar_mensagem">
                                <input type="hidden" name="id_projeto" value="<?= (int)$idProjeto ?>">
                                <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfRedirectBase . '?tab=comunicacao', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="text" name="mensagem" class="form-control-custom" placeholder="Escrever mensagem..." required>
                                <button type="submit" class="sp-btn-primary px-3" aria-label="Enviar mensagem">
                                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </div>

        <script>
        function mudarAbaStartup(tabId, btnEl) {
            document.querySelectorAll('.sp-tab-pane').forEach(function(el) {
                el.classList.add('d-none');
            });
            document.querySelectorAll('.sp-tab-btn').forEach(function(b) {
                b.classList.remove('active');
            });
            var target = document.getElementById('sp-pane-' + tabId);
            if (target) target.classList.remove('d-none');
            if (btnEl) btnEl.classList.add('active');
        }
        </script>

        <?php if (!$isAdminOrSuper): ?>
            <!-- MODAL: EDITAR PROPOSTA -->
            <div class="modal fade" id="modalEditarProposta" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content modal-content-custom">
                        <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="editar_proposta">
                            <input type="hidden" name="id_projeto" value="<?= (int)$idProjeto ?>">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfRedirectBase . '?tab=proposta', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="modal-header-custom d-flex justify-content-between align-items-center">
                                <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-pen-to-square me-2 text-warning"></i>Actualizar Proposta</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body-custom">
                                <div class="mb-3">
                                    <label class="form-label-custom">Nome da Startup *</label>
                                    <input type="text" name="titulo" class="form-control-custom" required value="<?= htmlspecialchars((string)$projeto['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Tipo *</label>
                                        <select name="tipo" class="form-control-custom" required>
                                            <?php foreach ($labelsTipo as $tk => $tl): ?>
                                                <option value="<?= htmlspecialchars($tk, ENT_QUOTES, 'UTF-8') ?>" <?= (($projeto['tipo'] ?? '') === $tk) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($tl, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Área Temática</label>
                                        <select name="area" class="form-control-custom">
                                            <?php
                                            $areasMap = ['tecnologia' => 'Tecnologia', 'saude' => 'Saúde', 'educacao' => 'Educação', 'agro' => 'Agronegócio', 'financas' => 'Finanças', 'outro' => 'Outro'];
                                            foreach ($areasMap as $ak => $al):
                                            ?>
                                                <option value="<?= $ak ?>" <?= (($projeto['area_tematica'] ?? '') === $ak) ? 'selected' : '' ?>><?= $al ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label-custom">Descrição Geral *</label>
                                    <textarea name="descricao" class="form-control-custom" rows="3" required><?= htmlspecialchars((string)($projeto['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Problema que Resolve</label>
                                        <textarea name="problema" class="form-control-custom" rows="3"><?= htmlspecialchars((string)($projeto['problema'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Solução Proposta</label>
                                        <textarea name="solucao" class="form-control-custom" rows="3"><?= htmlspecialchars((string)($projeto['solucao'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label-custom">Actualizar Pitch Deck (PDF, PPT, PPTX — Opcional)</label>
                                    <input type="file" name="pitch_ficheiro" class="form-control-custom" accept=".pdf,.ppt,.pptx,.zip">
                                </div>
                            </div>
                            <div class="modal-footer-custom">
                                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Alterações</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- MODAL: ADICIONAR MEMBRO -->
            <div class="modal fade" id="modalAddMembro" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content modal-content-custom">
                        <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="adicionar_membro">
                            <input type="hidden" name="id_projeto" value="<?= (int)$idProjeto ?>">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfRedirectBase . '?tab=proposta', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="modal-header-custom">
                                <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>Adicionar Membro à Equipa</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body-custom">
                                <div class="mb-3">
                                    <label class="form-label-custom">E-mail do Utilizador Registado *</label>
                                    <input type="email" name="email" class="form-control-custom" required placeholder="email@exemplo.com">
                                </div>
                                <div>
                                    <label class="form-label-custom">Papel / Função</label>
                                    <select name="papel" class="form-control-custom">
                                        <option value="Cofundador">Cofundador</option>
                                        <option value="Desenvolvedor">Desenvolvedor</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Membro">Membro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer-custom">
                                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn-primary-custom">Adicionar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- MODAL: CARREGAR DOCUMENTO -->
            <div class="modal fade" id="modalDoc" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content modal-content-custom">
                        <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="upload_documento">
                            <input type="hidden" name="id_projeto" value="<?= (int)$idProjeto ?>">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfRedirectBase . '?tab=documentos', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="modal-header-custom">
                                <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-upload me-2" aria-hidden="true"></i>Carregar Documento</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body-custom">
                                <div class="mb-3">
                                    <label class="form-label-custom">Título do Documento *</label>
                                    <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Pitch Deck, Modelo de Negócio">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label-custom">Categoria</label>
                                    <select name="tipo" class="form-control-custom">
                                        <option value="Pitch">Pitch / Apresentação</option>
                                        <option value="Plano de Negócio">Plano de Negócio</option>
                                        <option value="Contrato">Contrato / Documento Legal</option>
                                        <option value="Outro">Outro</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label-custom">Ficheiro (PDF, Office, ZIP ou Imagem) *</label>
                                    <input type="file" name="ficheiro" class="form-control-custom" required>
                                </div>
                            </div>
                            <div class="modal-footer-custom">
                                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn-primary-custom">Carregar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- MODAL: SUBMETER EVIDÊNCIA -->
            <div class="modal fade" id="modalSubmeterEvidencia" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content modal-content-custom">
                        <form action="/incubadora_ispsn/app/controllers/metas_action.php" method="POST" enctype="multipart/form-data" class="m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="submeter_evidencia">
                            <input type="hidden" name="id_meta_projeto" id="submeterMetaId">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfRedirectBase . '?tab=metas', ENT_QUOTES, 'UTF-8') ?>">

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

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../partials/_layout_end.php';
?>
