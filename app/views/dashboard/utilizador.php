<?php
// app/views/dashboard/utilizador.php
// Painel Geral do Empreendedor — Design Nativo Alinhado ao Painel do Mentor/Admin

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
$paginaActiva = 'dashboard';

$userParam = $isAdminOrSuper ? ('?id_usuario=' . (int)$idUsuario) : '';
$userAmp   = $isAdminOrSuper ? ('&id_usuario=' . (int)$idUsuario) : '';

// 1. Listar projetos do estudante
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

// 2. Carregar projeto ativo
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

// 3. Dados resumidos para o Painel Geral
$mentorAtivo      = null;
$metasFase        = [];
$tarefasPendentes = [];
$proximasReunioes = [];
$totalSessoes     = 0;
$numAvalAtrib     = 0;
$numAvalConcl     = 0;
$ultimaMensagem   = null;

if ($meuProjeto) {
    $idP       = (int)$meuProjeto['id'];
    $faseAtual = (string)($meuProjeto['fase'] ?? 'ideacao');

    // Mentor atribuído e total de sessões
    $stmtMent = $mysqli->prepare("
        SELECT m.id AS id_mentoria, u.nome AS mentor_nome, u.email AS mentor_email, mt.especialidade,
               (SELECT COUNT(*) FROM sessoes_mentoria s WHERE s.id_mentoria = m.id) AS total_sessoes
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
        if ($mentorAtivo) {
            $totalSessoes = (int)($mentorAtivo['total_sessoes'] ?? 0);
        }
    }

    // Progresso da avaliação (3 avaliadores)
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

    // Metas da fase atual
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

    // Tarefas em aberto atribuídas pelo mentor
    $stmtT = $mysqli->prepare("
        SELECT *
        FROM tarefas
        WHERE id_projeto = ? AND COALESCE(validada_mentor, 0) = 0
        ORDER BY data_limite ASC
        LIMIT 6
    ");
    if ($stmtT) {
        $stmtT->bind_param('i', $idP);
        $stmtT->execute();
        $tarefasPendentes = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtT->close();
    }

    // Próximas reuniões agendadas (mesma query do mentor.php)
    $stmtR = $mysqli->prepare("
        SELECT r.*
        FROM reunioes r
        WHERE r.id_projeto = ? AND r.data_reuniao >= NOW() AND r.status = 'agendada'
        ORDER BY r.data_reuniao ASC
        LIMIT 4
    ");
    if ($stmtR) {
        $stmtR->bind_param('i', $idP);
        $stmtR->execute();
        $proximasReunioes = $stmtR->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtR->close();
    }

    // Última mensagem no chat
    $stmtMsg = $mysqli->prepare("
        SELECT m.mensagem, u.nome, u.perfil
        FROM mensagens m
        JOIN usuarios u ON u.id = m.id_usuario
        WHERE m.id_projeto = ?
        ORDER BY m.id DESC
        LIMIT 1
    ");
    if ($stmtMsg) {
        $stmtMsg->bind_param('i', $idP);
        $stmtMsg->execute();
        $ultimaMensagem = $stmtMsg->get_result()->fetch_assoc();
        $stmtMsg->close();
    }
}

$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
/* Estilos nativos alinhados ao Painel do Mentor (dashboard/mentor.php & mentor/projeto_detalhe.php) */
.std-dash-grid { display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start; }
@media (max-width: 1100px) { .std-dash-grid { grid-template-columns: 1fr; } }

.maturity-pipeline { padding: 8px 0; }
.pipeline-container { display: flex; align-items: center; justify-content: space-between; position: relative; }
.pipeline-step { display: flex; flex-direction: column; align-items: center; z-index: 2; flex: 1; text-decoration: none; }
.step-icon {
    width: 45px; height: 45px; border-radius: 50%;
    background: var(--surface-2); color: var(--text-muted);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; transition: all 0.3s;
    border: 3px solid var(--surface); box-shadow: 0 0 0 2px var(--border);
}
.step-label { font-size: 0.75rem; font-weight: 700; margin-top: 8px; color: var(--text-muted); }
.pipeline-line { flex: 1; height: 4px; background: var(--border); margin-top: -24px; z-index: 1; transition: all 0.3s; }
.pipeline-step.active .step-icon { background: var(--primary); color: #fff; transform: scale(1.12); box-shadow: 0 0 0 3px rgba(217,119,6,0.28); }
.pipeline-step.active .step-label { color: var(--primary); }
.pipeline-step.completed .step-icon { background: #10B981; color: #fff; box-shadow: 0 0 0 2px #10B981; }
.pipeline-step.completed .step-label { color: #10B981; }
.pipeline-line.completed { background: #10B981; }

.metas-banner {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px;
    background: linear-gradient(135deg, #FFFBEB, #FEF3C7);
    border-bottom: 1px solid #FDE68A;
}
body.dark-mode .metas-banner {
    background: linear-gradient(135deg, rgba(217,119,6,0.18), rgba(180,83,9,0.1));
    border-bottom-color: var(--border);
}
.metas-badge-count {
    background: #D97706; color: #fff;
    font-size: 0.72rem; font-weight: 800;
    min-width: 24px; height: 24px;
    border-radius: 12px; display: inline-flex;
    align-items: center; justify-content: center;
    padding: 0 8px;
}
.meta-item {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    transition: background var(--transition);
}
.meta-item:last-child { border-bottom: none; }
.meta-item:hover { background: var(--table-hover); }

.task-item {
    display: flex; gap: 12px; align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}
.task-item:last-child { border-bottom: none; }
.task-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 6px; flex-shrink: 0; }
.task-dot.alta  { background: #EF4444; }
.task-dot.media { background: #F59E0B; }
.task-dot.baixa { background: #10B981; }

.meeting-item {
    display: flex; gap: 14px; align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}
.meeting-item:last-child { border-bottom: none; }
.meeting-date-badge {
    flex-shrink: 0; width: 44px; min-height: 50px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: #fff; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    font-weight: 800; line-height: 1.1;
}
.meeting-date-badge .day   { font-size: 1.2rem; }
.meeting-date-badge .month { font-size: 0.62rem; text-transform: uppercase; opacity: .88; }
</style>

<!-- FLASH MESSAGES -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<!-- CABEÇALHO NATIVO DO SISTEMA (.page-header) -->
<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa fa-rocket me-2" style="color:var(--primary)"></i>
            Painel do Empreendedor
        </div>
        <div class="page-header-sub">
            Olá, <?= htmlspecialchars(explode(' ', $nomeEstudante)[0]) ?>. Bem-vindo ao centro de acompanhamento da sua startup.
        </div>
    </div>
    <div class="d-flex align-items-center flex-wrap gap-2">
        <?php if (count($listaProjetos) > 1 && $meuProjeto): ?>
            <form method="GET" class="m-0">
                <?php if ($isAdminOrSuper): ?>
                    <input type="hidden" name="id_usuario" value="<?= (int)$idUsuario ?>">
                <?php endif; ?>
                <select name="id_projeto" class="form-select" style="font-size:0.84rem; border-radius:9px; border-color:var(--border);" onchange="this.form.submit()">
                    <?php foreach ($listaProjetos as $lp): ?>
                        <option value="<?= (int)$lp['id'] ?>" <?= ((int)$lp['id'] === (int)$meuProjeto['id']) ? 'selected' : '' ?>>
                            Startup: <?= htmlspecialchars($lp['titulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
            <i class="fa fa-plus"></i> Submeter Nova Ideia
        </button>
    </div>
</div>

<?php if (!$meuProjeto): ?>
    <!-- ESTADO INICIAL: SEM STARTUP REGISTADA -->
    <div class="card-custom">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa fa-seedling" style="color:var(--primary)"></i></div>
            <div class="empty-state-title">Ainda não tem nenhuma startup registada</div>
            <div class="empty-state-text mb-4">
                Submeta a proposta da sua startup para iniciar a avaliação pela Comissão Técnica, receber mentoria e bater metas por fase.
            </div>
            <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
                <i class="fa fa-rocket"></i> Criar Minha Startup Agora
            </button>
        </div>
    </div>

<?php else:
    $idP          = (int)$meuProjeto['id'];
    $estadoProj   = (string)($meuProjeto['estado'] ?? 'submetido');
    $faseAtual    = (string)($meuProjeto['fase'] ?? 'ideacao');
    $pontosSP     = (int)($meuProjeto['pontos'] ?? 0);
    $metasConcl   = count(array_filter($metasFase, fn($m) => ($m['estado'] ?? '') === 'concluida'));
    $totalMetas   = count($metasFase);
    $emTriagem    = in_array($estadoProj, ['submetido', 'em_avaliacao', 'em_revisao', 'rejeitado'], true);

    $fasesLabels = [
        'ideacao'   => ['emoji' => '💡', 'label' => 'Ideação',   'icon' => 'lightbulb'],
        'validacao' => ['emoji' => '🔬', 'label' => 'Validação', 'icon' => 'vial'],
        'mvp'       => ['emoji' => '📦', 'label' => 'MVP',       'icon' => 'cube'],
        'tracao'    => ['emoji' => '📈', 'label' => 'Tração',    'icon' => 'chart-line'],
        'mercado'   => ['emoji' => '🛒', 'label' => 'Mercado',   'icon' => 'shop'],
    ];
    $faseInfo = $fasesLabels[$faseAtual] ?? $fasesLabels['ideacao'];

    $urlProposta   = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=' . $idP . '&sec=proposta'   . $userAmp;
    $urlMetas      = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=' . $idP . '&sec=metas'      . $userAmp;
    $urlMentoria   = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=' . $idP . '&sec=mentoria'   . $userAmp;
    $urlDocumentos = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=' . $idP . '&sec=documentos' . $userAmp;
?>

    <!-- 1. KPI CARDS NATIVOS (IDÊNTICOS AO PAINEL DO MENTOR) -->
    <div class="kpi-grid mb-4">
        <a href="<?= htmlspecialchars($urlProposta) ?>" class="kpi-card text-decoration-none" style="--kpi-color:var(--primary)">
            <div class="kpi-icon"><i class="fa fa-rocket"></i></div>
            <div class="kpi-value" style="font-size:1.55rem;"><?= $faseInfo['emoji'] ?> <?= $faseInfo['label'] ?></div>
            <div class="kpi-label">Fase Actual · <?= $pontosSP ?> SP</div>
            <div class="small text-muted mt-1" style="font-size:0.72rem;">Estado: <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $estadoProj))) ?></div>
        </a>

        <a href="<?= htmlspecialchars($urlMetas) ?>" class="kpi-card text-decoration-none" style="--kpi-color:#8B5CF6">
            <div class="kpi-icon"><i class="fa fa-bullseye"></i></div>
            <?php if ($emTriagem && $totalMetas === 0): ?>
                <div class="kpi-value"><?= $numAvalConcl ?><span style="font-size:1.1rem; font-weight:600; color:var(--text-muted)">/3</span></div>
                <div class="kpi-label">Pareceres da Comissão</div>
                <div class="small text-muted mt-1" style="font-size:0.72rem;"><?= $numAvalAtrib ?>/3 avaliadores atribuídos</div>
            <?php else: ?>
                <div class="kpi-value"><?= $metasConcl ?><span style="font-size:1.1rem; font-weight:600; color:var(--text-muted)">/<?= $totalMetas ?></span></div>
                <div class="kpi-label">Metas da Fase Validadas</div>
                <div class="small text-muted mt-1" style="font-size:0.72rem;">Clique para bater metas e enviar evidências</div>
            <?php endif; ?>
        </a>

        <a href="<?= htmlspecialchars($urlMetas) ?>" class="kpi-card text-decoration-none" style="--kpi-color:var(--warning)">
            <div class="kpi-icon"><i class="fa fa-list-check"></i></div>
            <div class="kpi-value"><?= count($tarefasPendentes) ?></div>
            <div class="kpi-label">Tarefas em Aberto</div>
            <div class="small text-muted mt-1" style="font-size:0.72rem;">Plano de ação com o mentor</div>
        </a>

        <a href="<?= htmlspecialchars($urlMentoria) ?>" class="kpi-card text-decoration-none" style="--kpi-color:var(--success)">
            <div class="kpi-icon"><i class="fa fa-comments"></i></div>
            <div class="kpi-value"><?= count($proximasReunioes) + $totalSessoes ?></div>
            <div class="kpi-label">Mentoria &amp; Sessões</div>
            <div class="small text-muted mt-1" style="font-size:0.72rem;"><?= $mentorAtivo ? 'Mentor: ' . htmlspecialchars($mentorAtivo['mentor_nome']) : 'Abrir canal de mentoria' ?></div>
        </a>
    </div>

    <!-- 2. TIMELINE DE MATURIDADE (IDÊNTICA A mentor/projeto_detalhe.php) -->
    <div class="card-custom mb-4">
        <div class="card-body-custom py-3 px-4">
            <div class="d-flex justify-content-between mb-3 align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0" style="font-size:1rem;">
                        <i class="fa fa-map-signs me-2" style="color:var(--primary)"></i>
                        Jornada da Startup: <span style="color:var(--primary)"><?= htmlspecialchars($meuProjeto['titulo']) ?></span>
                    </h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge-estado badge-<?= htmlspecialchars($estadoProj) ?>">
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $estadoProj))) ?>
                    </span>
                    <a href="<?= htmlspecialchars($urlProposta) ?>" class="btn-ghost" style="padding:4px 12px; font-size:0.76rem;">
                        <i class="fa fa-pen-to-square"></i> Gerir Startup
                    </a>
                </div>
            </div>

            <div class="maturity-pipeline">
                <?php
                $fasesKeys = array_keys($fasesLabels);
                $currentFaseIdx = array_search($faseAtual, $fasesKeys, true);
                if ($currentFaseIdx === false) $currentFaseIdx = 0;
                ?>
                <div class="pipeline-container">
                    <?php foreach ($fasesKeys as $idx => $fK):
                        $fItem  = $fasesLabels[$fK];
                        $status = ($idx < $currentFaseIdx) ? 'completed' : (($idx === $currentFaseIdx) ? 'active' : 'pending');
                    ?>
                        <a href="<?= htmlspecialchars($urlMetas) ?>" class="pipeline-step <?= $status ?>" title="Ver metas da fase <?= htmlspecialchars($fItem['label']) ?>">
                            <div class="step-icon"><i class="fa fa-<?= $fItem['icon'] ?>"></i></div>
                            <div class="step-label"><?= $fItem['label'] ?></div>
                        </a>
                        <?php if ($idx < 4): ?>
                            <div class="pipeline-line <?= ($idx < $currentFaseIdx) ? 'completed' : '' ?>"></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. GRELHA CENTRAL 2 COLUNAS (IGUAL AO PAINEL DO MENTOR) -->
    <div class="std-dash-grid">

        <!-- COLUNA PRINCIPAL -->
        <div style="min-width:0;">

            <!-- BANNER DE METAS & EVIDÊNCIAS DA FASE -->
            <div class="card-custom mb-4">
                <div class="metas-banner">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:36px;height:36px;border-radius:9px;background:rgba(217,119,6,.15);color:#D97706;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                            <i class="fa fa-bullseye"></i>
                        </div>
                        <div>
                            <div style="font-size:0.9rem;font-weight:700;color:#92400E;">
                                Metas &amp; Evidências — Fase de <?= htmlspecialchars($faseInfo['label']) ?>
                            </div>
                            <div style="font-size:0.75rem;color:#A16207;">
                                <?php if ($emTriagem && empty($metasFase)): ?>
                                    Proposta em apreciação técnica (<?= $numAvalConcl ?>/3 pareceres) · As metas desbloqueiam após assinatura do Termo
                                <?php else: ?>
                                    Submeta os comprovativos das metas ativas para validação do seu mentor
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <a href="<?= htmlspecialchars($urlMetas) ?>" class="btn-primary-custom" style="padding:6px 14px; font-size:0.76rem;">
                        <i class="fa fa-paper-plane"></i> Ir para Metas &amp; Evidências
                    </a>
                </div>

                <?php if (empty($metasFase)): ?>
                    <div class="card-body-custom">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <div class="fw-bold mb-1" style="font-size:0.9rem;">
                                    <?php if ($emTriagem): ?>
                                        A sua startup está na etapa de Avaliação Técnica (<?= $numAvalConcl ?> de 3 pareceres concluídos)
                                    <?php else: ?>
                                        A aguardar ativação das metas da fase de <?= htmlspecialchars($faseInfo['label']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small">
                                    Consulte no menu <strong>Metas &amp; Evidências</strong> todas as metas previstas para a fase de <?= htmlspecialchars($faseInfo['label']) ?> e prepare os seus entregáveis.
                                </div>
                            </div>
                            <a href="<?= htmlspecialchars($urlMetas) ?>" class="btn-ghost" style="font-size:0.8rem;">
                                Ver roteiro de metas &rarr;
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($metasFase as $mf):
                        $estM = (string)($mf['estado'] ?? 'inactiva');
                        $badgeStyle = 'background:#F1F5F9; color:#64748B;';
                        $badgeTexto = 'Inativa';
                        if ($estM === 'concluida') {
                            $badgeStyle = 'background:#D1FAE5; color:#065F46;';
                            $badgeTexto = 'Validada ✓';
                        } elseif ($estM === 'activa') {
                            $badgeStyle = 'background:#FEF3C7; color:#92400E;';
                            $badgeTexto = 'Ativa p/ Entrega';
                        } elseif ($estM === 'em_avaliacao') {
                            $badgeStyle = 'background:#DBEAFE; color:#1E40AF;';
                            $badgeTexto = 'Em Validação pelo Mentor';
                        } elseif ($estM === 'reprovada') {
                            $badgeStyle = 'background:#FEE2E2; color:#991B1B;';
                            $badgeTexto = 'Devolvida';
                        }
                    ?>
                        <div class="meta-item">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
                                <div style="flex:1; min-width:220px;">
                                    <div style="font-size:0.88rem; font-weight:700; color:var(--text-primary); margin-bottom:2px;">
                                        #<?= (int)$mf['numero'] ?>. <?= htmlspecialchars($mf['meta_titulo']) ?>
                                    </div>
                                    <div style="font-size:0.8rem; color:var(--text-secondary);">
                                        <?= htmlspecialchars($mf['meta_descricao']) ?>
                                    </div>
                                    <?php if (!empty($mf['feedback_mentor'])): ?>
                                        <div class="mt-1 text-danger" style="font-size:0.75rem;">
                                            <strong>Feedback do Mentor:</strong> <?= htmlspecialchars($mf['feedback_mentor']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge-estado" style="<?= $badgeStyle ?>"><?= $badgeTexto ?></span>
                                    <?php if ($estM === 'activa' || $estM === 'reprovada'): ?>
                                        <a href="<?= htmlspecialchars($urlMetas) ?>" class="btn-primary-custom" style="padding:6px 12px; font-size:0.75rem;">
                                            <i class="fa fa-upload"></i> Enviar Evidência
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- RESUMO DO PROJETO & ACESSO RÁPIDO ÀS SECÇÕES DO MENU -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-compass"></i> Áreas de Trabalho da Startup</div>
                    <span class="small text-muted">Organizadas no seu menu lateral</span>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="<?= htmlspecialchars($urlProposta) ?>" class="p-3 rounded-3 d-flex align-items-start gap-3 text-decoration-none h-100" style="background:var(--surface-2); border:1px solid var(--border);">
                                <div style="width:40px;height:40px;border-radius:10px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fa fa-rocket"></i>
                                </div>
                                <div>
                                    <div class="fw-bold" style="color:var(--text-primary); font-size:0.9rem;">Minha Startup &amp; Equipa</div>
                                    <div class="text-muted" style="font-size:0.78rem;">Editar problema, solução, equipa co-fundadora e diagnóstico IA.</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="<?= htmlspecialchars($urlMetas) ?>" class="p-3 rounded-3 d-flex align-items-start gap-3 text-decoration-none h-100" style="background:var(--surface-2); border:1px solid var(--border);">
                                <div style="width:40px;height:40px;border-radius:10px;background:#8B5CF6;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fa fa-bullseye"></i>
                                </div>
                                <div>
                                    <div class="fw-bold" style="color:var(--text-primary); font-size:0.9rem;">Metas &amp; Evidências</div>
                                    <div class="text-muted" style="font-size:0.78rem;">Bater metas da fase, submeter comprovativos e entregar tarefas do mentor.</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="<?= htmlspecialchars($urlMentoria) ?>" class="p-3 rounded-3 d-flex align-items-start gap-3 text-decoration-none h-100" style="background:var(--surface-2); border:1px solid var(--border);">
                                <div style="width:40px;height:40px;border-radius:10px;background:#10B981;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fa fa-comments"></i>
                                </div>
                                <div>
                                    <div class="fw-bold" style="color:var(--text-primary); font-size:0.9rem;">Mentoria &amp; Chat Direto</div>
                                    <div class="text-muted" style="font-size:0.78rem;">Conversar em tempo real com o mentor, ver reuniões e sessões realizadas.</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="<?= htmlspecialchars($urlDocumentos) ?>" class="p-3 rounded-3 d-flex align-items-start gap-3 text-decoration-none h-100" style="background:var(--surface-2); border:1px solid var(--border);">
                                <div style="width:40px;height:40px;border-radius:10px;background:#3B82F6;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fa fa-folder-open"></i>
                                </div>
                                <div>
                                    <div class="fw-bold" style="color:var(--text-primary); font-size:0.9rem;">Doc Hub &amp; Termo</div>
                                    <div class="text-muted" style="font-size:0.78rem;">Carregar Pitch Deck, ficheiros técnicos e consultar o Termo de Incubação.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- COLUNA LATERAL (360px — IGUAL AO PAINEL DO MENTOR) -->
        <div style="display:flex; flex-direction:column; gap:24px;">

            <!-- CARTÃO DO MENTOR & CHAT RÁPIDO -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-comments"></i> Mentoria &amp; Canal Direto</div>
                </div>
                <div class="card-body-custom" style="padding:18px 20px;">
                    <?php if ($mentorAtivo): ?>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div style="width:42px;height:42px;border-radius:50%;background:var(--primary);color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <?= strtoupper(substr($mentorAtivo['mentor_nome'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-bold" style="font-size:0.9rem;"><?= htmlspecialchars($mentorAtivo['mentor_nome']) ?></div>
                                <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($mentorAtivo['especialidade'] ?? 'Mentor Designado') ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-2 px-3 rounded-3 mb-3" style="background:var(--surface-2); font-size:0.8rem; color:var(--text-secondary);">
                            <i class="fa fa-user-tie me-1" style="color:var(--primary)"></i>
                            <?= $emTriagem ? 'O mentor é atribuído após a aprovação técnica.' : 'Pode usar o chat para comunicar com a Coordenação.' ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($ultimaMensagem): ?>
                        <div class="p-2 px-3 rounded-3 mb-3" style="background:var(--surface-2); border-left:3px solid var(--primary); font-size:0.8rem;">
                            <div class="fw-bold" style="font-size:0.72rem; color:var(--primary);"><?= htmlspecialchars($ultimaMensagem['nome']) ?>:</div>
                            <div class="text-truncate"><?= htmlspecialchars($ultimaMensagem['mensagem']) ?></div>
                        </div>
                    <?php endif; ?>

                    <a href="<?= htmlspecialchars($urlMentoria) ?>" class="btn-primary-custom w-100 justify-content-center" style="font-size:0.82rem;">
                        <i class="fa fa-comments"></i> Abrir Chat &amp; Mentoria
                    </a>
                </div>
            </div>

            <!-- TAREFAS PENDENTES DO MENTOR -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-clipboard-list"></i> Tarefas do Mentor</div>
                    <?php if (!empty($tarefasPendentes)): ?>
                        <span class="metas-badge-count" style="background:var(--warning);"><?= count($tarefasPendentes) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body-custom" style="padding:16px 20px;">
                    <?php if (empty($tarefasPendentes)): ?>
                        <div style="text-align:center; padding:20px 0;">
                            <i class="fa fa-check-circle" style="font-size:1.7rem; color:var(--success); margin-bottom:6px; display:block;"></i>
                            <p style="font-size:0.82rem; color:var(--text-muted); margin:0;">Nenhuma tarefa pendente!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tarefasPendentes as $t):
                            $prio = $t['prioridade'] ?? 'media';
                        ?>
                            <div class="task-item">
                                <span class="task-dot <?= htmlspecialchars($prio) ?>"></span>
                                <div style="flex:1; min-width:0;">
                                    <div style="font-size:0.84rem; font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($t['titulo']) ?></div>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <small class="text-muted" style="font-size:0.72rem;">
                                            <i class="fa fa-clock me-1"></i><?= !empty($t['data_limite']) ? date('d/m/Y', strtotime($t['data_limite'])) : 'Sem prazo' ?>
                                        </small>
                                        <a href="<?= htmlspecialchars($urlMetas) ?>" style="font-size:0.74rem; color:var(--primary); font-weight:700; text-decoration:none;">Entregar &rarr;</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PRÓXIMAS REUNIÕES AGENDADAS -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-calendar-days"></i> Próximas Reuniões</div>
                    <?php if (!empty($proximasReunioes)): ?>
                        <span class="metas-badge-count" style="background:var(--info);"><?= count($proximasReunioes) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body-custom" style="padding:16px 20px;">
                    <?php if (empty($proximasReunioes)): ?>
                        <div style="text-align:center; padding:20px 0;">
                            <i class="fa fa-calendar-xmark" style="font-size:1.7rem; color:var(--border); margin-bottom:6px; display:block;"></i>
                            <p style="font-size:0.82rem; color:var(--text-muted); margin:0;">Nenhuma reunião marcada.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($proximasReunioes as $re): ?>
                            <div class="meeting-item">
                                <div class="meeting-date-badge">
                                    <span class="day"><?= date('d', strtotime($re['data_reuniao'])) ?></span>
                                    <span class="month"><?= date('M', strtotime($re['data_reuniao'])) ?></span>
                                </div>
                                <div style="flex:1; min-width:0;">
                                    <div style="font-size:0.85rem; font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($re['titulo']) ?></div>
                                    <div style="font-size:0.72rem; color:var(--text-muted);"><i class="fa fa-clock me-1"></i><?= date('H:i', strtotime($re['data_reuniao'])) ?></div>
                                    <?php if (!empty($re['link_reuniao'])): ?>
                                        <a href="<?= htmlspecialchars($re['link_reuniao']) ?>" target="_blank" class="btn-primary-custom mt-2" style="padding:4px 10px; font-size:0.72rem;">
                                            <i class="fa fa-video"></i> Entrar na Reunião
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

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
                    <h5 class="modal-title fw-bold m-0"><i class="fa fa-rocket me-2" style="color:var(--primary);"></i>Submeter Nova Proposta de Startup</h5>
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
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-paper-plane me-1"></i> Submeter para Avaliação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
