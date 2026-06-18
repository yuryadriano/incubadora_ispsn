<?php
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

obrigarLogin();

$idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
$nome = $_SESSION['usuario_nome'] ?? 'Utilizador';

/* ============================
   COMENTÁRIOS DO ORIENTADOR
   ============================ */

// salvar comentário
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['novo_comentario'], $_POST['id_projeto'])
) {
    $comentario = trim($_POST['novo_comentario']);
    $idProjeto  = (int)$_POST['id_projeto'];
    $fase       = $_POST['fase'] ?? 'em_analise';

    if (strlen($comentario) >= 5) {
        $stmt = $mysqli->prepare("
            INSERT INTO comentarios_projetos
            (id_projeto, id_usuario, comentario, fase)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiss",
            $idProjeto,
            $_SESSION['usuario_id'],
            $comentario,
            $fase
        );
        $stmt->execute();
        $stmt->close();
    }
}


/* SUBMISSÃO DE PROJECTO — gerida por projeto_action.php via POST directo */
// (flash messages são lidas abaixo na view)

/* CONTADORES */
$stmt = $mysqli->prepare("SELECT COUNT(*) total FROM projetos WHERE criado_por=?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$meusProjetos = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $mysqli->prepare("SELECT COUNT(*) total FROM projetos WHERE criado_por=? AND tipo='pfc'");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$minhasPfcs = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $mysqli->prepare("
    SELECT *
    FROM projetos
    WHERE criado_por=?
    ORDER BY criado_em DESC LIMIT 1
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$ultimoProjeto = $stmt->get_result()->fetch_assoc();
$stmt->close();

$comentarios = [];

if ($ultimoProjeto) {
    $stmt = $mysqli->prepare("
        SELECT c.comentario, c.fase, c.criado_em, u.nome
        FROM comentarios_projetos c
        JOIN usuarios u ON u.id = c.id_usuario
        WHERE c.id_projeto = (
            SELECT id FROM projetos
            WHERE criado_por = ?
            ORDER BY criado_em DESC LIMIT 1
        )
        ORDER BY c.criado_em DESC
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $comentarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}


$mapaProgresso = [
    'submetido'          => 10,
    'em_avaliacao'       => 30,
    'aprovado'           => 50,
    'incubado'           => 75,
    'fundo_investimento' => 90,
    'concluido'          => 100,
    'rejeitado'          => 0
];
$progresso = $ultimoProjeto ? ($mapaProgresso[$ultimoProjeto['estado']] ?? 10) : 0;

$labels = [
    'submetido'          => 'Submetido (Em Triagem)',
    'em_avaliacao'       => 'Em Avaliação Técnica 🔍',
    'aprovado'           => 'Aprovado para Incubação ✓',
    'rejeitado'          => 'Rejeitado ✗',
    'incubado'           => 'Em Incubação 🚀',
    'fundo_investimento' => 'Pronto p/ Investimento 💰',
    'concluido'          => 'Graduado ✨'
];

$corBadge = [
    'submetido'    => 'secondary',
    'em_avaliacao' => 'warning',
    'aprovado'     => 'success',
    'rejeitado'    => 'danger',
    'incubado'     => 'primary',
    'fundo_investimento' => 'success',
    'concluido'    => 'success'
];

/* --- NOVOS DADOS: FINANCIAMENTOS E SESSÕES --- */
$totalFinanciamento = 0;
$sessoesMentoria = [];
$meusFinanciamentos = [];
$meusKpis = [];
$minhasTarefas = [];
$minhasAvaliacosMentor = [];
$mediaAvaliacaoAdmin = ['media' => 0, 'total' => 0];
$ultimasAvaliacoesOficiais = [];

if ($ultimoProjeto) {
    // Buscar total aprovado para o projeto mais recente
    $stmt = $mysqli->prepare("
        SELECT SUM(montante_aprovado) as total 
        FROM financiamentos 
        WHERE id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        AND estado IN ('activo', 'concluido')
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $resFin = $stmt->get_result()->fetch_assoc();
    $totalFinanciamento = $resFin['total'] ?? 0;
    $stmt->close();

    // Buscar sessões de mentoria
    $stmt = $mysqli->prepare("
        SELECT s.*, u.nome as mentor_nome
        FROM sessoes_mentoria s
        JOIN mentorias m ON m.id = s.id_mentoria
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u ON u.id = mt.id_usuario
        WHERE m.id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        ORDER BY s.data_sessao DESC
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $sessoesMentoria = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Buscar Financiamentos Activos (para o modal de despesas)
    $stmt = $mysqli->prepare("SELECT id, fonte FROM financiamentos WHERE id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1) AND estado='activo'");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $meusFinanciamentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Buscar KPIs Activos (para o modal de KPIs)
    $stmt = $mysqli->prepare("SELECT id, nome, unidade FROM kpis WHERE id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1) AND activo=1");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $meusKpis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // --- NOVOS DADOS: TAREFAS E REUNIÕES ---
    $stmt = $mysqli->prepare("SELECT * FROM tarefas WHERE id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1) ORDER BY status ASC, data_limite ASC");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $minhasTarefas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $minhasReunioes = [];
    $stmt = $mysqli->prepare("
        SELECT r.*, u.nome as mentor_nome
        FROM reunioes r
        JOIN usuarios u ON u.id = r.id_mentor
        WHERE r.id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        AND r.data_reuniao >= NOW()
        AND r.status = 'agendada'
        ORDER BY r.data_reuniao ASC
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $minhasReunioes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $minhasAvaliacosMentor = [];
    $stmt = $mysqli->prepare("
        SELECT av.*, u.nome as mentor_nome
        FROM avaliacoes_mentor av
        JOIN mentores m ON m.id = av.id_mentor
        JOIN usuarios u ON u.id = m.id_usuario
        WHERE av.id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        ORDER BY av.criado_em DESC
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $minhasAvaliacosMentor = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Buscar média das avaliações oficiais (Admin) e observações recentes
    $mediaAvaliacaoAdmin = null;
    $ultimasAvaliacoesOficiais = [];
    $stmt = $mysqli->prepare("
        SELECT pontuacao_total, observacoes, avaliado_em 
        FROM avaliacoes 
        WHERE id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        ORDER BY avaliado_em DESC
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $resultAval = $stmt->get_result();
    $ultimasAvaliacoesOficiais = $resultAval->fetch_all(MYSQLI_ASSOC);
    
    $totalAval = count($ultimasAvaliacoesOficiais);
    $somaAval = array_sum(array_column($ultimasAvaliacoesOficiais, 'pontuacao_total'));
    $mediaAvaliacaoAdmin = [
        'media' => $totalAval > 0 ? $somaAval / $totalAval : 0,
        'total' => $totalAval
    ];
    $stmt->close();

    // ── BUSCAR DOCUMENTOS DO PROJETO ──
    $meusDocumentos = [];
    $stmtDoc = $mysqli->prepare("
        SELECT f.*, u.nome as quem_submeteu, u.perfil as perfil_submeteu
        FROM ficheiros_projeto f
        JOIN usuarios u ON u.id = f.id_usuario_up
        WHERE f.id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        ORDER BY f.criado_em DESC
    ");
    $stmtDoc->bind_param('i', $idUsuario);
    $stmtDoc->execute();
    $meusDocumentos = $stmtDoc->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtDoc->close();

    // ── BUSCAR ESPAÇOS DISPONÍVEIS E RESERVAS ──
    $espacosDisponiveis = $mysqli->query("SELECT * FROM espacos WHERE status = 'disponivel' ORDER BY tipo, nome")->fetch_all(MYSQLI_ASSOC);

    $minhasReservasPainel = [];
    $stmtRes = $mysqli->prepare("
        SELECT r.*, e.nome as espaco_nome, e.tipo as espaco_tipo 
        FROM reservas_espaco r 
        JOIN espacos e ON e.id = r.id_espaco 
        WHERE r.id_usuario = ? 
        ORDER BY r.data_reserva DESC, r.hora_inicio DESC
        LIMIT 4
    ");
    $stmtRes->bind_param('i', $idUsuario);
    $stmtRes->execute();
    $minhasReservasPainel = $stmtRes->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtRes->close();
}
?>
<?php
$tituloPagina = 'Meu Painel';
$paginaActiva = 'dashboard';
// Flash messages
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);
require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
    /* ── DESIGN TOKENS & CLASS GLASSMORPHISM ── */
    :root {
        --primary-glass: rgba(217, 119, 6, 0.08);
        --success-glass: rgba(16, 185, 129, 0.08);
        --info-glass: rgba(59, 130, 246, 0.08);
        --purple-glass: rgba(139, 92, 246, 0.08);
        --danger-glass: rgba(239, 68, 68, 0.08);
        --border-glass: rgba(253, 230, 138, 0.25);
    }

    .glass-card {
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        box-shadow: 0 8px 32px 0 rgba(139, 92, 246, 0.03);
        transition: transform var(--transition), box-shadow var(--transition);
        overflow: hidden;
    }
    .glass-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px 0 rgba(217, 119, 6, 0.06);
    }

    /* Cabeçalho Premium */
    .welcome-banner {
        background: linear-gradient(135deg, #1C1917 0%, #27272A 100%);
        border-radius: var(--radius-lg);
        padding: 35px 30px;
        color: #fff;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.05);
        box-shadow: var(--shadow-lg);
        margin-bottom: 28px;
    }
    .welcome-banner::before {
        content: '';
        position: absolute;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(217, 119, 6, 0.15) 0%, transparent 70%);
        top: -100px;
        right: -50px;
        z-index: 1;
    }
    .welcome-banner-content {
        position: relative;
        z-index: 2;
    }

    /* Stepper */
    .stepper-track-modern {
        display: flex;
        justify-content: space-between;
        position: relative;
        padding: 20px 0;
        margin-top: 10px;
    }
    .stepper-track-modern::before {
        content: '';
        position: absolute;
        top: 38px;
        left: 8%;
        right: 8%;
        height: 4px;
        background: #F3F4F6;
        z-index: 1;
        border-radius: 10px;
    }
    .stepper-track-modern-progress {
        position: absolute;
        top: 38px;
        left: 8%;
        height: 4px;
        background: linear-gradient(90deg, #10B981, var(--primary));
        z-index: 1;
        border-radius: 10px;
        transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .step-node {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
    }
    .step-icon-outer {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #fff;
        border: 3px solid #E5E7EB;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: #9CA3AF;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
    }
    .step-node.completed .step-icon-outer {
        background: #10B981;
        border-color: #10B981;
        color: #fff;
    }
    .step-node.active .step-icon-outer {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
        transform: scale(1.15);
        box-shadow: 0 0 0 5px rgba(217, 119, 6, 0.2);
    }
    .step-node-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: #9CA3AF;
        margin-top: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .step-node.completed .step-node-label { color: #10B981; }
    .step-node.active .step-node-label { color: var(--text-primary); }

    /* Interactive Checklist */
    .checklist-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .checklist-item {
        padding: 16px 20px;
        border-radius: var(--radius);
        background: #fff;
        border: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.2s ease;
    }
    .checklist-item:hover {
        border-color: var(--primary);
        background: #FAF7F2;
    }
    .checklist-content {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .checklist-checkbox {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 2px solid #CBD5E1;
        display: flex;
        align-items: center;
        justify-content: center;
        color: transparent;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .checklist-item.validated .checklist-checkbox {
        background: #10B981;
        border-color: #10B981;
        color: #fff;
    }
    .checklist-item.awaiting .checklist-checkbox {
        background: #F59E0B;
        border-color: #F59E0B;
        color: #fff;
    }
    .checklist-title {
        font-weight: 600;
        font-size: 0.92rem;
        color: var(--text-primary);
    }
    .checklist-desc {
        font-size: 0.78rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    /* Badges de Estado customizados */
    .badge-status-custom {
        font-size: 0.65rem;
        padding: 3px 8px;
        border-radius: 20px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .badge-status-pending { background: #E5E7EB; color: #4B5563; }
    .badge-status-progress { background: #DBEAFE; color: #1D4ED8; }
    .badge-status-awaiting { background: #FEF3C7; color: #D97706; }
    .badge-status-valid { background: #D1FAE5; color: #065F46; }

    /* Documents lists */
    .doc-tab-title {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-secondary);
        letter-spacing: 0.5px;
        margin-bottom: 10px;
    }

    /* Reservations custom widget */
    .space-room-card {
        border: 1px solid rgba(0,0,0,0.06);
        border-radius: 12px;
        padding: 12px;
        background: #FDFDFD;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .space-room-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: var(--primary-glass);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }
</style>

<!-- FLASH -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4 shadow-sm"><i class="fa fa-check-circle me-2"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4 shadow-sm"><i class="fa fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<!-- BANNER DE BOAS-VINDAS PREMIUM -->
<?php 
$horaAtual = (int)date('H');
$saudacao = 'Bem-vindo';
if ($horaAtual >= 5 && $horaAtual < 12) $saudacao = 'Bom dia';
elseif ($horaAtual >= 12 && $horaAtual < 18) $saudacao = 'Boa tarde';
else $saudacao = 'Boa noite';
?>
<div class="welcome-banner">
    <div class="welcome-banner-content d-flex justify-content-between align-items-center flex-wrap g-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-warning text-dark px-3 py-1.5 fw-bold rounded-pill" style="font-size:0.75rem">
                    <i class="fa fa-trophy me-1"></i> <?= $ultimoProjeto['pontos'] ?? 0 ?> SP (Startup Points)
                </span>
                <?php if($ultimoProjeto): ?>
                    <span class="badge bg-success px-3 py-1.5 fw-bold rounded-pill" style="font-size:0.75rem">
                        <i class="fa fa-circle-play me-1"></i> Fase: <?= strtoupper(str_replace('_', ' ', $ultimoProjeto['fase'] ?? 'ideacao')) ?>
                    </span>
                <?php endif; ?>
            </div>
            <h2 class="fw-800 mb-1" style="font-size:1.85rem; letter-spacing:-0.5px"><?= $saudacao ?>, <?= htmlspecialchars(explode(' ', $nome)[0]) ?>! 👋</h2>
            <p class="text-white-50 small mb-0">
                <?php if ($ultimoProjeto): ?>
                    Startup Activa: <strong><?= htmlspecialchars($ultimoProjeto['titulo']) ?></strong> · Progresso Global: <strong><?= $progresso ?>%</strong>
                <?php else: ?>
                    Bem-vindo à Incubadora Académica do ISPSN. Registe a sua ideia para começar a acelerar.
                <?php endif; ?>
            </p>
        </div>
        
        <div class="d-flex gap-2">
            <?php if ($ultimoProjeto): ?>
                <button class="btn btn-warning btn-sm fw-bold px-3 py-2 text-dark" style="border-radius:10px" data-bs-toggle="modal" data-bs-target="#modalNovaReservaDashboard">
                    <i class="fa fa-calendar-plus me-1"></i> Reservar Sala
                </button>
                <button class="btn btn-outline-light btn-sm fw-bold px-3 py-2" style="border-radius:10px" data-bs-toggle="modal" data-bs-target="#modalUploadDocumentoDashboard">
                    <i class="fa fa-upload me-1"></i> Mandar Documento
                </button>
            <?php else: ?>
                <button class="btn btn-warning btn-sm fw-bold px-3 py-2 text-dark" style="border-radius:10px" data-bs-toggle="modal" data-bs-target="#modalProjeto">
                    <i class="fa fa-plus me-1"></i> Submeter Ideia
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($ultimoProjeto): ?>
<!-- ── PIPELINE DE INOVAÇÃO & MATURIDADE ── -->
<div class="glass-card mb-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold mb-1"><i class="fa fa-road me-2 text-primary"></i>Pipeline de Evolução da Startup</h5>
            <p class="text-muted small mb-0">Cumpra as metas exigidas em cada estágio para subir de nível e aceder ao Fundo de Investimento.</p>
        </div>
        <div class="text-end">
            <div class="small fw-bold text-muted" style="font-size:0.65rem; text-transform:uppercase">Prontidão para Financiamento</div>
            <div class="h5 mb-0 fw-800 text-success"><?= $progresso ?>%</div>
        </div>
    </div>

    <!-- Progress Tracker -->
    <?php 
    $pipelineSteps = [
        ['id' => 'ideacao',          'label' => 'Ideação',      'icon' => 'lightbulb'],
        ['id' => 'validacao',       'label' => 'Validação',    'icon' => 'vial'],
        ['id' => 'mvp',             'label' => 'MVP',          'icon' => 'cube'],
        ['id' => 'tracao',          'label' => 'Tração',       'icon' => 'chart-line'],
        ['id' => 'mercado',         'label' => 'Mercado',      'icon' => 'shop'],
        ['id' => 'fundo_investimento', 'label' => 'Financiado',  'icon' => 'sack-dollar']
    ];
    $currentFase = $ultimoProjeto['fase'] ?? 'ideacao';
    $currentIdx = 0;
    foreach($pipelineSteps as $idx => $step) {
        if($currentFase == $step['id']) {
            $currentIdx = $idx;
        }
    }
    // Calcular percentagem da barra
    $progressPct = ($currentIdx / (count($pipelineSteps) - 1)) * 84 + 8;
    ?>
    <div class="position-relative mb-2">
        <div class="stepper-track-modern">
            <div class="stepper-track-modern-progress" style="width: <?= $progressPct ?>%"></div>
            <?php foreach($pipelineSteps as $idx => $step): 
                $status = ($idx < $currentIdx) ? 'completed' : (($idx === $currentIdx) ? 'active' : 'pending');
            ?>
                <div class="step-node <?= $status ?>">
                    <div class="step-icon-outer">
                        <i class="fa fa-<?= $step['icon'] ?>"></i>
                    </div>
                    <div class="step-node-label"><?= $step['label'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- GRID CENTRAL -->
<div class="row g-4 mb-4">
    
    <!-- ESQUERDA: CHECKLIST INTERATIVO DE METAS (Metas) -->
    <div class="col-lg-7">
        <div class="glass-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-1"><i class="fa fa-list-check me-2 text-primary"></i>Metas & Objetivos de Incubação</h5>
                    <p class="text-muted small mb-0">Envie evidências do cumprimento das metas para ganhar pontos e avançar de fase.</p>
                </div>
                <span class="badge bg-light text-dark border px-2 py-1.5 small fw-bold">
                    <?= count(array_filter($minhasTarefas, fn($t) => $t['status'] === 'concluida' && $t['validada_mentor'] == 1)) ?> / <?= count($minhasTarefas) ?> Concluídas
                </span>
            </div>

            <?php if(empty($minhasTarefas)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa fa-clipboard-check fa-3x mb-3 text-black-50" style="opacity:0.2"></i>
                    <p class="mb-0 small">O seu mentor ainda não atribuiu metas ao seu plano de ação.</p>
                </div>
            <?php else: ?>
                <div class="checklist-container">
                    <?php foreach($minhasTarefas as $t): 
                        $statusClass = 'pending';
                        $statusText = 'Pendente';
                        $statusBadge = 'badge-status-pending';
                        
                        if ($t['status'] === 'concluida') {
                            if ($t['validada_mentor'] == 1) {
                                $statusClass = 'validated';
                                $statusText = 'Validada';
                                $statusBadge = 'badge-status-valid';
                            } else {
                                $statusClass = 'awaiting';
                                $statusText = 'Aguardando Validação';
                                $statusBadge = 'badge-status-awaiting';
                            }
                        } elseif ($t['status'] === 'em_progresso') {
                            $statusClass = 'progress';
                            $statusText = 'Em Progresso';
                            $statusBadge = 'badge-status-progress';
                        }
                    ?>
                        <div class="checklist-item <?= $statusClass ?>">
                            <div class="checklist-content">
                                <div class="checklist-checkbox">
                                    <i class="fa fa-<?= $statusClass === 'validated' ? 'check' : ($statusClass === 'awaiting' ? 'hourglass-half' : '') ?>"></i>
                                </div>
                                <div>
                                    <div class="checklist-title"><?= htmlspecialchars($t['titulo']) ?></div>
                                    <div class="checklist-desc"><?= htmlspecialchars($t['descricao']) ?></div>
                                    <?php if($t['data_limite']): ?>
                                        <div class="x-small text-muted mt-1">
                                            <i class="fa fa-calendar-alt me-1"></i> Limite: <?= date('d/m/Y', strtotime($t['data_limite'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge-status-custom <?= $statusBadge ?>">
                                    <?= $statusText ?>
                                </span>

                                <?php if ($statusClass === 'pending'): ?>
                                    <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" style="margin:0">
                                        <input type="hidden" name="action" value="atualizar_estado_tarefa">
                                        <input type="hidden" name="id_tarefa" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="status" value="em_progresso">
                                        <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
                                        <button type="submit" class="btn btn-sm btn-ghost" title="Iniciar meta">
                                            Começar
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($statusClass === 'pending' || $statusClass === 'progress'): ?>
                                    <button class="btn btn-sm btn-primary-custom" style="padding: 5px 10px; font-size:0.75rem" data-bs-toggle="modal" data-bs-target="#modalEvidencia_<?= $t['id'] ?>">
                                        Anexar Evidência
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- MODAL ENVIAR EVIDÊNCIA -->
                        <div class="modal fade" id="modalEvidencia_<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content modal-content-custom">
                                    <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="atualizar_estado_tarefa">
                                        <input type="hidden" name="id_tarefa" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="status" value="concluida">
                                        <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
                                        
                                        <div class="modal-header-custom">
                                            <h5 class="modal-title fw-bold"><i class="fa fa-envelope-open-text me-2"></i> Submeter Evidência de Meta</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body-custom">
                                            <div class="mb-3 p-3 bg-light rounded border-start border-4 border-warning">
                                                <strong>Meta:</strong> <?= htmlspecialchars($t['titulo']) ?>
                                                <div class="small text-muted mt-1"><?= htmlspecialchars($t['descricao']) ?></div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label-custom">Descrição / Notas de Conclusão *</label>
                                                <textarea name="evidencia_nota" class="form-control-custom" rows="3" required placeholder="Descreva sucintamente como cumpriu esta meta (ex: link do site no ar, funcionalidades integradas, etc.)."></textarea>
                                            </div>

                                            <div class="mb-0">
                                                <label class="form-label-custom">Anexo Comprovativo (PDF, Imagem, ZIP) *</label>
                                                <input type="file" name="evidencia_ficheiro" class="form-control-custom" required>
                                                <small class="text-muted d-block mt-1">Carregue um arquivo contendo a evidência do desenvolvimento (Pitch, Mockup, Relatório).</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer-custom">
                                            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn-primary-custom"><i class="fa fa-upload"></i> Entregar Meta</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DIREITA: DOCUMENTOS (Enviar/Receber) -->
    <div class="col-lg-5">
        <div class="glass-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-1"><i class="fa fa-folder-open me-2 text-primary"></i>Repositório de Documentos</h5>
                    <p class="text-muted small mb-0">Partilhe relatórios e receba materiais didáticos ou contratos.</p>
                </div>
                <button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#modalUploadDocumentoDashboard">
                    <i class="fa fa-plus"></i>
                </button>
            </div>

            <?php if(empty($meusDocumentos)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa fa-file-arrow-up fa-3x mb-3 text-black-50" style="opacity:0.2"></i>
                    <p class="mb-0 small">Ainda não partilhou documentos neste projeto.</p>
                </div>
            <?php else: ?>
                <!-- Documentos Recebidos (Incubadora/Mentor) -->
                <div class="doc-tab-title">Recebidos da Incubadora / Mentor</div>
                <div class="list-group list-group-flush mb-4" style="max-height: 200px; overflow-y: auto;">
                    <?php 
                    $recebidos = array_filter($meusDocumentos, fn($d) => $d['perfil_submeteu'] !== 'utilizador');
                    if(empty($recebidos)):
                    ?>
                        <div class="text-center py-3 text-muted small">Nenhum documento recebido.</div>
                    <?php else: ?>
                        <?php foreach($recebidos as $d): ?>
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center" style="background:transparent;border-color:rgba(0,0,0,0.04)">
                                <div>
                                    <div class="fw-bold text-truncate" style="font-size:0.82rem; max-width: 220px;"><?= htmlspecialchars($d['titulo']) ?></div>
                                    <small class="text-muted" style="font-size:0.7rem">Por: <?= htmlspecialchars($d['quem_submeteu']) ?> · <?= date('d/m/Y', strtotime($d['criado_em'])) ?></small>
                                </div>
                                <a href="/incubadora_ispsn/<?= $d['path'] ?>" target="_blank" class="btn btn-sm btn-ghost text-primary p-2">
                                    <i class="fa fa-download"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Documentos Enviados (Startup) -->
                <div class="doc-tab-title">Submetidos pela nossa Equipa</div>
                <div class="list-group list-group-flush" style="max-height: 200px; overflow-y: auto;">
                    <?php 
                    $enviados = array_filter($meusDocumentos, fn($d) => $d['perfil_submeteu'] === 'utilizador');
                    if(empty($enviados)):
                    ?>
                        <div class="text-center py-3 text-muted small">Nenhum documento submetido.</div>
                    <?php else: ?>
                        <?php foreach($enviados as $d): ?>
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center" style="background:transparent;border-color:rgba(0,0,0,0.04)">
                                <div>
                                    <div class="fw-bold text-truncate" style="font-size:0.82rem; max-width: 220px;"><?= htmlspecialchars($d['titulo']) ?></div>
                                    <small class="text-muted" style="font-size:0.7rem">Ficheiro: <?= $d['tipo'] ?> · <?= date('d/m/Y', strtotime($d['criado_em'])) ?></small>
                                </div>
                                <a href="/incubadora_ispsn/<?= $d['path'] ?>" target="_blank" class="btn btn-sm btn-ghost text-primary p-2">
                                    <i class="fa fa-download"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ABAIXO: ESPAÇOS FÍSICOS & RESERVAS DA INCUBADORA -->
<div class="row g-4 mb-4">
    <!-- WIDGET DE RESERVAS DE ESPAÇO -->
    <div class="col-lg-8">
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-1"><i class="fa fa-bookmark me-2 text-primary"></i>Reserva de Espaço Físico (Coworking & Salas)</h5>
                    <p class="text-muted small mb-0">Disponibilizamos salas de reuniões e espaço de coworking para as suas atividades operacionais.</p>
                </div>
                <button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovaReservaDashboard">
                    <i class="fa fa-calendar-plus me-1"></i> Nova Reserva
                </button>
            </div>

            <!-- Listagem de reservas ativas -->
            <div class="table-responsive">
                <table class="table table-sm align-middle" style="font-size: 0.85rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #F3F4F6;">
                            <th>Sala / Espaço</th>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($minhasReservasPainel)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">Não possui nenhuma reserva pendente ou ativa.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($minhasReservasPainel as $r): 
                                $statusClass = ($r['status'] == 'confirmada' ? 'success' : ($r['status'] == 'pendente' ? 'warning' : 'danger'));
                            ?>
                                <tr style="border-bottom: 1px solid rgba(0,0,0,0.02);">
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($r['espaco_nome']) ?></div>
                                        <small class="text-muted" style="font-size:0.7rem;"><?= ucfirst($r['espaco_tipo']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($r['data_reserva'])) ?></td>
                                    <td><?= substr($r['hora_inicio'], 0, 5) ?> - <?= substr($r['hora_fim'], 0, 5) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $statusClass ?>-subtle text-<?= $statusClass ?> px-3 py-1.5 rounded-pill" style="font-size:0.65rem">
                                            <?= strtoupper($r['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($r['status'] == 'pendente'): ?>
                                            <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST" onsubmit="return confirm('Cancelar esta reserva?')" style="margin:0">
                                                <input type="hidden" name="action" value="gestao_reserva">
                                                <input type="hidden" name="id_reserva" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="novo_status" value="cancelada">
                                                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="fa fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- REGRAS DE ESPAÇOS E SALAS DISPONÍVEIS -->
    <div class="col-lg-4">
        <div class="glass-card h-100 p-4">
            <h5 class="fw-bold mb-3"><i class="fa fa-circle-info me-2 text-primary"></i>Salas Disponíveis</h5>
            <div class="mb-3">
                <?php if (empty($espacosDisponiveis)): ?>
                    <p class="text-muted small">Nenhum espaço cadastrado ou disponível hoje.</p>
                <?php else: ?>
                    <?php foreach(array_slice($espacosDisponiveis, 0, 3) as $e): ?>
                        <div class="space-room-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="space-room-icon">
                                    <i class="fa fa-<?= $e['tipo'] === 'coworking' ? 'laptop-code' : ($e['tipo'] === 'reuniao' ? 'users-gear' : 'lightbulb') ?>"></i>
                                </div>
                                <div>
                                    <div class="fw-bold small"><?= htmlspecialchars($e['nome']) ?></div>
                                    <small class="text-muted" style="font-size:0.68rem;">Lotação: <?= $e['capacidade'] ?> pessoas</small>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success small rounded-pill">LIVRE</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="p-3 bg-light rounded" style="border-left: 4px solid var(--primary);">
                <div class="fw-bold small mb-1">Nota da Recepção:</div>
                <p class="x-small text-muted mb-0">Solicite a sua reserva de sala com antecedência para evitar conflitos de horário. O tempo máximo por reserva é de 4 horas.</p>
            </div>
        </div>
    </div>
</div>

<!-- DETALHES DE SESSÕES & FEEDBACK -->
<div class="row g-4">
    <!-- FEEDBACK DO ORIENTADOR -->
    <div class="col-lg-6">
        <div class="glass-card h-100 p-4">
            <h5 class="fw-bold mb-4"><i class="fa fa-comment-dots text-primary me-2"></i>Feedback & Comentários do Orientador</h5>
            <?php if (empty($comentarios)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa fa-comments-slash fa-3x mb-3 text-black-50" style="opacity:0.2"></i>
                    <p class="mb-0 small">A sua startup ainda não possui feedbacks do orientador.</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:16px; max-height: 380px; overflow-y: auto; padding-right:5px;">
                    <?php foreach ($comentarios as $c): ?>
                        <div style="padding:14px 16px;background:var(--surface-2);border-radius:var(--radius);border-left:4px solid var(--primary)">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong style="font-size:0.85rem"><?= htmlspecialchars($c['nome']) ?></strong>
                                <small class="text-muted" style="font-size:0.7rem;"><?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?></small>
                            </div>
                            <span class="badge-estado badge-<?= str_replace(' ','_',$c['fase']) ?>" style="margin-bottom:8px;display:inline-block; font-size:0.6rem">
                                <?= strtoupper(str_replace('_',' ',$c['fase'])) ?>
                            </span>
                            <p class="small mb-0" style="color:var(--text-primary)"><?= nl2br(htmlspecialchars($c['comentario'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SESSÕES DE MENTORIA REALIZADAS -->
    <div class="col-lg-6">
        <div class="glass-card h-100 p-4">
            <h5 class="fw-bold mb-4"><i class="fa fa-handshake text-primary me-2"></i>Histórico de Sessões de Mentoria</h5>
            <?php if (empty($sessoesMentoria)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa fa-calendar-xmark fa-3x mb-3 text-black-50" style="opacity:0.2"></i>
                    <p class="mb-0 small">Nenhuma sessão de mentoria logada.</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:16px; max-height: 380px; overflow-y: auto; padding-right:5px;">
                    <?php foreach ($sessoesMentoria as $s): ?>
                        <div style="padding:14px 16px;background:var(--surface-1);border-radius:var(--radius);border:1px solid var(--border-color)">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong style="font-size:0.85rem"><i class="fa fa-user-tie me-1"></i> Mentor: <?= htmlspecialchars($s['mentor_nome']) ?></strong>
                                <small class="text-muted" style="font-size:0.7rem;"><?= date('d/m/Y', strtotime($s['data_sessao'])) ?></small>
                            </div>
                            <div class="mb-2">
                                <small class="badge bg-light text-dark"><?= $s['duracao_min'] ?> minutos</small>
                                <small class="ms-1 text-warning">
                                    <?php for($i=0; $i<$s['avaliacao_equipa']; $i++) echo '★'; ?>
                                </small>
                            </div>
                            <p class="small mb-0"><strong>Tópicos:</strong> <?= nl2br(htmlspecialchars($s['topicos'])) ?></p>
                            <?php if($s['proximos_passos']): ?>
                                <div class="mt-2 pt-2 border-top x-small" style="color:var(--primary)">
                                    <strong>Próximos Passos:</strong> <?= htmlspecialchars($s['proximos_passos']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── MODAL RESERVA DE ESPAÇO ── -->
<div class="modal fade" id="modalNovaReservaDashboard" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST">
                <input type="hidden" name="action" value="solicitar_reserva">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-bookmark me-2"></i> Reservar Espaço da Incubadora</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Escolha o Espaço *</label>
                        <select name="id_espaco" class="form-select form-control-custom" required>
                            <option value="">Selecione...</option>
                            <?php foreach($espacosDisponiveis as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= $e['nome'] ?> (<?= ucfirst($e['tipo']) ?>) - Capacidade: <?= $e['capacidade'] ?>p</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Data *</label>
                        <input type="date" name="data_reserva" class="form-control-custom" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Hora Início *</label>
                            <input type="time" name="hora_inicio" class="form-control-custom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Hora Fim *</label>
                            <input type="time" name="hora_fim" class="form-control-custom" required>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Objetivo da Reserva</label>
                        <textarea name="objetivo" class="form-control-custom" rows="2" placeholder="Ex: Reunião de equipa para discutir MVP"></textarea>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Solicitar reserva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── MODAL UPLOAD DOCUMENTO ── -->
<div class="modal fade" id="modalUploadDocumentoDashboard" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_documento">
                <input type="hidden" name="id_projeto" value="<?= $ultimoProjeto['id'] ?>">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-upload me-2"></i>Carregar Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Título do Documento *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Pitch Deck 2026, Business Canvas">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Tipo</label>
                        <select name="tipo" class="form-control-custom">
                            <option value="Pitch">Pitch / Apresentação</option>
                            <option value="Plano de Negócio">Plano de Negócio</option>
                            <option value="Contrato">Contrato / Documento Legal</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-custom">Ficheiro (PDF/PPTX/ZIP/Imagem) *</label>
                        <input type="file" name="ficheiro" class="form-control-custom" required>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Fazer Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- CASO NÃO TENHA PROJETO AINDA -->
<div class="glass-card p-5 text-center my-5">
    <div class="empty-state-icon text-primary mb-4"><i class="fa fa-diagram-project fa-4x"></i></div>
    <h3 class="fw-800 text-dark mb-2">Ainda não tem ideias submetidas</h3>
    <p class="text-muted small mx-auto mb-4" style="max-width: 480px;">O ecossistema da Incubadora Académica do ISPSN apoia-o a validar e estruturar a sua ideia de negócio. Clique abaixo para registar o seu projeto ou trabalho.</p>
    <button class="btn btn-warning fw-bold text-dark px-4 py-2.5" style="border-radius:10px" data-bs-toggle="modal" data-bs-target="#modalProjeto">
        <i class="fa fa-plus me-1"></i> Submeter Primeiro Projeto
    </button>
</div>
<?php endif; ?>

<!-- MODAL SUBMETER IDEIA -->
<div class="modal fade" id="modalProjeto" tabindex="-1" aria-labelledby="modalProjetoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content modal-content-custom">
      <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
        <input type="hidden" name="action" value="criar_projeto">
        <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
        <div class="modal-header-custom">
            <h5 class="modal-title fw-bold" id="modalProjetoLabel">
                <i class="fa fa-rocket me-2" style="color:var(--primary)"></i>
                Submeter Ideia de Startup / Projecto
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body-custom">
            <div class="mb-3">
                <label class="form-label-custom">Nome da Startup ou Projecto *</label>
                <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: App de Transporte de Carga Local">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label-custom">Estágio / Tipo *</label>
                    <select name="tipo" class="form-control-custom" required>
                        <option value="startup_tecnologica">Startup Tecnológica (SaaS, App)</option>
                        <option value="negocio_tradicional">Negócio Tradicional (Comércio, Serviços)</option>
                        <option value="impacto_social">Impacto Social / ONG</option>
                        <option value="outro">Outro (Misto)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Área Temática</label>
                    <select name="area" class="form-control-custom">
                        <option value="tecnologia">Tecnologia & Inovação</option>
                        <option value="saude">Saúde</option>
                        <option value="educacao">Educação</option>
                        <option value="agro">Agronegócio</option>
                        <option value="financas">Finanças</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label-custom">Descrição do Projecto *</label>
                <textarea name="descricao" class="form-control-custom" rows="3" required placeholder="Descreva o seu projecto em detalhe (mínimo 20 caracteres)"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label-custom">Problema Identificado</label>
                <textarea name="problema" class="form-control-custom" rows="2" placeholder="Qual é o problema que o seu projecto resolve?"></textarea>
            </div>

            <div class="mb-0">
                <label class="form-label-custom">Solução Proposta</label>
                <textarea name="solucao" class="form-control-custom" rows="2" placeholder="Como o seu projecto resolve o problema?"></textarea>
            </div>
        </div>
        <div class="modal-footer-custom">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn-primary-custom">
                <i class="fa fa-paper-plane"></i> Submeter Projeto
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = '<script>
const el=document.getElementById("graficoProgresso");
if(el){
  const primaryColor = getComputedStyle(document.documentElement).getPropertyValue("--primary").trim() || "#D97706";
  new Chart(el,{
    type:"doughnut",
    data:{datasets:[{data:[<?= $progresso ?>,<?= 100-$progresso ?>],backgroundColor:[primaryColor,"#F3F4F6"],borderWidth:0}]},
    options:{cutout:"75%",plugins:{legend:{display:false},tooltip:{enabled:false}}}
  });
}
<\/script>';
require_once __DIR__ . '/../partials/_layout_end.php';
?>
