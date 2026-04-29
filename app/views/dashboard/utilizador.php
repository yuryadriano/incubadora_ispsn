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
    SELECT titulo, estado, criado_em
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
    .task-badge {
        font-size: 0.65rem;
        padding: 1px 5px;
        border-radius: 4px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .task-pendente { background: #fee2e2; color: #991b1b; }
    .task-em_progresso { background: #fef3c7; color: #92400e; }
    .task-concluida { background: #dcfce7; color: #166534; }

    /* COMPACT CARDS OVERRIDE */
    .kpi-card { 
        padding: 1.25rem !important; 
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 140px;
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05) !important;
    }
    .kpi-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.08);
    }
    .kpi-value { 
        font-size: 1.75rem !important; 
        font-weight: 800 !important;
        margin-bottom: 0.25rem !important;
    }
    .kpi-icon { 
        width: 42px !important; 
        height: 42px !important; 
        font-size: 1.1rem !important; 
        margin-bottom: 12px !important;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: color-mix(in srgb, var(--kpi-color), transparent 90%);
        color: var(--kpi-color);
    }
    .kpi-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .kpi-trend {
        margin-top: auto;
        font-size: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .card-body-custom { padding: 1.25rem !important; }
    .card-header-custom { padding: 1rem 1.25rem !important; }

    /* STEPPER PIPELINE - MODERN & COMPACT */
    .innovation-pipeline-stepper {
        position: relative;
        padding: 10px 0;
    }
    .stepper-track {
        display: flex;
        justify-content: space-between;
        position: relative;
    }
    .stepper-track::before {
        content: '';
        position: absolute;
        top: 14px;
        left: 5%;
        right: 5%;
        height: 2px;
        background: #eef2f7;
        z-index: 1;
    }
    .step-item {
        position: relative;
        z-index: 2;
        text-align: center;
        flex: 1;
    }
    .step-dot {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: white;
        border: 2px solid #eef2f7;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 6px;
        font-weight: 700;
        font-size: 0.65rem;
        color: #cbd5e1;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .step-label {
        font-size: 0.6rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.2px;
    }
    .step-item.active .step-dot {
        border-color: var(--primary);
        color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary), transparent 90%);
        transform: scale(1.1);
    }
    .step-item.active .step-label { color: var(--primary); }
    
    .step-item.completed .step-dot {
        background: var(--success);
        border-color: var(--success);
        color: white;
    }
    .step-item.completed .step-label { color: #64748b; }

    .step-item.rejected .step-dot {
        background: var(--danger);
        border-color: var(--danger);
        color: white;
    }
    .step-item.rejected .step-label { color: var(--danger); }
</style>

<!-- FLASH -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger  mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

    <!-- TÍTULO -->
    <div class="page-header">
        <div>
            <div class="page-header-title">
                <i class="fa fa-house me-2" style="color:var(--primary)"></i>
                Meu Painel
            </div>
            <div class="page-header-sub">
                Acompanhe os seus projectos e monografias
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($ultimoProjeto): ?>
                <?php if (!empty($meusKpis)): ?>
                <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#modalKPIs">
                    <i class="fa fa-chart-line"></i> Actualizar KPIs
                </button>
                <?php endif; ?>

                <?php if (!empty($meusFinanciamentos)): ?>
                <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#modalDespesa">
                    <i class="fa fa-receipt"></i> Registar Despesa
                </button>
                <?php endif; ?>
            <?php endif; ?>
            <button class="btn-primary-custom"
                    data-bs-toggle="modal"
                    data-bs-target="#modalProjeto">
                <i class="fa fa-plus"></i> Submeter Projecto
            </button>
        </div>
    </div>

    <!-- KPI CARDS -->
    <div class="kpi-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; margin-bottom: 20px;">

        <div class="kpi-card" style="--kpi-color:var(--primary)">
            <div class="kpi-icon"><i class="fa fa-rocket"></i></div>
            <div class="kpi-value"><?= $meusProjetos ?></div>
            <div class="kpi-label">Meus Projectos</div>
            <div class="kpi-trend" style="color:var(--primary)"><i class="fa fa-circle-dot"></i> <?= $ultimoProjeto ? $labels[$ultimoProjeto['estado']] : 'Sem projectos' ?></div>
        </div>

        <div class="kpi-card" style="--kpi-color:var(--success)">
            <div class="kpi-icon"><i class="fa fa-money-bill-trend-up"></i></div>
            <div class="kpi-value"><?= number_format($totalFinanciamento, 0, ',', '.') ?> <small style="font-size:0.5em">Kz</small></div>
            <div class="kpi-label">Capital Levantado</div>
            <div class="kpi-trend" style="color:var(--success)"><i class="fa fa-wallet"></i> Total aprovado</div>
        </div>

        <div class="kpi-card" style="--kpi-color:#8B5CF6">
            <div class="kpi-icon"><i class="fa fa-list-check"></i></div>
            <div class="kpi-value"><?= count($minhasTarefas) ?></div>
            <div class="kpi-label">Tarefas Atribuídas</div>
            <div class="kpi-trend" style="color:#8B5CF6"><i class="fa fa-tasks"></i> Do seu mentor</div>
        </div>

        <div class="kpi-card" style="--kpi-color:#EC4899">
            <div class="kpi-icon"><i class="fa fa-chart-line"></i></div>
            <div class="kpi-value"><?= $progresso ?>%</div>
            <div class="kpi-label">Progresso</div>
            <div class="kpi-trend" style="color:#EC4899"><i class="fa fa-arrow-trend-up"></i> Projecto actual</div>
        </div>

    </div>


<!-- ACOMPANHAMENTO -->
<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">
            <i class="fa fa-chart-line"></i> Acompanhamento do Projecto
        </div>
    </div>
    <div class="card-body-custom">
    <?php if($ultimoProjeto): ?>
        <div class="row g-4">
            <!-- GRÁFICO E KPI DE SCORE -->
            <div class="col-md-3 text-center d-flex flex-column justify-content-center border-end">
                <div class="position-relative d-inline-block mx-auto mb-2">
                    <canvas id="graficoProgresso" style="max-width:90px;max-height:90px"></canvas>
                    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); font-size:1.1rem; font-weight:800; color:var(--primary)"><?= $progresso ?>%</div>
                </div>
                <small class="text-muted d-block fw-bold mb-3" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.5px">Progresso Geral</small>

                <?php if($mediaAvaliacaoAdmin && $mediaAvaliacaoAdmin['total'] > 0): ?>
                    <div class="p-2 rounded" style="background:var(--surface-1); border:1px solid var(--border-color)">
                        <div class="fw-bold text-muted" style="font-size:0.6rem; text-transform:uppercase">Score Médio</div>
                        <div class="mb-0 fw-800" style="font-size:1.1rem; color:<?= $mediaAvaliacaoAdmin['media'] >= 7 ? 'var(--success)' : ($mediaAvaliacaoAdmin['media'] >= 4 ? 'var(--warning)' : 'var(--danger)') ?>">
                            <?= number_format($mediaAvaliacaoAdmin['media'], 1) ?> <small style="font-size:0.6em; color:#94a3b8">/ 10</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PIPELINE DE INOVAÇÃO -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-800 mb-1" style="color:var(--text-primary); letter-spacing:-0.5px"><?= htmlspecialchars($ultimoProjeto['titulo']) ?></h5>
                        <div class="d-flex align-items-center gap-2">
                            <?php
                            $cor = $corBadge[$ultimoProjeto['estado']] ?? 'info';
                            ?>
                            <span class="badge-estado badge-<?= $cor ?>" style="font-size:0.65rem; padding: 4px 12px; border-radius: 20px;">
                                <i class="fa fa-circle me-1" style="font-size:0.5rem"></i>
                                <?= $labels[$ultimoProjeto['estado']] ?? $ultimoProjeto['estado'] ?>
                            </span>
                        </div>
                    </div>
                    <?php if(!empty($minhasAvaliacosMentor)): ?>
                       <div class="text-end p-2 px-3 rounded-3" style="background: color-mix(in srgb, var(--primary), transparent 95%); border: 1px solid color-mix(in srgb, var(--primary), transparent 90%)">
                           <div class="small fw-bold text-muted" style="font-size:0.6rem; text-transform:uppercase">Maturidade</div>
                           <div class="h5 mb-0 fw-800" style="color:var(--primary)"><?= $minhasAvaliacosMentor[0]['progresso_geral'] ?>%</div>
                       </div>
                    <?php endif; ?>
                </div>
                
                <!-- STEPPER DO PIPELINE -->
                <div class="innovation-pipeline-stepper mb-3">
                    <?php 
                    $pipelineSteps = [
                        ['id' => 'submetido',          'label' => 'Submetido'],
                        ['id' => 'em_avaliacao',       'label' => 'Avaliação'],
                        ['id' => 'aprovado',           'label' => 'Seleccionado'],
                        ['id' => 'incubado',           'label' => 'Incubação'],
                        ['id' => 'fundo_investimento', 'label' => 'Investimento'],
                        ['id' => 'concluido',          'label' => 'Graduação']
                    ];
                    
                    $currentState = $ultimoProjeto['estado'];
                    $currentIdx = 0;
                    if($currentState == 'rejeitado') $currentIdx = -1;
                    else {
                        foreach($pipelineSteps as $idx => $step) {
                            if($currentState == $step['id']) {
                                $currentIdx = $idx;
                            }
                        }
                    }
                    ?>
                    <div class="stepper-track">
                        <?php foreach($pipelineSteps as $idx => $step): 
                            $status = ($idx < $currentIdx) ? 'completed' : (($idx === $currentIdx) ? 'active' : 'pending');
                            if($currentState == 'rejeitado' && $idx == 0) $status = 'rejected';
                        ?>
                            <div class="step-item <?= $status ?>">
                                <div class="step-dot" style="width:28px; height:28px; font-size:0.7rem; border-width:2px">
                                    <?php if($status == 'completed'): ?><i class="fa fa-check"></i>
                                    <?php elseif($status == 'rejected'): ?><i class="fa fa-times"></i>
                                    <?php else: ?><?= $idx + 1 ?><?php endif; ?>
                                </div>
                                <div class="step-label" style="font-size:0.6rem"><?= $step['label'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-7">
                        <div class="p-2 bg-light rounded border-start border-4 border-primary" style="font-size:0.75rem">
                            <?php if($currentState == 'submetido'): ?>
                                <p class="mb-0">A sua ideia está em <strong>Triagem Inicial</strong>. Verifique o seu e-mail nos próximos dias para agendar a apresentação técnica.</p>
                            <?php elseif($currentState == 'em_avaliacao'): ?>
                                <p class="mb-0">Os avaliadores estão a analisar a viabilidade do seu projecto. Certifique-se de que o seu <strong>Pitch Deck</strong> está actualizado.</p>
                            <?php elseif($currentState == 'aprovado'): ?>
                                <p class="mb-0">Parabéns! O seu projecto foi aprovado para o programa. O próximo passo é a <strong>Assinatura do Acordo de Incubação</strong>.</p>
                            <?php elseif($currentState == 'incubado'): ?>
                                <p class="mb-0">Em fase de aceleração. Foque no cumprimento das <strong>Tarefas do Mentor</strong> e na validação do seu modelo de negócio.</p>
                            <?php elseif($currentState == 'fundo_investimento'): ?>
                                <p class="mb-0">Excelente progresso! O seu projecto está em preparação para <strong>Rodada de Investimento</strong>. Prepare os seus relatórios financeiros.</p>
                            <?php elseif($currentState == 'concluido'): ?>
                                <p class="mb-0"><strong>Projecto Graduado!</strong> Parabéns pela jornada. Continue a utilizar a nossa rede de contactos para escalar.</p>
                            <?php else: ?>
                                <p class="mb-0">Consulte a administração para orientações sobre a fase actual do seu projecto.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <?php if(!empty($minhasTarefas)): ?>
                            <div class="d-flex flex-column gap-1 mb-2">
                            <?php foreach(array_slice($minhasTarefas, 0, 2) as $t): ?>
                                <div class="p-1 px-2 border rounded small bg-white d-flex align-items-center gap-2" style="font-size:0.7rem">
                                    <i class="fa <?= $t['status']=='concluida'?'fa-check-circle text-success':'fa-circle-notch text-muted' ?>"></i>
                                    <span class="text-truncate"><?= htmlspecialchars($t['titulo']) ?></span>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if(!empty($ultimasAvaliacoesOficiais)): ?>
                            <div class="p-1 px-2 border rounded bg-white" style="border-left: 3px solid var(--primary) !important; font-size:0.7rem">
                                <div class="fw-bold mb-0">Feedback: <?= $ultimasAvaliacoesOficiais[0]['pontuacao_total'] ?>/10</div>
                                <div class="text-muted text-truncate">
                                    <?= htmlspecialchars($ultimasAvaliacoesOficiais[0]['observacoes'] ?? 'Sem comentários.') ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa fa-diagram-project"></i></div>
            <div class="empty-state-title">Nenhum projecto submetido</div>
            <div class="empty-state-text">Clique em "Submeter Projecto" para começar</div>
        </div>
    <?php endif; ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- COLUNA TAREFAS -->
    <div class="col-lg-7">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-tasks"></i> Plano de Trabalho (Tarefas)</div>
            </div>
            <div class="card-body-custom">
                <?php if(empty($minhasTarefas)): ?>
                    <p class="text-muted text-center py-4">Sem tarefas atribuídas.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle" style="font-size:0.875rem">
                            <thead>
                                <tr>
                                    <th>Tarefa</th>
                                    <th>Prazo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($minhasTarefas as $t): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($t['titulo']) ?></div>
                                        <div class="text-muted x-small"><?= htmlspecialchars($t['descricao']) ?></div>
                                    </td>
                                    <td><?= $t['data_limite'] ? date('d/m/Y', strtotime($t['data_limite'])) : '—' ?></td>
                                    <td>
                                        <span class="task-badge task-<?= $t['status'] ?>">
                                            <?= str_replace('_',' ',$t['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- COLUNA AVALIAÇÕES MENTOR -->
    <div class="col-lg-5">
        <div class="card-custom mb-4" style="border-left: 4px solid var(--primary)">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa-solid fa-calendar-days"></i> Próximas Reuniões</div>
            </div>
            <div class="card-body-custom">
                <?php if(empty($minhasReunioes)): ?>
                    <p class="text-muted text-center py-3 small">Sem reuniões agendadas.</p>
                <?php else: ?>
                    <?php foreach($minhasReunioes as $r): ?>
                    <div class="p-3 mb-2 rounded bg-light border">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-bold small"><?= htmlspecialchars($r['titulo']) ?></div>
                                <div class="text-muted" style="font-size:0.75rem">
                                    <i class="fa-solid fa-clock me-1"></i> <?= date('d/m/Y H:i', strtotime($r['data_reuniao'])) ?>
                                </div>
                            </div>
                            <?php if($r['link_reuniao']): ?>
                                <a href="<?= $r['link_reuniao'] ?>" target="_blank" class="btn-primary-custom" style="padding:4px 8px; font-size:0.7rem">
                                    <i class="fa-solid fa-video"></i> Entrar
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted">
                            <i class="fa-solid fa-user-tie me-1"></i> Mentor: <?= htmlspecialchars($r['mentor_nome']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa-solid fa-star"></i> Feedback do Mentor</div>
            </div>
            <div class="card-body-custom">
                <?php if(empty($minhasAvaliacosMentor)): ?>
                    <p class="text-muted text-center py-4">Aguardando primeira avaliação.</p>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:12px">
                        <?php foreach(array_slice($minhasAvaliacosMentor, 0, 3) as $av): ?>
                        <div class="p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-primary"><?= htmlspecialchars($av['periodo']) ?></span>
                                <span class="badge bg-primary"><?= $av['progresso_geral'] ?>%</span>
                            </div>
                            <p class="small text-muted mb-0"><?= mb_strimwidth(htmlspecialchars($av['feedback']), 0, 100, '...') ?></p>
                            <div class="text-end mt-2">
                                <small class="text-muted fst-italic">Por: <?= htmlspecialchars($av['mentor_nome']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- COMENTÁRIOS E SESSÕES -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">
                    <i class="fa fa-comments"></i> Comentários da Incubadora
                </div>
            </div>
            <div class="card-body-custom">
            <?php if (!empty($comentarios)): ?>
                <div style="display:flex;flex-direction:column;gap:16px">
                <?php foreach ($comentarios as $c): ?>
                <div style="padding:14px 16px;background:var(--surface-2);border-radius:var(--radius);border-left:4px solid var(--primary)">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong style="font-size:0.875rem"><?= htmlspecialchars($c['nome']) ?></strong>
                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?></small>
                    </div>
                    <span class="badge-estado badge-<?= str_replace(' ','_',$c['fase']) ?>" style="margin-bottom:8px;display:inline-block">
                        <?= strtoupper(str_replace('_',' ',$c['fase'])) ?>
                    </span>
                    <p style="margin:0;font-size:0.875rem;color:var(--text-primary)"><?= nl2br(htmlspecialchars($c['comentario'])) ?></p>
                </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding:32px">
                    <div class="empty-state-icon"><i class="fa fa-comment-slash"></i></div>
                    <div class="empty-state-title">Sem comentários ainda</div>
                    <div class="empty-state-text">O seu orientador ainda não adicionou comentários.</div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">
                    <i class="fa fa-handshake"></i> Sessões de Mentoria Realizadas
                </div>
            </div>
            <div class="card-body-custom">
            <?php if (!empty($sessoesMentoria)): ?>
                <div style="display:flex;flex-direction:column;gap:16px">
                <?php foreach ($sessoesMentoria as $s): ?>
                <div style="padding:14px 16px;background:var(--surface-1);border-radius:var(--radius);border:1px solid var(--border-color)">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong style="font-size:0.875rem"><i class="fa fa-user-tie me-1"></i> <?= htmlspecialchars($s['mentor_nome']) ?></strong>
                        <small class="text-muted"><?= date('d/m/Y', strtotime($s['data_sessao'])) ?></small>
                    </div>
                    <div class="mb-2">
                        <small class="badge bg-light text-dark"><?= $s['duracao_min'] ?> min</small>
                        <small class="ms-1 text-warning">
                            <?php for($i=0; $i<$s['avaliacao_equipa']; $i++) echo '★'; ?>
                        </small>
                    </div>
                    <p style="margin:0;font-size:0.875rem;font-weight:600;color:var(--text-primary)">Tópicos: <?= nl2br(htmlspecialchars($s['topicos'])) ?></p>
                    <?php if($s['proximos_passos']): ?>
                        <p class="mt-2 mb-0" style="font-size:0.8rem;color:var(--primary)">
                            <strong>Próximos Passos:</strong> <?= htmlspecialchars($s['proximos_passos']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding:32px">
                    <div class="empty-state-icon"><i class="fa fa-calendar-xmark"></i></div>
                    <div class="empty-state-title">Nenhuma sessão logada</div>
                    <div class="empty-state-text">As suas sessões com mentores aparecerão aqui.</div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUBMETER PROJECTO -->
<div class="modal fade" id="modalProjeto" tabindex="-1" aria-labelledby="modalProjetoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content modal-content-custom">
      <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
        <input type="hidden" name="action" value="criar_projeto">
        <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
        <div class="modal-header-custom">
            <h5 class="modal-title fw-bold" id="modalProjetoLabel">
                <i class="fa fa-rocket me-2" style="color:var(--primary)"></i>
                Registar Startup / Ideia
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body-custom">

            <div class="mb-3">
                <label class="form-label-custom">Nome da Startup ou Projecto *</label>
                <input type="text" name="titulo" class="form-control-custom" required
                       placeholder="Ex: App de Transporte de Carga Local">
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
                <textarea name="descricao" class="form-control-custom" rows="3" required
                          placeholder="Descreva o seu projecto em detalhe (mínimo 20 caracteres)"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label-custom">Problema Identificado</label>
                <textarea name="problema" class="form-control-custom" rows="2"
                          placeholder="Qual é o problema que o seu projecto resolve?"></textarea>
            </div>

            <div class="mb-0">
                <label class="form-label-custom">Solução Proposta</label>
                <textarea name="solucao" class="form-control-custom" rows="2"
                          placeholder="Como o seu projecto resolve o problema?"></textarea>
            </div>

        </div>
        <div class="modal-footer-custom">
            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn-primary-custom">
                <i class="fa fa-paper-plane"></i> Submeter Projecto
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL REGISTAR DESPESA -->
<div class="modal fade" id="modalDespesa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/operacoes_action.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="registar_despesa">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-receipt me-2"></i>Registar Despesa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Fonte de Financiamento *</label>
                        <select name="id_financiamento" class="form-control-custom" required>
                            <option value="">— Seleccione o financiamento —</option>
                            <?php foreach (($meusFinanciamentos ?? []) as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['fonte']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Descrição da Despesa *</label>
                        <input type="text" name="descricao" class="form-control-custom" required placeholder="Ex: Compra de Servidor, Material de Escritório">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label-custom">Valor (Kz) *</label>
                            <input type="number" name="valor" class="form-control-custom" required min="0" step="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label-custom">Data *</label>
                            <input type="date" name="data_despesa" class="form-control-custom" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Categoria</label>
                        <select name="categoria" class="form-control-custom">
                            <option value="Tecnologia">Tecnologia</option>
                            <option value="Equipamento">Equipamento</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Recursos Humanos">Recursos Humanos</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Justificativo (PDF/Imagem)</label>
                        <input type="file" name="justificativo" class="form-control-custom">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-check"></i> Salvar Despesa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL ACTUALIZAR KPIs -->
<div class="modal fade" id="modalKPIs" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/operacoes_action.php">
                <input type="hidden" name="action" value="registar_kpi">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-chart-line me-2"></i>Actualizar Indicador (KPI)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Indicador *</label>
                        <select name="id_kpi" class="form-control-custom" required>
                            <option value="">— Seleccione o KPI —</option>
                            <?php foreach (($meusKpis ?? []) as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nome']) ?> (<?= $k['unidade'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label-custom">Valor *</label>
                            <input type="number" name="valor" class="form-control-custom" required step="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label-custom">Período *</label>
                            <input type="text" name="periodo" class="form-control-custom" required placeholder="Ex: 2026-04, 2026-Q2">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Observações</label>
                        <textarea name="observacoes" class="form-control-custom" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-save"></i> Guardar Valor</button>
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
