<?php
// app/views/dashboard/utilizador.php
// Centro de Comando Interativo do Empreendedor — Incubadora ISPSN

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

$tituloPagina = 'Painel do Empreendedor';
$paginaActiva = 'meu-painel';

$userParam = $isAdminOrSuper ? ('?id_usuario=' . (int)$idUsuario) : '';
$userAmp   = $isAdminOrSuper ? ('&id_usuario=' . (int)$idUsuario) : '';

// ── 1. Listar todos os projetos do estudante (criador ou membro) ─────────
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

// ── 2. Carregar projeto ativo selecionado ────────────────────────────────
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

// ── 3. Carregar dados reais do projeto ───────────────────────────────────
$metasFase        = [];
$todasMetasPadrao = [
    'ideacao'   => [],
    'validacao' => [],
    'mvp'       => [],
    'tracao'    => [],
    'mercado'   => [],
];
$resMP = $mysqli->query("SELECT fase, numero, titulo, descricao, peso_percentual FROM metas_padrao WHERE activo = 1 ORDER BY fase, numero ASC");
if ($resMP) {
    while ($rmp = $resMP->fetch_assoc()) {
        $fKey = (string)$rmp['fase'];
        if (isset($todasMetasPadrao[$fKey])) {
            $todasMetasPadrao[$fKey][] = $rmp;
        }
    }
}

$tarefas          = [];
$reunioes         = [];
$mensagens        = [];
$membros          = [];
$documentos       = [];
$termoAtivo       = null;
$numAvalAtrib     = 0;
$numAvalConcl     = 0;
$mediasCriterios  = [
    'inovacao'         => 7,
    'viabilidade'      => 7,
    'impacto'          => 7,
    'equipa'           => 7,
    'sustentabilidade' => 7,
    'escalabilidade'   => 7,
    'mercado'          => 7,
    'proposta'         => 7,
];
$temNotasReais    = false;
$feedEventos      = [];

if ($meuProjeto) {
    $idP       = (int)$meuProjeto['id'];
    $faseAtual = (string)($meuProjeto['fase'] ?? 'ideacao');

    // Mentor associado (mentorias + mentores)
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
        $mentRow = $stmtMent->get_result()->fetch_assoc();
        $stmtMent->close();
        if ($mentRow) {
            $meuProjeto['mentor_nome']  = $mentRow['mentor_nome'];
            $meuProjeto['mentor_email'] = $mentRow['mentor_email'];
        }
    }

    // Garantir inicialização de metas da fase atual
    $stmtInitM = $mysqli->prepare("
        INSERT IGNORE INTO metas_projeto (id_projeto, id_meta_padrao, estado)
        SELECT ?, id, 'inactiva' FROM metas_padrao WHERE fase = ? AND activo = 1
    ");
    if ($stmtInitM) {
        $stmtInitM->bind_param('is', $idP, $faseAtual);
        $stmtInitM->execute();
        $stmtInitM->close();
    }

    // Metas da fase atual
    $stmtM = $mysqli->prepare("
        SELECT mp.*, mpad.numero, mpad.titulo, mpad.descricao AS descricao_padrao, mpad.peso_percentual AS pontos_meta
        FROM metas_projeto mp
        JOIN metas_padrao mpad ON mpad.id = mp.id_meta_padrao
        WHERE mp.id_projeto = ? AND mpad.fase = ?
        ORDER BY mpad.numero ASC
    ");
    if ($stmtM) {
        $stmtM->bind_param('is', $idP, $faseAtual);
        $stmtM->execute();
        $metasFase = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtM->close();
    }

    // Tarefas do mentor
    $stmtT = $mysqli->prepare("SELECT * FROM tarefas WHERE id_projeto = ? ORDER BY COALESCE(validada_mentor, 0) ASC, data_limite ASC");
    if ($stmtT) {
        $stmtT->bind_param('i', $idP);
        $stmtT->execute();
        $tarefas = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtT->close();
    }

    // Mensagens com o mentor / coordenação
    $stmtMsg = $mysqli->prepare("
        SELECT m.*, u.nome AS autor_nome, u.perfil AS autor_perfil
        FROM mensagens m
        JOIN usuarios u ON u.id = m.id_usuario
        WHERE m.id_projeto = ?
        ORDER BY m.id DESC
        LIMIT 12
    ");
    if ($stmtMsg) {
        $stmtMsg->bind_param('i', $idP);
        $stmtMsg->execute();
        $mensagens = array_reverse($stmtMsg->get_result()->fetch_all(MYSQLI_ASSOC));
        $stmtMsg->close();
    }

    // Equipa
    $stmtEq = $mysqli->prepare("
        SELECT u.id, u.nome, u.email, mp.papel
        FROM membros_projeto mp
        JOIN usuarios u ON u.id = mp.id_usuario
        WHERE mp.id_projeto = ?
        ORDER BY (mp.papel = 'Líder') DESC, u.nome ASC
    ");
    if ($stmtEq) {
        $stmtEq->bind_param('i', $idP);
        $stmtEq->execute();
        $membros = $stmtEq->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtEq->close();
    }

    // Documentos submetidos
    $stmtDoc = $mysqli->prepare("
        SELECT f.*, u.nome AS autor_nome
        FROM ficheiros_projeto f
        LEFT JOIN usuarios u ON u.id = f.id_usuario_up
        WHERE f.id_projeto = ?
        ORDER BY f.id DESC
    ");
    if ($stmtDoc) {
        $stmtDoc->bind_param('i', $idP);
        $stmtDoc->execute();
        $documentos = $stmtDoc->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtDoc->close();
    }

    // Termo de incubação
    $stmtTermo = $mysqli->prepare("
        SELECT *
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

    // Progresso de avaliação técnica (X de 3 avaliadores)
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

    // Médias reais por critério (se já houver avaliações registadas)
    $stmtMed = $mysqli->prepare("
        SELECT
            COUNT(*) AS n,
            ROUND(AVG(nota_inovacao), 1) AS inovacao,
            ROUND(AVG(nota_viabilidade), 1) AS viabilidade,
            ROUND(AVG(nota_impacto), 1) AS impacto,
            ROUND(AVG(nota_equipa), 1) AS equipa,
            ROUND(AVG(nota_sustentabilidade), 1) AS sustentabilidade,
            ROUND(AVG(nota_escalabilidade), 1) AS escalabilidade,
            ROUND(AVG(nota_mercado), 1) AS mercado,
            ROUND(AVG(nota_proposta), 1) AS proposta
        FROM avaliacoes
        WHERE id_projeto = ?
    ");
    if ($stmtMed) {
        $stmtMed->bind_param('i', $idP);
        $stmtMed->execute();
        $medRow = $stmtMed->get_result()->fetch_assoc();
        $stmtMed->close();
        if ($medRow && (int)$medRow['n'] > 0) {
            $temNotasReais = true;
            foreach ($mediasCriterios as $kCrit => $_v) {
                $mediasCriterios[$kCrit] = (float)($medRow[$kCrit] ?? 7);
            }
        }
    }

    // Histórico recente de estados + comentários
    $stmtHist = $mysqli->prepare("
        SELECT estado_novo AS titulo, motivo AS detalhe, data_mudanca AS data_ev, 'estado' AS tipo_ev
        FROM historico_estados
        WHERE id_projeto = ?
        ORDER BY data_mudanca DESC
        LIMIT 4
    ");
    if ($stmtHist) {
        $stmtHist->bind_param('i', $idP);
        $stmtHist->execute();
        $feedEventos = array_merge($feedEventos, $stmtHist->get_result()->fetch_all(MYSQLI_ASSOC));
        $stmtHist->close();
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
    $rowRes      = $stmtRes->get_result()->fetch_assoc();
    $numReservas = (int)($rowRes['n'] ?? 0);
    $stmtRes->close();
}

// ── 4. Mapas de estado e prontidão do dossiê ─────────────────────────────
$labelsEstado = [
    'submetido'          => ['txt' => 'Submetido (Em Triagem)',      'cor' => 'amber', 'icon' => 'fa-hourglass-half'],
    'em_avaliacao'       => ['txt' => 'Em Avaliação Técnica',        'cor' => 'blue',  'icon' => 'fa-magnifying-glass-chart'],
    'em_revisao'         => ['txt' => 'Revisão Solicitada',          'cor' => 'amber', 'icon' => 'fa-pen-ruler'],
    'aprovado'           => ['txt' => 'Aprovado para Incubação',     'cor' => 'green', 'icon' => 'fa-circle-check'],
    'rejeitado'          => ['txt' => 'Não Selecionado',             'cor' => 'red',   'icon' => 'fa-circle-xmark'],
    'incubado'           => ['txt' => 'Em Incubação Ativa',          'cor' => 'green', 'icon' => 'fa-rocket'],
    'fundo_investimento' => ['txt' => 'Fundo de Investimento',       'cor' => 'green', 'icon' => 'fa-sack-dollar'],
    'concluido'          => ['txt' => 'Startup Graduada',            'cor' => 'green', 'icon' => 'fa-award'],
];

$fasesOrdem = [
    'ideacao'   => ['lbl' => 'Ideação',   'num' => 1, 'icon' => 'fa-lightbulb',         'desc' => 'Validação do problema real, definição da persona e proposta única de valor.'],
    'validacao' => ['lbl' => 'Validação', 'num' => 2, 'icon' => 'fa-vial-circle-check', 'desc' => 'Entrevistas com clientes, testes de hipóteses de mercado e modelo de negócio.'],
    'mvp'       => ['lbl' => 'MVP',       'num' => 3, 'icon' => 'fa-cubes',             'desc' => 'Construção do Produto Mínimo Viável funcional e testes de usabilidade com utilizadores reais.'],
    'tracao'    => ['lbl' => 'Tração',    'num' => 4, 'icon' => 'fa-chart-line',        'desc' => 'Primeiras vendas ou utilizadores recorrentes, métricas de retenção e aquisição.'],
    'mercado'   => ['lbl' => 'Mercado',   'num' => 5, 'icon' => 'fa-globe',             'desc' => 'Expansão comercial, parcerias estratégicas e preparação para investimento externo.'],
];

$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

$urlPainelAtual = '/incubadora_ispsn/app/views/dashboard/utilizador.php'
    . ($meuProjeto ? ('?id_projeto=' . (int)$meuProjeto['id'] . $userAmp) : $userParam);

require_once __DIR__ . '/../partials/_layout.php';
?>

<div class="std-painel">

    <!-- FLASH MESSAGES -->
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
             ONBOARDING INTERATIVO (SEM PROJETO SUBMETIDO)
        ═══════════════════════════════════════════════════ -->
        <section class="std-command-hero">
            <div class="row g-4 align-items-center position-relative" style="z-index: 2;">
                <div class="col-lg-8">
                    <span class="std-live-pill mb-3" style="background: rgba(245,158,11,0.18); color: #FBBF24; border: 1px solid rgba(245,158,11,0.4);">
                        <span class="std-live-dot" style="background: #F59E0B;"></span>
                        <span>Incubadora de Startups do ISPSN</span>
                    </span>
                    <h1 style="font-size: 1.75rem; font-weight: 800; color: #FFFFFF; letter-spacing: -0.02em; margin-bottom: 10px;">
                        Transforme a sua ideia académica numa Startup real
                    </h1>
                    <p style="color: #CBD5E1; max-width: 62ch; margin-bottom: 20px; font-size: 0.94rem;">
                        Submeta a sua proposta inovadora para avaliação da Comissão Técnica, receba diagnóstico imediato por Inteligência Artificial, mentoria especializada e acesso ao coworking do ISPSN.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="std-hero-btn std-hero-btn-amber" data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
                            <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                            <span>Submeter Nova Ideia de Startup</span>
                        </button>
                        <a href="/incubadora_ispsn/app/views/utilizador/reservas.php<?= $userParam ?>" class="std-hero-btn">
                            <i class="fa-solid fa-building-user" aria-hidden="true"></i>
                            <span>Explorar Espaços de Coworking</span>
                        </a>
                        <a href="/incubadora_ispsn/app/views/admin/ranking.php<?= $userParam ?>" class="std-hero-btn">
                            <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                            <span>Ver Ranking de Startups</span>
                        </a>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="std-hero-glass">
                        <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #FBBF24; margin-bottom: 10px;">
                            <i class="fa-solid fa-route me-1"></i> Como Funciona o Programa
                        </div>
                        <div class="d-flex flex-column gap-2" style="font-size: 0.82rem; color: #E2E8F0;">
                            <div><strong style="color:#FBBF24;">1. Submissão:</strong> Registe problema, solução e Pitch Deck.</div>
                            <div><strong style="color:#38BDF8;">2. Avaliação (3 Juries):</strong> Média ≥ 7.0 aprova a proposta.</div>
                            <div><strong style="color:#34D399;">3. Incubação (5 Fases):</strong> Mentoria, metas e Startup Points.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    <?php else:
        $idP       = (int)$meuProjeto['id'];
        $estKey    = (string)($meuProjeto['estado'] ?? 'submetido');
        $faseKey   = (string)($meuProjeto['fase'] ?? 'ideacao');
        $estMeta   = $labelsEstado[$estKey] ?? ['txt' => ucfirst($estKey), 'cor' => 'amber', 'icon' => 'fa-circle-info'];
        $faseMeta  = $fasesOrdem[$faseKey]  ?? ['lbl' => ucfirst($faseKey), 'num' => 1, 'icon' => 'fa-lightbulb', 'desc' => ''];
        $pontosSP  = (int)($meuProjeto['pontos'] ?? 0);

        // Prontidão real do Dossiê (4 dimensões verificáveis na BD)
        $temProblemaSol = (strlen(trim((string)($meuProjeto['problema'] ?? ''))) >= 15 && strlen(trim((string)($meuProjeto['solucao'] ?? ''))) >= 15);
        $temPitchDoc    = (!empty($meuProjeto['pitch_path']) || count($documentos) > 0);
        $temEquipa      = (count($membros) >= 2);
        $temDescricao   = (strlen(trim((string)($meuProjeto['descricao'] ?? ''))) >= 40);

        $checklistDossie = [
            [
                'id'     => 'desc',
                'titulo' => 'Resumo Executivo Estruturado',
                'det'    => $temDescricao ? strlen(trim((string)$meuProjeto['descricao'])) . ' caracteres registados' : 'Expanda a descrição da proposta',
                'ok'     => $temDescricao,
                'modal'  => '#modalEditarProposta',
                'btn'    => 'Editar Resumo',
                'icon'   => 'fa-file-signature',
            ],
            [
                'id'     => 'prob_sol',
                'titulo' => 'Problema & Solução Definidos',
                'det'    => $temProblemaSol ? 'Problema e proposta de valor preenchidos' : 'Detalhe a dor do mercado e a solução',
                'ok'     => $temProblemaSol,
                'modal'  => '#modalEditarProposta',
                'btn'    => 'Definir Canvas',
                'icon'   => 'fa-bullseye',
            ],
            [
                'id'     => 'pitch',
                'titulo' => 'Pitch Deck / Documento Técnico',
                'det'    => $temPitchDoc ? (count($documentos) + (!empty($meuProjeto['pitch_path']) ? 1 : 0)) . ' ficheiro(s) anexado(s)' : 'Anexe o PDF/PPT da apresentação',
                'ok'     => $temPitchDoc,
                'modal'  => '#modalUploadDoc',
                'btn'    => 'Carregar Pitch',
                'icon'   => 'fa-file-powerpoint',
            ],
            [
                'id'     => 'equipa',
                'titulo' => 'Equipa Empreendedora',
                'det'    => count($membros) . ' membro(s) na startup' . ($temEquipa ? ' (Equipa formada)' : ' (Convide co-fundadores)'),
                'ok'     => $temEquipa,
                'modal'  => '#modalAddMembro',
                'btn'    => 'Convidar Equipa',
                'icon'   => 'fa-user-group',
            ],
        ];
        $itensProntos   = count(array_filter($checklistDossie, fn($c) => $c['ok']));
        $pctProntidao   = (int)round(($itensProntos / 4) * 100);

        // Progresso de metas da fase ou de avaliação
        $metasValidadasCount = count(array_filter($metasFase, fn($m) => ($m['estado'] ?? '') === 'concluida'));
        $totalMetasFase      = count($metasFase);
        $pctMetasFase        = $totalMetasFase > 0 ? (int)round(($metasValidadasCount / $totalMetasFase) * 100) : 0;
        $pctAvaliacao        = (int)round((min(3, $numAvalConcl) / 3) * 100);
        $emFaseTriagem       = in_array($estKey, ['submetido', 'em_avaliacao', 'em_revisao', 'rejeitado'], true);
    ?>

        <!-- ═══════════════════════════════════════════════════
             1. HERO DE COMANDO DA STARTUP (NAVY + AMBER CONTRAST)
        ═══════════════════════════════════════════════════ -->
        <section class="std-command-hero">
            <div class="row g-4 align-items-center position-relative" style="z-index: 2;">
                <div class="col-lg-7">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="std-live-pill" style="background: rgba(245,158,11,0.2); color: #FCD34D; border: 1px solid rgba(245,158,11,0.45);">
                            <span class="std-live-dot" style="background: #FBBF24;"></span>
                            <span><?= htmlspecialchars($estMeta['txt'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <span class="std-live-pill" style="background: rgba(255,255,255,0.08); color: #E2E8F0; border: 1px solid rgba(255,255,255,0.14);">
                            <i class="fa-solid <?= htmlspecialchars($faseMeta['icon'], ENT_QUOTES, 'UTF-8') ?>" style="color:#FBBF24;"></i>
                            <span>Fase <?= (int)$faseMeta['num'] ?>/5 · <?= htmlspecialchars($faseMeta['lbl'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <?php if (count($listaProjetos) > 1): ?>
                            <form method="GET" class="m-0 d-inline-block">
                                <?php if ($isAdminOrSuper): ?>
                                    <input type="hidden" name="id_usuario" value="<?= (int)$idUsuario ?>">
                                <?php endif; ?>
                                <select name="id_projeto" class="form-select form-select-sm" style="background:#1E293B; color:#F8FAFC; border-color:rgba(255,255,255,0.2); font-size:0.75rem; font-weight:700; border-radius:999px; padding:3px 28px 3px 12px;" onchange="this.form.submit()">
                                    <?php foreach ($listaProjetos as $lp): ?>
                                        <option value="<?= (int)$lp['id'] ?>" <?= ((int)$lp['id'] === $idP) ? 'selected' : '' ?>>
                                            Alternar: <?= htmlspecialchars((string)$lp['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </div>

                    <h1 style="font-size: 1.7rem; font-weight: 800; color: #FFFFFF; letter-spacing: -0.02em; margin: 4px 0 8px;">
                        <?= htmlspecialchars((string)$meuProjeto['titulo'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>

                    <div class="d-flex flex-wrap align-items-center gap-3 mb-3" style="font-size: 0.8rem; color: #CBD5E1;">
                        <span><i class="fa-solid fa-layer-group me-1" style="color:#FBBF24;"></i><?= htmlspecialchars(ucfirst((string)($meuProjeto['area_tematica'] ?? 'Tecnologia')), ENT_QUOTES, 'UTF-8') ?></span>
                        <span><i class="fa-solid fa-cube me-1" style="color:#38BDF8;"></i><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)($meuProjeto['tipo'] ?? 'Startup'))), ENT_QUOTES, 'UTF-8') ?></span>
                        <span><i class="fa-regular fa-calendar me-1" style="color:#34D399;"></i>Registada em <?= date('d/m/Y', strtotime((string)$meuProjeto['criado_em'])) ?></span>
                        <?php if (!empty($meuProjeto['mentor_nome'])): ?>
                            <span><i class="fa-solid fa-user-tie me-1" style="color:#A78BFA;"></i>Mentor: <strong style="color:#FFF;"><?= htmlspecialchars((string)$meuProjeto['mentor_nome'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                        <?php endif; ?>
                    </div>

                    <!-- AÇÕES DIRETAS INTERATIVAS NO HERO -->
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="std-hero-btn std-hero-btn-amber" data-bs-toggle="modal" data-bs-target="#modalEditarProposta">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            <span>Editar Proposta &amp; Pitch</span>
                        </button>
                        <button type="button" class="std-hero-btn" data-bs-toggle="modal" data-bs-target="#modalUploadDoc">
                            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                            <span>Carregar Documento</span>
                        </button>
                        <button type="button" class="std-hero-btn" data-bs-toggle="modal" data-bs-target="#modalAddMembro">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            <span>Convidar Equipa</span>
                        </button>
                        <button type="button" class="std-hero-btn" onclick="ativarAbaWorkspace('tabSimulador')">
                            <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                            <span>Simulador da Comissão</span>
                        </button>
                        <button type="button" class="std-hero-btn" data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            <span>Nova Ideia</span>
                        </button>
                    </div>
                </div>

                <!-- TELEMETRIA EM TEMPO REAL DA STARTUP -->
                <div class="col-lg-5">
                    <div class="std-hero-glass">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #FBBF24;">
                                <i class="fa-solid fa-gauge-high me-1"></i> Telemetria Real do Dossiê
                            </span>
                            <span class="sp-code fw-bold" style="color: #FBBF24; font-size: 0.85rem;">
                                <i class="fa-solid fa-bolt me-1"></i><?= $pontosSP ?> SP
                            </span>
                        </div>

                        <!-- Barra 1: Prontidão do Dossiê (X/4) -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.78rem;">
                                <span style="color: #E2E8F0; font-weight: 600;">Prontidão do Dossiê Técnico</span>
                                <span class="sp-code fw-bold" style="color: <?= $pctProntidao === 100 ? '#34D399' : '#FBBF24' ?>;"><?= $itensProntos ?>/4 (<?= $pctProntidao ?>%)</span>
                            </div>
                            <div style="height: 8px; background: rgba(255,255,255,0.12); border-radius: 999px; overflow: hidden;">
                                <div style="width: <?= $pctProntidao ?>%; height: 100%; background: linear-gradient(90deg, #F59E0B, #10B981); border-radius: 999px; transition: width 0.4s ease;"></div>
                            </div>
                        </div>

                        <!-- Barra 2: Pareceres da Comissão (X/3) ou Metas da Fase (X/Y) -->
                        <?php if ($emFaseTriagem): ?>
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.78rem;">
                                    <span style="color: #E2E8F0; font-weight: 600;">Pareceres da Comissão Técnica</span>
                                    <span class="sp-code fw-bold" style="color: #38BDF8;"><?= $numAvalConcl ?>/3 concluídos (<?= $pctAvaliacao ?>%)</span>
                                </div>
                                <div style="height: 8px; background: rgba(255,255,255,0.12); border-radius: 999px; overflow: hidden;">
                                    <div style="width: <?= max(6, $pctAvaliacao) ?>%; height: 100%; background: linear-gradient(90deg, #38BDF8, #6366F1); border-radius: 999px; transition: width 0.4s ease;"></div>
                                </div>
                                <div style="font-size: 0.71rem; color: #94A3B8; margin-top: 6px;">
                                    <?= $numAvalAtrib ?> de 3 avaliadores atribuídos · Consolidação automática ao 3.º parecer.
                                </div>
                            </div>
                        <?php else: ?>
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.78rem;">
                                    <span style="color: #E2E8F0; font-weight: 600;">Metas da Fase (<?= htmlspecialchars($faseMeta['lbl'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                    <span class="sp-code fw-bold" style="color: #34D399;"><?= $metasValidadasCount ?>/<?= $totalMetasFase ?> validadas (<?= $pctMetasFase ?>%)</span>
                                </div>
                                <div style="height: 8px; background: rgba(255,255,255,0.12); border-radius: 999px; overflow: hidden;">
                                    <div style="width: <?= max(6, $pctMetasFase) ?>%; height: 100%; background: linear-gradient(90deg, #10B981, #3B82F6); border-radius: 999px; transition: width 0.4s ease;"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════════════
             2. 4 KPI CARDS INTERATIVOS (CLICÁVEIS)
        ═══════════════════════════════════════════════════ -->
        <div class="kpi-grid mb-4">
            <div class="kpi-card amber std-kpi-interactive" onclick="document.getElementById('secJornada').scrollIntoView({behavior:'smooth'})" title="Clique para inspecionar o Mapa de Maturidade">
                <div class="kpi-top">
                    <span class="kpi-label">Maturidade &amp; Pontos</span>
                    <div class="kpi-icon amber"><i class="fa-solid fa-bolt"></i></div>
                </div>
                <div class="kpi-value sp-num"><?= $pontosSP ?> <small style="font-size:0.9rem; font-weight:700; color:var(--sp-accent);">SP</small></div>
                <div class="kpi-footer">
                    <span class="kpi-badge up">Etapa <?= (int)$faseMeta['num'] ?> de 5</span>
                    <span><?= htmlspecialchars($faseMeta['lbl'], ENT_QUOTES, 'UTF-8') ?> · Ver etapas →</span>
                </div>
            </div>

            <div class="kpi-card blue std-kpi-interactive" onclick="ativarAbaWorkspace('tabChecklist')" title="Clique para abrir o Diagnóstico e Checklist">
                <div class="kpi-top">
                    <span class="kpi-label"><?= $emFaseTriagem ? 'Comissão de Avaliação' : 'Metas da Fase Atual' ?></span>
                    <div class="kpi-icon blue"><i class="fa-solid <?= $emFaseTriagem ? 'fa-clipboard-check' : 'fa-flag-checkered' ?>"></i></div>
                </div>
                <?php if ($emFaseTriagem): ?>
                    <div class="kpi-value sp-num"><?= $numAvalConcl ?><small style="font-size:1rem; color:var(--text-muted);">/3</small></div>
                    <div class="kpi-footer">
                        <span class="kpi-badge <?= $numAvalConcl >= 3 ? 'up' : 'warn' ?>"><?= $numAvalAtrib ?>/3 atribuídos</span>
                        <span><?= !empty($meuProjeto['media_final']) ? 'Média: ' . number_format((float)$meuProjeto['media_final'], 1) . '/10' : 'Em apreciação' ?></span>
                    </div>
                <?php else: ?>
                    <div class="kpi-value sp-num"><?= $metasValidadasCount ?><small style="font-size:1rem; color:var(--text-muted);">/<?= $totalMetasFase ?></small></div>
                    <div class="kpi-footer">
                        <span class="kpi-badge up"><?= $pctMetasFase ?>% concluído</span>
                        <span><?= count($tarefas) ?> tarefa(s) do mentor</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="kpi-card green std-kpi-interactive" data-bs-toggle="modal" data-bs-target="#modalEditarProposta" title="Clique para editar o dossiê e proposta">
                <div class="kpi-top">
                    <span class="kpi-label">Prontidão do Dossiê</span>
                    <div class="kpi-icon green"><i class="fa-solid fa-shield-check"></i></div>
                </div>
                <div class="kpi-value sp-num"><?= $itensProntos ?><small style="font-size:1rem; color:var(--text-muted);">/4</small></div>
                <div class="kpi-footer">
                    <span class="kpi-badge <?= $itensProntos === 4 ? 'up' : 'warn' ?>"><?= $pctProntidao ?>% completo</span>
                    <span>Clique para melhorar dossiê →</span>
                </div>
            </div>

            <div class="kpi-card red std-kpi-interactive" onclick="ativarAbaWorkspace('tabEquipaDocs')" title="Clique para gerir equipa e documentos">
                <div class="kpi-top">
                    <span class="kpi-label">Equipa &amp; Repositório</span>
                    <div class="kpi-icon red"><i class="fa-solid fa-folder-tree"></i></div>
                </div>
                <div class="kpi-value sp-num"><?= count($membros) ?> <small style="font-size:0.9rem; color:var(--text-muted);">membro(s)</small></div>
                <div class="kpi-footer">
                    <span class="kpi-badge up"><?= count($documentos) + (!empty($meuProjeto['pitch_path']) ? 1 : 0) ?> doc(s)</span>
                    <span><?= $numReservas ?> reserva(s) de espaço</span>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════
             3. MAPA INTERATIVO DE MATURIDADE (CLICÁVEL POR FASE)
        ═══════════════════════════════════════════════════ -->
        <section class="std-journey-wrap" id="secJornada">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="sp-kicker" style="color: var(--sp-accent);">Ciclo Oficial de Incubação ISPSN</div>
                    <h2 class="fw-bold m-0" style="font-size: 1.08rem;">
                        <i class="fa-solid fa-compass me-1" style="color: var(--sp-accent);"></i>
                        Mapa Interativo da Jornada — <span style="font-weight: 500; font-size: 0.85rem; color: var(--sp-text-muted);">Clique em qualquer etapa abaixo para inspecionar os requisitos e metas</span>
                    </h2>
                </div>
                <span class="sp-chip sp-chip-warn">
                    <i class="fa-solid fa-hand-pointer"></i> Interativo
                </span>
            </div>

            <?php
            $jaIncubado = in_array($estKey, ['aprovado', 'incubado', 'fundo_investimento', 'concluido'], true);
            $numFaseAtual = (int)$faseMeta['num'];
            $etapasJornada = [
                'triagem' => [
                    'key'    => 'triagem',
                    'titulo' => '1. Admissão & Avaliação',
                    'sub'    => $jaIncubado ? 'Aprovado ✓' : ($numAvalConcl . '/3 pareceres'),
                    'icon'   => 'fa-clipboard-check',
                    'status' => $jaIncubado ? 'done' : 'current',
                    'desc'   => 'A Comissão Técnica (3 avaliadores independentes) analisa 8 dimensões da proposta. Média consolidada ≥ 7.0/10 (sem vetos de Inovação < 5 ou Sustentabilidade < 4) aprova o projeto para incubação.',
                    'metas'  => [
                        ['titulo' => 'Submissão do Problema, Solução e Resumo Executivo', 'pontos' => 0],
                        ['titulo' => 'Anexo do Pitch Deck ou Apresentação Técnica', 'pontos' => 0],
                        ['titulo' => 'Apreciação por 3 Avaliadores da Comissão Técnica', 'pontos' => 0],
                        ['titulo' => 'Assinatura Digital do Termo de Incubação', 'pontos' => 0],
                    ],
                ],
            ];
            foreach ($fasesOrdem as $fKey => $fInfo) {
                $stNode = 'pending';
                if ($estKey === 'concluido' || ($jaIncubado && $fInfo['num'] < $numFaseAtual)) {
                    $stNode = 'done';
                } elseif ($jaIncubado && $fInfo['num'] === $numFaseAtual) {
                    $stNode = 'current';
                } elseif (!$jaIncubado && $fKey === $faseKey) {
                    $stNode = 'pending';
                }
                $metasLista = [];
                foreach ($todasMetasPadrao[$fKey] ?? [] as $mpItem) {
                    $metasLista[] = [
                        'titulo' => $mpItem['titulo'] . ' — ' . ($mpItem['descricao'] ?? ''),
                        'pontos' => (int)($mpItem['peso_percentual'] ?? 0),
                    ];
                }
                if (empty($metasLista)) {
                    $metasLista[] = ['titulo' => $fInfo['desc'], 'pontos' => 50];
                }
                $etapasJornada[$fKey] = [
                    'key'    => $fKey,
                    'titulo' => ($fInfo['num'] + 1) . '. ' . $fInfo['lbl'],
                    'sub'    => $stNode === 'done' ? 'Concluída ✓' : ($stNode === 'current' ? 'Fase Atual' : '+50 SP ao avançar'),
                    'icon'   => $fInfo['icon'],
                    'status' => $stNode,
                    'desc'   => $fInfo['desc'],
                    'metas'  => $metasLista,
                ];
            }
            $etapaInicialKey = $jaIncubado ? $faseKey : 'triagem';
            ?>

            <div class="std-journey-track" role="tablist" aria-label="Etapas de maturidade da startup">
                <?php foreach ($etapasJornada as $eKey => $eInfo):
                    $clsNode = $eInfo['status'] === 'done' ? 'is-done' : ($eInfo['status'] === 'current' ? 'is-current' : '');
                    $clsSel  = ($eKey === $etapaInicialKey) ? 'is-selected' : '';
                ?>
                    <button type="button"
                            class="std-journey-node <?= $clsNode ?> <?= $clsSel ?>"
                            data-stage-key="<?= htmlspecialchars($eKey, ENT_QUOTES, 'UTF-8') ?>"
                            onclick="selecionarEtapaJornada('<?= htmlspecialchars($eKey, ENT_QUOTES, 'UTF-8') ?>', this)">
                        <div class="std-journey-circle">
                            <i class="fa-solid <?= $eInfo['status'] === 'done' ? 'fa-check' : htmlspecialchars($eInfo['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        </div>
                        <div class="std-journey-label"><?= htmlspecialchars($eInfo['titulo'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="std-journey-sub"><?= htmlspecialchars($eInfo['sub'], ENT_QUOTES, 'UTF-8') ?></div>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Gaveta Dinâmica de Inspecção da Etapa Selecionada -->
            <div class="std-journey-drawer" id="journeyDrawer">
                <?php $initStage = $etapasJornada[$etapaInicialKey]; ?>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <span class="sp-kicker" id="jdKicker" style="color: var(--sp-accent);">Etapa Selecionada</span>
                        <h3 class="fw-bold m-0" id="jdTitle" style="font-size: 1rem;"><?= htmlspecialchars($initStage['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                    </div>
                    <span class="sp-chip sp-chip-warn" id="jdBadge"><?= htmlspecialchars($initStage['sub'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <p class="mb-2" id="jdDesc" style="font-size: 0.85rem; color: var(--sp-text-sec);">
                    <?= htmlspecialchars($initStage['desc'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <div id="jdMetas" class="d-flex flex-wrap gap-2 mt-2">
                    <?php foreach ($initStage['metas'] as $mIt): ?>
                        <span class="sp-meta-pill">
                            <i class="fa-solid fa-circle-check"></i>
                            <span><?= htmlspecialchars($mIt['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($mIt['pontos'] > 0): ?>
                                <strong style="color:var(--sp-accent);">+<?= (int)$mIt['pontos'] ?> SP</strong>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════════════
             4. WORKSPACE INTERATIVO (COL-8) + RADAR & CHAT (COL-4)
        ═══════════════════════════════════════════════════ -->
        <div class="row g-4 align-items-start">

            <!-- COLUNA PRINCIPAL (8 COLUNAS): 4 ABAS DE ACÇÃO -->
            <div class="col-lg-8">
                <nav class="sp-tabs" role="tablist" aria-label="Ferramentas da Startup">
                    <button type="button" class="sp-tab-btn active" id="btn_tabChecklist" onclick="ativarAbaWorkspace('tabChecklist')">
                        <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                        <span>1. Checklist &amp; Metas</span>
                    </button>
                    <button type="button" class="sp-tab-btn" id="btn_tabPropostaIA" onclick="ativarAbaWorkspace('tabPropostaIA')">
                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        <span>2. Proposta &amp; Consultor IA</span>
                    </button>
                    <button type="button" class="sp-tab-btn" id="btn_tabEquipaDocs" onclick="ativarAbaWorkspace('tabEquipaDocs')">
                        <i class="fa-solid fa-people-group" aria-hidden="true"></i>
                        <span>3. Equipa &amp; Ficheiros (<?= count($membros) + count($documentos) ?>)</span>
                    </button>
                    <button type="button" class="sp-tab-btn" id="btn_tabSimulador" onclick="ativarAbaWorkspace('tabSimulador')">
                        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                        <span>4. Simulador da Comissão</span>
                    </button>
                </nav>

                <!-- ABA 1: CHECKLIST DO DOSSIÊ & METAS / TAREFAS -->
                <div id="tabChecklist" class="std-ws-pane">
                    <section class="sp-surface-primary">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge"><i class="fa-solid fa-clipboard-check"></i></span>
                                <div>
                                    <div class="sp-kicker">Ações Diretas do Empreendedor</div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Checklist Interativa de Prontidão do Dossiê</h2>
                                </div>
                            </div>
                            <span class="sp-chip <?= $itensProntos === 4 ? 'sp-chip-ok' : 'sp-chip-warn' ?> sp-num">
                                <?= $itensProntos ?>/4 concluídos (<?= $pctProntidao ?>%)
                            </span>
                        </div>

                        <div class="std-check-grid mb-4">
                            <?php foreach ($checklistDossie as $itemC): ?>
                                <div class="std-check-card <?= $itemC['ok'] ? 'done' : '' ?>">
                                    <div class="d-flex gap-2 align-items-start">
                                        <span style="width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; background:<?= $itemC['ok'] ? '#10B981' : 'rgba(217,119,6,0.16)' ?>; color:<?= $itemC['ok'] ? '#fff' : 'var(--sp-accent)' ?>;">
                                            <i class="fa-solid <?= $itemC['ok'] ? 'fa-check' : htmlspecialchars($itemC['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                        </span>
                                        <div>
                                            <div class="fw-bold" style="font-size: 0.85rem; color: var(--sp-text);">
                                                <?= htmlspecialchars($itemC['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div style="font-size: 0.74rem; color: var(--sp-text-muted); margin-top: 2px;">
                                                <?= htmlspecialchars($itemC['det'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="sp-btn-secondary py-1 px-2" style="min-height: 30px; font-size: 0.72rem; flex-shrink: 0;" data-bs-toggle="modal" data-bs-target="<?= $itemC['modal'] ?>">
                                        <i class="fa-solid <?= $itemC['ok'] ? 'fa-pen' : 'fa-plus' ?>"></i>
                                        <span><?= htmlspecialchars($itemC['btn'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Metas Oficiais da Fase Atual -->
                        <div class="pt-3 border-top" style="border-color: var(--sp-border) !important;">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <div class="sp-kicker">Roteiro da Fase <?= htmlspecialchars($faseMeta['lbl'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <h3 class="fw-bold m-0" style="font-size: 1rem;">Metas &amp; Entregáveis do Mentor</h3>
                                </div>
                                <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=<?= $idP ?><?= $userAmp ?>" class="sp-link">
                                    <span>Abrir gestão de metas completa</span>
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>

                            <?php if (!empty($tarefas)): ?>
                                <div class="d-flex flex-column gap-2 mb-3">
                                    <?php foreach ($tarefas as $t):
                                        $stT    = (string)($t['status'] ?? 'pendente');
                                        $valM   = !empty($t['validada_mentor']);
                                        $idTar  = (int)$t['id'];
                                        $chipCl = $valM ? 'sp-chip-ok' : ($stT === 'concluida' ? 'sp-chip-warn' : 'sp-chip-neutral');
                                        $chipTx = $valM ? 'Validada (+10 SP)' : ($stT === 'concluida' ? 'Aguarda Mentor' : ($stT === 'em_progresso' ? 'Em Progresso' : 'Pendente'));
                                    ?>
                                        <div class="p-3 rounded-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border);">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                <div>
                                                    <div class="fw-bold" style="font-size: 0.88rem;"><?= htmlspecialchars((string)$t['titulo'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    <?php if (!empty($t['descricao'])): ?>
                                                        <div style="font-size: 0.78rem; color: var(--sp-text-sec);"><?= htmlspecialchars((string)$t['descricao'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="sp-chip <?= $chipCl ?>"><?= $chipTx ?></span>
                                                    <?php if (!$valM): ?>
                                                        <button type="button" class="sp-btn-secondary py-1 px-2" style="min-height: 30px; font-size: 0.74rem;" data-bs-toggle="collapse" data-bs-target="#boxEvidPainel<?= $idTar ?>">
                                                            <i class="fa-solid fa-paper-plane"></i> Atualizar / Evidência
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if (!$valM): ?>
                                                <div class="collapse mt-3" id="boxEvidPainel<?= $idTar ?>">
                                                    <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" enctype="multipart/form-data" class="p-3 rounded-3" style="background: var(--sp-surface); border: 1px solid var(--sp-border);">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="atualizar_estado_tarefa">
                                                        <input type="hidden" name="id_tarefa" value="<?= $idTar ?>">
                                                        <input type="hidden" name="status" value="concluida">
                                                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($urlPainelAtual, ENT_QUOTES, 'UTF-8') ?>">
                                                        <div class="row g-2 align-items-end">
                                                            <div class="col-md-5">
                                                                <label class="form-label-custom" style="font-size:0.74rem;">Ficheiro de Evidência (PDF/ZIP/IMG)</label>
                                                                <input type="file" name="evidencia_ficheiro" class="form-control form-control-sm">
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label-custom" style="font-size:0.74rem;">Nota / Resultado Obtido</label>
                                                                <input type="text" name="evidencia_nota" class="form-control form-control-sm" placeholder="Resumo da entrega..." required>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <button type="submit" class="sp-btn-primary w-100 py-1" style="min-height:32px; font-size:0.75rem;">Enviar</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($metasFase)): ?>
                                <div class="row g-2">
                                    <?php foreach ($metasFase as $mf):
                                        $estM = (string)($mf['estado'] ?? 'inactiva');
                                        $chipM = $estM === 'concluida' ? 'sp-chip-ok' : ($estM === 'submetida' ? 'sp-chip-warn' : 'sp-chip-neutral');
                                    ?>
                                        <div class="col-md-6">
                                            <div class="p-3 rounded-3 h-100 d-flex flex-column justify-content-between" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border);">
                                                <div>
                                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                                        <span class="fw-bold" style="font-size: 0.84rem;">#<?= (int)$mf['numero'] ?> · <?= htmlspecialchars((string)$mf['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <span class="sp-chip <?= $chipM ?>">+<?= (int)$mf['pontos_meta'] ?> SP</span>
                                                    </div>
                                                    <div style="font-size: 0.76rem; color: var(--sp-text-sec);"><?= htmlspecialchars((string)$mf['descricao_padrao'], ENT_QUOTES, 'UTF-8') ?></div>
                                                </div>
                                                <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center" style="border-color: var(--sp-border) !important; font-size: 0.74rem;">
                                                    <span style="color: var(--sp-text-muted);">Estado: <strong><?= htmlspecialchars(ucfirst($estM), ENT_QUOTES, 'UTF-8') ?></strong></span>
                                                    <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=<?= $idP ?>&tab=execucao<?= $userAmp ?>" class="sp-link" style="font-size: 0.74rem;">Submeter entrega →</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <!-- ABA 2: PROPOSTA DE VALOR & CONSULTOR ESTRATÉGICO IA -->
                <div id="tabPropostaIA" class="std-ws-pane" style="display: none;">
                    <section class="sp-surface-primary">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                                <div>
                                    <div class="sp-kicker">Canvas &amp; Inteligência Artificial</div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Proposta de Valor &amp; Consultor IA (Gemini)</h2>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="sp-btn-secondary" data-bs-toggle="modal" data-bs-target="#modalEditarProposta">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    <span>Editar Proposta</span>
                                </button>
                                <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="gerar_analise_ia">
                                    <input type="hidden" name="id_projeto" value="<?= $idP ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($urlPainelAtual, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="sp-btn-primary">
                                        <i class="fa-solid fa-robot"></i>
                                        <span><?= !empty($meuProjeto['feedback_ia']) ? 'Atualizar Diagnóstico IA' : 'Gerar Diagnóstico IA' ?></span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="p-3 rounded-3 mb-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border);">
                            <div class="sp-kicker mb-1">Resumo Executivo</div>
                            <p class="mb-0" style="font-size: 0.9rem; white-space: pre-line;"><?= htmlspecialchars((string)($meuProjeto['descricao'] ?? 'Sem descrição.'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 h-100" style="background: var(--sp-err-bg); border: 1px solid var(--sp-err-border);">
                                    <div class="sp-kicker mb-1" style="color: var(--sp-err-text);"><i class="fa-solid fa-triangle-exclamation me-1"></i> Problema que Resolve</div>
                                    <p class="mb-0" style="font-size: 0.86rem; white-space: pre-line;"><?= !empty($meuProjeto['problema']) ? htmlspecialchars((string)$meuProjeto['problema'], ENT_QUOTES, 'UTF-8') : 'Ainda não detalhado. Clique em Editar Proposta.' ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 h-100" style="background: var(--sp-ok-bg); border: 1px solid var(--sp-ok-border);">
                                    <div class="sp-kicker mb-1" style="color: var(--sp-ok-text);"><i class="fa-solid fa-lightbulb me-1"></i> Solução Proposta</div>
                                    <p class="mb-0" style="font-size: 0.86rem; white-space: pre-line;"><?= !empty($meuProjeto['solucao']) ? htmlspecialchars((string)$meuProjeto['solucao'], ENT_QUOTES, 'UTF-8') : 'Ainda não detalhada. Clique em Editar Proposta.' ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Diagnóstico do Consultor IA -->
                        <div class="p-4 rounded-3" style="background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(59,130,246,0.06)); border: 1px solid var(--sp-accent-border);">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                <div class="fw-bold" style="color: var(--sp-accent); font-size: 0.95rem;">
                                    <i class="fa-solid fa-brain me-1"></i> Parecer do Consultor Estratégico IA (ISPSN)
                                </div>
                                <span class="sp-chip sp-chip-warn">Gemini AI</span>
                            </div>
                            <?php if (!empty($meuProjeto['feedback_ia'])): ?>
                                <div style="font-size: 0.86rem; line-height: 1.65; color: var(--sp-text);">
                                    <?= nl2br(htmlspecialchars((string)$meuProjeto['feedback_ia'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            <?php else: ?>
                                <p class="mb-0" style="font-size: 0.85rem; color: var(--sp-text-sec);">
                                    Ainda não foi gerado um parecer estratégico por IA para esta versão da proposta. Clique no botão <strong>"Gerar Diagnóstico IA"</strong> acima para obter pontos fortes, riscos e sugestões práticas de melhoria.
                                </p>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <!-- ABA 3: EQUIPA & REPOSITÓRIO DE FICHEIROS -->
                <div id="tabEquipaDocs" class="std-ws-pane" style="display: none;">
                    <section class="sp-surface-primary">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge"><i class="fa-solid fa-people-group"></i></span>
                                <div>
                                    <div class="sp-kicker">Talento &amp; Documentação</div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Equipa Fundadora &amp; Repositório Técnico</h2>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="sp-btn-secondary" data-bs-toggle="modal" data-bs-target="#modalAddMembro">
                                    <i class="fa-solid fa-user-plus"></i>
                                    <span>Convidar Membro</span>
                                </button>
                                <button type="button" class="sp-btn-primary" data-bs-toggle="modal" data-bs-target="#modalUploadDoc">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span>Anexar Ficheiro</span>
                                </button>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="sp-kicker mb-2">Membros da Startup (<?= count($membros) ?>)</div>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($membros as $mb): ?>
                                        <div class="p-3 rounded-3 d-flex justify-content-between align-items-center" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border);">
                                            <div>
                                                <div class="fw-bold" style="font-size: 0.86rem;"><?= htmlspecialchars((string)$mb['nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <div style="font-size: 0.74rem; color: var(--sp-text-muted);"><?= htmlspecialchars((string)$mb['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                            </div>
                                            <span class="sp-chip <?= ($mb['papel'] ?? '') === 'Líder' ? 'sp-chip-warn' : 'sp-chip-neutral' ?>">
                                                <?= htmlspecialchars((string)$mb['papel'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="sp-kicker mb-2">Ficheiros &amp; Pitch Deck (<?= count($documentos) + (!empty($meuProjeto['pitch_path']) ? 1 : 0) ?>)</div>
                                <div class="d-flex flex-column gap-2">
                                    <?php if (!empty($meuProjeto['pitch_path'])): ?>
                                        <div class="p-3 rounded-3 d-flex justify-content-between align-items-center" style="background: var(--sp-warn-bg); border: 1px solid var(--sp-warn-border);">
                                            <div>
                                                <div class="fw-bold" style="font-size: 0.85rem;"><i class="fa-solid fa-file-powerpoint me-1" style="color:var(--sp-accent);"></i> Pitch Deck Oficial</div>
                                                <div style="font-size: 0.72rem; color: var(--sp-text-sec);">Apresentação principal da proposta</div>
                                            </div>
                                            <a href="/incubadora_ispsn/<?= htmlspecialchars((string)$meuProjeto['pitch_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="sp-btn-secondary py-1 px-2" style="min-height: 30px; font-size: 0.74rem;">
                                                <i class="fa-solid fa-download"></i> Abrir
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php foreach ($documentos as $doc): ?>
                                        <div class="p-3 rounded-3 d-flex justify-content-between align-items-center" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border);">
                                            <div>
                                                <div class="fw-bold" style="font-size: 0.84rem;"><?= htmlspecialchars((string)$doc['titulo'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <div style="font-size: 0.72rem; color: var(--sp-text-muted);"><?= htmlspecialchars((string)$doc['tipo'], ENT_QUOTES, 'UTF-8') ?> · <?= date('d/m/Y', strtotime((string)$doc['criado_em'])) ?></div>
                                            </div>
                                            <a href="/incubadora_ispsn/<?= htmlspecialchars((string)$doc['path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="sp-btn-secondary py-1 px-2" style="min-height: 30px; font-size: 0.74rem;">
                                                <i class="fa-solid fa-download"></i>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($meuProjeto['pitch_path']) && empty($documentos)): ?>
                                        <div class="sp-empty-line">
                                            <i class="fa-regular fa-folder-open"></i>
                                            <span>Nenhum ficheiro anexado. Carregue o seu Pitch Deck.</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- ABA 4: SIMULADOR INTERATIVO DE CRITÉRIOS DA COMISSÃO TÉCNICA -->
                <div id="tabSimulador" class="std-ws-pane" style="display: none;">
                    <section class="sp-surface-primary">
                        <div class="sp-card-head">
                            <div class="sp-card-head-title">
                                <span class="sp-icon-badge"><i class="fa-solid fa-sliders"></i></span>
                                <div>
                                    <div class="sp-kicker">Auto-Diagnóstico nos 8 Critérios Oficiais</div>
                                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Simulador de Avaliação da Comissão Técnica</h2>
                                </div>
                            </div>
                            <span class="sp-chip sp-chip-ok" id="simResultChip">Média Simulada: 7.0/10 (Aprovável)</span>
                        </div>

                        <p style="font-size: 0.84rem; color: var(--sp-text-sec); margin-bottom: 16px;">
                            Ajuste os cursores abaixo para simular como a Comissão Técnica de 3 avaliadores classifica o seu projeto nos <strong>8 critérios oficiais da Incubadora ISPSN</strong>. O gráfico Radar à direita atualiza em tempo real!
                            <br><small style="color: var(--sp-text-muted);">Regra Oficial: Média Consolidada ≥ 7.0 aprova para incubação. Nota de Inovação &lt; 5 ou Sustentabilidade &lt; 4 aciona veto de revisão.</small>
                        </p>

                        <?php
                        $criteriosConfig = [
                            ['key' => 'inovacao',         'lbl' => '1. Grau de Inovação (mín. 5)',       'val' => $mediasCriterios['inovacao']],
                            ['key' => 'viabilidade',      'lbl' => '2. Viabilidade Técnica',             'val' => $mediasCriterios['viabilidade']],
                            ['key' => 'impacto',          'lbl' => '3. Impacto Económico/Social',        'val' => $mediasCriterios['impacto']],
                            ['key' => 'equipa',           'lbl' => '4. Competência da Equipa',           'val' => $mediasCriterios['equipa']],
                            ['key' => 'sustentabilidade', 'lbl' => '5. Sustentabilidade (mín. 4)',       'val' => $mediasCriterios['sustentabilidade']],
                            ['key' => 'escalabilidade',   'lbl' => '6. Potencial de Escalabilidade',     'val' => $mediasCriterios['escalabilidade']],
                            ['key' => 'mercado',          'lbl' => '7. Potencial de Mercado',            'val' => $mediasCriterios['mercado']],
                            ['key' => 'proposta',         'lbl' => '8. Clareza da Proposta & Pitch',     'val' => $mediasCriterios['proposta']],
                        ];
                        ?>
                        <div class="row g-3">
                            <?php foreach ($criteriosConfig as $idxC => $crit): ?>
                                <div class="col-md-6">
                                    <div class="p-3 rounded-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border);">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label for="simRange_<?= $crit['key'] ?>" class="fw-bold" style="font-size: 0.8rem;">
                                                <?= htmlspecialchars($crit['lbl'], ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                            <span class="sp-code fw-bold" id="simVal_<?= $crit['key'] ?>" style="color: var(--sp-accent);">
                                                <?= number_format((float)$crit['val'], 0) ?>/10
                                            </span>
                                        </div>
                                        <input type="range"
                                               class="form-range sim-criterion-slider"
                                               id="simRange_<?= $crit['key'] ?>"
                                               data-idx="<?= $idxC ?>"
                                               data-key="<?= $crit['key'] ?>"
                                               min="1" max="10" step="1"
                                               value="<?= (int)round((float)$crit['val']) ?>"
                                               oninput="atualizarSimuladorComissao()">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

            </div>

            <!-- COLUNA LATERAL (4 COLUNAS): RADAR CHART.JS, MENTOR & CANAL DIRETO -->
            <div class="col-lg-4">

                <!-- GRÁFICO INTERATIVO CHART.JS (RADAR DOS 8 CRITÉRIOS) -->
                <div class="sp-surface-primary mb-4" style="padding: 18px 20px;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <div class="sp-kicker">Visualização Analítica</div>
                            <h3 class="fw-bold m-0" style="font-size: 0.98rem;">Radar de Maturidade (8 Eixos)</h3>
                        </div>
                        <span class="sp-chip <?= $temNotasReais ? 'sp-chip-ok' : 'sp-chip-warn' ?>">
                            <?= $temNotasReais ? 'Notas Oficiais' : 'Modo Simulador' ?>
                        </span>
                    </div>
                    <div style="position: relative; height: 235px; width: 100%;">
                        <canvas id="radarStartupChart"></canvas>
                    </div>
                    <div class="text-center mt-2" style="font-size: 0.74rem; color: var(--sp-text-muted);">
                        Use a aba <strong>4. Simulador da Comissão</strong> para testar cenários no gráfico.
                    </div>
                </div>

                <!-- MENTORIA, AGENDA & TERMO -->
                <aside class="sp-surface-aside mb-4">
                    <div class="sp-aside-block">
                        <span class="sp-aside-icon"><i class="fa-solid fa-user-tie"></i></span>
                        <div class="sp-aside-body">
                            <div class="sp-kicker mb-1">Mentor &amp; Acompanhamento</div>
                            <?php if (!empty($meuProjeto['mentor_nome'])): ?>
                                <div class="fw-bold" style="font-size: 0.88rem;"><?= htmlspecialchars((string)$meuProjeto['mentor_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="font-size: 0.75rem; color: var(--sp-text-muted);"><?= htmlspecialchars((string)$meuProjeto['mentor_email'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php else: ?>
                                <div style="font-size: 0.82rem; color: var(--sp-text-sec);">A atribuição de mentor ocorre após a aprovação técnica.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="sp-aside-block">
                        <span class="sp-aside-icon"><i class="fa-solid fa-file-contract"></i></span>
                        <div class="sp-aside-body">
                            <div class="sp-kicker mb-1">Termo de Incubação</div>
                            <?php if ($termoAtivo): ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="sp-chip <?= ($termoAtivo['estado'] ?? '') === 'assinado' ? 'sp-chip-ok' : 'sp-chip-warn' ?>">
                                        <?= ($termoAtivo['estado'] ?? '') === 'assinado' ? 'Assinado & Ativo' : 'Aguarda Assinatura' ?>
                                    </span>
                                    <a href="/incubadora_ispsn/app/views/admin/termo_incubacao.php?id_projeto=<?= $idP ?>" class="sp-link" style="font-size:0.78rem;">Abrir →</a>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.8rem; color: var(--sp-text-muted);">Emitido após aprovação pela Comissão.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="sp-aside-block">
                        <span class="sp-aside-icon"><i class="fa-solid fa-building-user"></i></span>
                        <div class="sp-aside-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="sp-kicker mb-1">Coworking &amp; Salas</div>
                                    <div class="fw-bold" style="font-size: 0.84rem;"><?= $numReservas ?> reserva(s) ativa(s)</div>
                                </div>
                                <a href="/incubadora_ispsn/app/views/utilizador/reservas.php<?= $userParam ?>" class="sp-btn-secondary py-1 px-2" style="min-height: 30px; font-size: 0.74rem;">
                                    Reservar →
                                </a>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- CANAL DIRETO COM MENTOR / COORDENAÇÃO -->
                <div class="sp-surface-primary" style="padding: 18px 20px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="sp-kicker">Comunicação Direta</div>
                            <h3 class="fw-bold m-0" style="font-size: 0.98rem;"><i class="fa-regular fa-comments me-1" style="color:var(--sp-accent);"></i> Canal da Startup</h3>
                        </div>
                        <span class="sp-chip sp-chip-neutral"><?= count($mensagens) ?> msg</span>
                    </div>

                    <div style="max-height: 210px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; padding-right: 4px;">
                        <?php if (empty($mensagens)): ?>
                            <div class="sp-empty-line" style="min-height: 44px; font-size: 0.78rem;">
                                <i class="fa-regular fa-comment-dots"></i>
                                <span>Envie uma mensagem ou dúvida para o mentor/coordenação.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($mensagens as $msg):
                                $souEu = ((int)$msg['id_usuario'] === (int)$idUsuario);
                                $dtMsg = $msg['criado_at'] ?? ($msg['enviado_em'] ?? '');
                            ?>
                                <div class="p-2 rounded-3" style="background: <?= $souEu ? 'var(--sp-warn-bg)' : 'var(--sp-surface-muted)' ?>; border: 1px solid <?= $souEu ? 'var(--sp-warn-border)' : 'var(--sp-border)' ?>; font-size: 0.8rem;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong style="font-size: 0.74rem;"><?= htmlspecialchars((string)$msg['autor_nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span style="font-size: 0.68rem; color: var(--sp-text-muted);"><?= $dtMsg ? date('d/m H:i', strtotime((string)$dtMsg)) : '' ?></span>
                                    </div>
                                    <div><?= nl2br(htmlspecialchars((string)$msg['mensagem'], ENT_QUOTES, 'UTF-8')) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="enviar_mensagem">
                        <input type="hidden" name="id_projeto" value="<?= $idP ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($urlPainelAtual, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="text" name="mensagem" class="form-control form-control-sm" placeholder="Escrever mensagem à mentoria..." required>
                        <button type="submit" class="sp-btn-primary py-1 px-3" style="min-height: 34px;">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </form>
                </div>

            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════
             MODAIS INTERATIVOS DA STARTUP (SEMPRE ATIVOS)
        ═══════════════════════════════════════════════════ -->

        <!-- MODAL 1: EDITAR PROPOSTA & PITCH -->
        <div class="modal fade" id="modalEditarProposta" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content modal-content-custom">
                    <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="editar_proposta">
                        <input type="hidden" name="id_projeto" value="<?= $idP ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($urlPainelAtual, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="modal-header-custom">
                            <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-pen-to-square me-2" style="color:var(--primary);"></i>Editar Proposta &amp; Dossiê Técnico</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body-custom">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label-custom">Nome da Startup / Projeto *</label>
                                    <input type="text" name="titulo" class="form-control-custom" required minlength="5" value="<?= htmlspecialchars((string)$meuProjeto['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Tipologia *</label>
                                    <select name="tipo" class="form-select form-control-custom" required>
                                        <?php
                                        $tiposMap = [
                                            'startup_tecnologica' => 'Startup Tecnológica',
                                            'negocio_tradicional' => 'Negócio Tradicional / Inovação',
                                            'impacto_social'      => 'Impacto Social / Ambiental',
                                            'outro'               => 'Outro',
                                        ];
                                        foreach ($tiposMap as $tk => $tl):
                                        ?>
                                            <option value="<?= $tk ?>" <?= ($meuProjeto['tipo'] ?? '') === $tk ? 'selected' : '' ?>><?= $tl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Área Temática *</label>
                                    <select name="area" class="form-select form-control-custom" required>
                                        <?php
                                        $areasMap = [
                                            'tecnologia' => 'Tecnologia & Sistemas',
                                            'saude'      => 'Saúde & Bem-Estar',
                                            'educacao'   => 'Educação',
                                            'agro'       => 'Agronegócio',
                                            'financas'   => 'Finanças / Fintech',
                                            'outro'      => 'Outra Área',
                                        ];
                                        foreach ($areasMap as $ak => $al):
                                        ?>
                                            <option value="<?= $ak ?>" <?= ($meuProjeto['area_tematica'] ?? '') === $ak ? 'selected' : '' ?>><?= $al ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label-custom">Resumo Executivo da Startup *</label>
                                    <textarea name="descricao" class="form-control-custom" rows="3" required minlength="20"><?= htmlspecialchars((string)($meuProjeto['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Problema que Resolve</label>
                                    <textarea name="problema" class="form-control-custom" rows="3" placeholder="Qual é a dor real do mercado/utilizador?"><?= htmlspecialchars((string)($meuProjeto['problema'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Solução &amp; Diferencial</label>
                                    <textarea name="solucao" class="form-control-custom" rows="3" placeholder="Como a sua tecnologia/produto resolve o problema?"><?= htmlspecialchars((string)($meuProjeto['solucao'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label-custom">Atualizar Pitch Deck (PDF, PPT, PPTX ou ZIP — máx. 15 MB)</label>
                                    <input type="file" name="pitch_ficheiro" class="form-control form-control-custom" accept=".pdf,.ppt,.pptx,.zip">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer-custom">
                            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL 2: UPLOAD DE DOCUMENTO TÉCNICO -->
        <div class="modal fade" id="modalUploadDoc" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-content-custom">
                    <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload_documento">
                        <input type="hidden" name="id_projeto" value="<?= $idP ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($urlPainelAtual, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="modal-header-custom">
                            <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-cloud-arrow-up me-2" style="color:var(--primary);"></i>Carregar Documento no Dossiê</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body-custom">
                            <div class="mb-3">
                                <label class="form-label-custom">Título do Documento *</label>
                                <input type="text" name="titulo" class="form-control-custom" placeholder="Ex: Business Model Canvas v2" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label-custom">Categoria *</label>
                                <select name="tipo" class="form-select form-control-custom" required>
                                    <option value="Pitch">Pitch Deck / Apresentação</option>
                                    <option value="Canvas">Business Model Canvas</option>
                                    <option value="Financeiro">Plano Financeiro</option>
                                    <option value="Prototipo">Evidência de MVP / Protótipo</option>
                                    <option value="Outro">Outro Documento Técnico</option>
                                </select>
                            </div>
                            <div class="mb-0">
                                <label class="form-label-custom">Ficheiro (PDF, Word, Excel, PPT, ZIP ou Imagem — máx. 10 MB) *</label>
                                <input type="file" name="ficheiro" class="form-control form-control-custom" required>
                            </div>
                        </div>
                        <div class="modal-footer-custom">
                            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-upload me-1"></i> Anexar ao Projeto</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL 3: CONVIDAR MEMBRO DE EQUIPA -->
        <div class="modal fade" id="modalAddMembro" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-content-custom">
                    <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="adicionar_membro">
                        <input type="hidden" name="id_projeto" value="<?= $idP ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($urlPainelAtual, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="modal-header-custom">
                            <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-user-plus me-2" style="color:var(--primary);"></i>Adicionar Membro à Equipa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body-custom">
                            <div class="mb-3">
                                <label class="form-label-custom">E-mail Institucional / Registado do Estudante *</label>
                                <input type="email" name="email" class="form-control-custom" placeholder="exemplo@estudante.ispsn.org" required>
                            </div>
                            <div class="mb-0">
                                <label class="form-label-custom">Papel na Startup *</label>
                                <select name="papel" class="form-select form-control-custom">
                                    <option value="Co-Fundador">Co-Fundador</option>
                                    <option value="CTO / Tecnologia">CTO / Desenvolvimento</option>
                                    <option value="Marketing & Vendas">Marketing &amp; Crescimento</option>
                                    <option value="Operações & Finanças">Operações &amp; Finanças</option>
                                    <option value="Membro">Membro Investigador</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer-custom">
                            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-check me-1"></i> Adicionar à Startup</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        const ETAPAS_JORNADA_DATA = <?= json_encode($etapasJornada, JSON_UNESCAPED_UNICODE) ?>;

        function selecionarEtapaJornada(stageKey, btnEl) {
            const data = ETAPAS_JORNADA_DATA[stageKey];
            if (!data) return;

            document.querySelectorAll('.std-journey-node').forEach(b => b.classList.remove('is-selected'));
            if (btnEl) btnEl.classList.add('is-selected');

            document.getElementById('jdTitle').textContent = data.titulo;
            document.getElementById('jdBadge').textContent = data.sub;
            document.getElementById('jdDesc').textContent  = data.desc;

            const metasWrap = document.getElementById('jdMetas');
            metasWrap.innerHTML = (data.metas || []).map(m => {
                const pts = m.pontos > 0 ? ` <strong style="color:var(--sp-accent);">+${m.pontos} SP</strong>` : '';
                return `<span class="sp-meta-pill"><i class="fa-solid fa-circle-check"></i><span>${m.titulo}</span>${pts}</span>`;
            }).join('');
        }

        function ativarAbaWorkspace(tabId) {
            document.querySelectorAll('.std-ws-pane').forEach(p => p.style.display = 'none');
            document.querySelectorAll('.sp-tab-btn').forEach(b => b.classList.remove('active'));

            const targetPane = document.getElementById(tabId);
            const targetBtn  = document.getElementById('btn_' + tabId);
            if (targetPane) targetPane.style.display = 'block';
            if (targetBtn)  targetBtn.classList.add('active');
        }

        let radarStartupInstance = null;
        document.addEventListener('DOMContentLoaded', () => {
            const ctx = document.getElementById('radarStartupChart');
            if (ctx && typeof Chart !== 'undefined') {
                const initialValues = [
                    <?= (float)$mediasCriterios['inovacao'] ?>,
                    <?= (float)$mediasCriterios['viabilidade'] ?>,
                    <?= (float)$mediasCriterios['impacto'] ?>,
                    <?= (float)$mediasCriterios['equipa'] ?>,
                    <?= (float)$mediasCriterios['sustentabilidade'] ?>,
                    <?= (float)$mediasCriterios['escalabilidade'] ?>,
                    <?= (float)$mediasCriterios['mercado'] ?>,
                    <?= (float)$mediasCriterios['proposta'] ?>
                ];
                radarStartupInstance = new Chart(ctx, {
                    type: 'radar',
                    data: {
                        labels: ['Inovação', 'Viabilidade', 'Impacto', 'Equipa', 'Sustentab.', 'Escalab.', 'Mercado', 'Proposta'],
                        datasets: [{
                            label: 'Pontuação (0-10)',
                            data: initialValues,
                            backgroundColor: 'rgba(245, 158, 11, 0.22)',
                            borderColor: '#D97706',
                            pointBackgroundColor: '#F59E0B',
                            pointBorderColor: '#fff',
                            borderWidth: 2
                        }, {
                            label: 'Limiar Aprovação (7.0)',
                            data: [7, 7, 7, 7, 7, 7, 7, 7],
                            backgroundColor: 'rgba(16, 185, 129, 0.05)',
                            borderColor: 'rgba(16, 185, 129, 0.45)',
                            borderDash: [4, 4],
                            pointRadius: 0,
                            borderWidth: 1.5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            r: {
                                min: 0,
                                max: 10,
                                ticks: { stepSize: 2, display: false },
                                pointLabels: { font: { size: 10, weight: '700' }, color: '#64748B' }
                            }
                        }
                    }
                });
                atualizarSimuladorComissao();
            }
        });

        function atualizarSimuladorComissao() {
            const sliders = document.querySelectorAll('.sim-criterion-slider');
            if (!sliders.length) return;
            const vals = [];
            let notaInov = 7;
            let notaSust = 7;

            sliders.forEach(sl => {
                const v = parseInt(sl.value, 10) || 0;
                const k = sl.getAttribute('data-key');
                vals.push(v);
                const lbl = document.getElementById('simVal_' + k);
                if (lbl) lbl.textContent = v + '/10';
                if (k === 'inovacao') notaInov = v;
                if (k === 'sustentabilidade') notaSust = v;
            });

            const avg = vals.reduce((a, b) => a + b, 0) / vals.length;
            const chip = document.getElementById('simResultChip');
            if (chip) {
                if (notaInov < 5 || notaSust < 4) {
                    chip.className = 'sp-chip sp-chip-err';
                    chip.textContent = `Média: ${avg.toFixed(1)}/10 · Veto Técnico (Inov < 5 ou Sust < 4)`;
                } else if (avg >= 7.0) {
                    chip.className = 'sp-chip sp-chip-ok';
                    chip.textContent = `Média Simulada: ${avg.toFixed(1)}/10 · Elegível para Incubação ✓`;
                } else if (avg >= 4.0) {
                    chip.className = 'sp-chip sp-chip-warn';
                    chip.textContent = `Média Simulada: ${avg.toFixed(1)}/10 · Revisão Necessária`;
                } else {
                    chip.className = 'sp-chip sp-chip-err';
                    chip.textContent = `Média Simulada: ${avg.toFixed(1)}/10 · Abaixo do Limiar`;
                }
            }

            if (radarStartupInstance) {
                radarStartupInstance.data.datasets[0].data = vals;
                radarStartupInstance.update();
            }
        }
        </script>
    <?php endif; ?>

    <!-- MODAL CRIAR NOVA IDEIA DE STARTUP (SEMPRE DISPONÍVEL) -->
    <div class="modal fade" id="modalNovoProjeto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content modal-content-custom">
                <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="criar_projeto">
                    <input type="hidden" name="id_usuario_alvo" value="<?= (int)$idUsuario ?>">
                    <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php<?= $userParam ?>">

                    <div class="modal-header-custom">
                        <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-rocket me-2" style="color:var(--primary);"></i>Submeter Nova Ideia de Startup</h5>
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
