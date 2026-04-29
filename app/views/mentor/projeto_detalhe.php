<?php
// app/views/mentor/projeto_detalhe.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['mentor', 'admin', 'superadmin']);

$idUsuario = (int)$_SESSION['usuario_id'];
$idProjeto = (int)($_GET['id'] ?? 0);

if (!$idProjeto) {
    header('Location: /incubadora_ispsn/public/index.php');
    exit;
}

// Buscar ID do mentor
$stmt = $mysqli->prepare("SELECT id FROM mentores WHERE id_usuario = ?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$mentorInfo = $stmt->get_result()->fetch_assoc();
$idMentor = $mentorInfo['id'] ?? 0;
$stmt->close();

// 1. Buscar projecto
$stmt = $mysqli->prepare("
    SELECT p.*, u.nome autor, u.email email_autor, u.telefone tel_autor
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    WHERE p.id = ?
");
$stmt->bind_param('i', $idProjeto);
$stmt->execute();
$projeto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$projeto) {
    header('Location: /incubadora_ispsn/public/index.php');
    exit;
}

// 2. Buscar Financiamento
$stmt = $mysqli->prepare("SELECT * FROM financiamentos WHERE id_projeto = ?");
$stmt->bind_param('i', $idProjeto);
$stmt->execute();
$financiamentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalAprovado = array_sum(array_column($financiamentos, 'montante_aprovado'));
$totalExecutado = array_sum(array_column($financiamentos, 'montante_executado'));
$percentagemExec = $totalAprovado > 0 ? round(($totalExecutado / $totalAprovado) * 100) : 0;

// 3. Buscar KPIs e registos
$stmt = $mysqli->prepare("
    SELECT k.*, 
           (SELECT valor FROM registos_kpi WHERE id_kpi = k.id ORDER BY registado_em DESC LIMIT 1) as ultimo_valor,
           (SELECT periodo FROM registos_kpi WHERE id_kpi = k.id ORDER BY registado_em DESC LIMIT 1) as ultimo_periodo
    FROM kpis k 
    WHERE k.id_projeto = ? AND k.activo = 1
");
$stmt->bind_param('i', $idProjeto);
$stmt->execute();
$kpis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 4. Buscar histórico de sessões
$stmt = $mysqli->prepare("
    SELECT s.*, u.nome as mentor_nome
    FROM sessoes_mentoria s
    JOIN mentorias m ON m.id = s.id_mentoria
    JOIN mentores mt ON mt.id = m.id_mentor
    JOIN usuarios u ON u.id = mt.id_usuario
    WHERE m.id_projeto = ?
    ORDER BY s.data_sessao DESC
");
$stmt->bind_param('i', $idProjeto);
$stmt->execute();
$sessoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 5. Buscar Tarefas
$stmt = $mysqli->prepare("SELECT * FROM tarefas WHERE id_projeto = ? ORDER BY criado_em DESC");
$stmt->bind_param('i', $idProjeto);
$stmt->execute();
$tarefas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 6. Buscar Avaliações de Mentor (Acompanhamentos)
$stmt = $mysqli->prepare("
    SELECT av.*, u.nome as mentor_nome
    FROM avaliacoes_mentor av
    JOIN mentores m ON m.id = av.id_mentor
    JOIN usuarios u ON u.id = m.id_usuario
    WHERE av.id_projeto = ?
    ORDER BY av.criado_em DESC
");
$stmt->bind_param('i', $idProjeto);
$stmt->execute();
$avaliacoesMentor = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$tituloPagina = 'Detalhe: ' . htmlspecialchars($projeto['titulo']);
$paginaActiva = 'projetos';
require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
    .nav-tabs-custom {
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 0;
    }
    .nav-link-custom {
        padding: 10px 20px;
        color: var(--text-muted);
        text-decoration: none;
        font-weight: 600;
        border-bottom: 2px solid transparent;
        transition: all 0.3s;
        cursor: pointer;
    }
    .nav-link-custom:hover { color: var(--primary); }
    .nav-link-custom.active {
        color: var(--primary);
        border-bottom: 2px solid var(--primary);
        background: rgba(79, 70, 229, 0.05);
    }
    .tab-content-custom { display: none; transition: opacity 0.3s; }
    .tab-content-custom.active { display: block; opacity: 1; }

    .task-item {
        padding: 15px;
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        background: white;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: transform 0.2s;
    }
    .task-item:hover { transform: translateX(5px); border-color: var(--primary); }
    .task-priority-baixa { border-left: 4px solid var(--success); }
    .task-priority-media { border-left: 4px solid var(--warning); }
    .task-priority-alta { border-left: 4px solid var(--danger); }
</style>

<!-- HEADER -->
<div class="page-header">
    <div>
        <a href="/incubadora_ispsn/app/views/dashboard/mentor.php" class="btn-ghost mb-2">
            <i class="fa fa-arrow-left"></i> Voltar ao Painel
        </a>
        <div class="page-header-title"><?= htmlspecialchars($projeto['titulo']) ?></div>
        <div class="page-header-sub">
            <span class="badge-estado badge-<?= $projeto['estado'] ?>"><?= ucfirst(str_replace('_',' ',$projeto['estado'])) ?></span>
            <span class="ms-2 text-muted">| Autor: <?= htmlspecialchars($projeto['autor']) ?></span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn-ghost" style="color:#d97706" data-bs-toggle="modal" data-bs-target="#modalReuniao">
            <i class="fa fa-calendar-plus me-1"></i> Agendar Reunião
        </button>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTarefa">
            <i class="fa fa-tasks me-1"></i> Atribuir Tarefa
        </button>
        <button class="btn-secondary-custom" style="background:#8B5CF6; color:white; border:none" data-bs-toggle="modal" data-bs-target="#modalAvaliacao">
             <i class="fa fa-star me-1"></i> Nova Avaliação
        </button>
    </div>
</div>

<!-- TIMELINE DE MATURIDADE -->
<div class="card-custom mb-4" style="border:none; background: transparent; box-shadow:none">
    <div class="d-flex justify-content-between mb-3 align-items-center">
        <h5 class="fw-bold mb-0"><i class="fa fa-map-signs me-2" style="color:var(--primary)"></i>Jornada da Startup</h5>
        <span class="badge bg-light text-primary small">
            Fase: <?= strtoupper($projeto['fase'] ?? 'ideacao') ?>
        </span>
    </div>
    
    <div class="maturity-pipeline">
        <?php 
        $fases = [
            ['id' => 'ideacao',   'label' => 'Ideação',   'icon' => 'lightbulb'],
            ['id' => 'validacao', 'label' => 'Validação', 'icon' => 'vial'],
            ['id' => 'mvp',       'label' => 'MVP',       'icon' => 'cube'],
            ['id' => 'tracao',    'label' => 'Tração',    'icon' => 'chart-line'],
            ['id' => 'mercado',   'label' => 'Mercado',   'icon' => 'shop']
        ];
        $currentFaseIdx = 0;
        foreach($fases as $idx => $f) if($f['id'] == ($projeto['fase'] ?? 'ideacao')) $currentFaseIdx = $idx;
        ?>
        <div class="pipeline-container">
            <?php foreach($fases as $idx => $f): 
                $status = ($idx < $currentFaseIdx) ? 'completed' : (($idx == $currentFaseIdx) ? 'active' : 'pending');
            ?>
                <div class="pipeline-step <?= $status ?>">
                    <div class="step-icon"><i class="fa fa-<?= $f['icon'] ?>"></i></div>
                    <div class="step-label"><?= $f['label'] ?></div>
                </div>
                <?php if($idx < 4): ?>
                    <div class="pipeline-line <?= ($idx < $currentFaseIdx) ? 'completed' : '' ?>"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.maturity-pipeline { padding: 10px 0; }
.pipeline-container { display: flex; align-items: center; justify-content: space-between; position: relative; }
.pipeline-step { display: flex; flex-direction: column; align-items: center; z-index: 2; flex: 1; }
.step-icon { width: 45px; height: 45px; border-radius: 50%; background: #f3f4f6; color: #9ca3af; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; transition: all 0.3s; border: 3px solid #fff; box-shadow: 0 0 0 2px #f3f4f6; }
.step-label { font-size: 0.75rem; font-weight: 600; margin-top: 8px; color: #9ca3af; }
.pipeline-line { flex: 1; height: 4px; background: #f3f4f6; margin-top: -25px; z-index: 1; transition: all 0.3s; }
.pipeline-step.active .step-icon { background: var(--primary); color: white; transform: scale(1.15); box-shadow: 0 0 0 2px var(--primary); }
.pipeline-step.active .step-label { color: var(--primary); }
.pipeline-step.completed .step-icon { background: #10b981; color: white; box-shadow: 0 0 0 2px #10b981; }
.pipeline-step.completed .step-label { color: #10b981; }
.pipeline-line.completed { background: #10b981; }
</style>

<!-- NAVEGAÇÃO POR TABS -->
<div class="nav-tabs-custom">
    <div class="nav-link-custom active" onclick="switchTab(event, 'tab-resumo')">Resumo Geral</div>
    <div class="nav-link-custom" onclick="switchTab(event, 'tab-tarefas')">Tarefas (<?= count($tarefas) ?>)</div>
    <div class="nav-link-custom" onclick="switchTab(event, 'tab-sessoes')">Sessões de Mentoria</div>
    <div class="nav-link-custom" onclick="switchTab(event, 'tab-avaliacoes')">Acompanhamento / Avaliações</div>
    <div class="nav-link-custom" onclick="switchTab(event, 'tab-chat')"><i class="fa fa-comments me-1" style="color:var(--primary)"></i> Chat / Consultoria</div>
</div>

<div class="row g-4">
    <!-- CONTEÚDO PRINCIPAL (TABS) -->
    <div class="col-lg-8">
        
        <!-- TAB 1: RESUMO -->
        <div id="tab-resumo" class="tab-content-custom active">
            <!-- DESCRIÇÃO -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-file-lines"></i> Resumo do Projecto</div>
                </div>
                <div class="card-body-custom">
                    <p><strong>Descrição:</strong><br><?= nl2br(htmlspecialchars($projeto['descricao'])) ?></p>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="alert alert-info py-2" style="font-size:0.875rem">
                                <strong>Problema:</strong><br><?= nl2br(htmlspecialchars($projeto['problema'])) ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success py-2" style="font-size:0.875rem">
                                <strong>Solução:</strong><br><?= nl2br(htmlspecialchars($projeto['solucao'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI HISTORY -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-chart-line"></i> Indicadores de Performance (KPIs)</div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <?php if (empty($kpis)): ?>
                            <p class="text-muted text-center p-4">Nenhum KPI definido.</p>
                        <?php else: ?>
                            <?php foreach($kpis as $k): ?>
                            <div class="col-md-4">
                                <div style="padding:15px; background:var(--surface-2); border-radius:var(--radius); border:1px solid var(--border-color)">
                                    <small class="text-muted d-block mb-1"><?= htmlspecialchars($k['nome']) ?></small>
                                    <div class="d-flex align-items-baseline gap-1">
                                        <span style="font-size:1.5rem; font-weight:800; color:var(--primary)">
                                            <?= $k['ultimo_valor'] !== null ? number_format($k['ultimo_valor'], 1) : '—' ?>
                                        </span>
                                        <small class="text-muted"><?= $k['unidade'] ?></small>
                                    </div>
                                    <div class="mt-2" style="font-size:0.75rem">
                                        <span class="text-muted">Meta:</span> <span class="fw-bold"><?= number_format($k['meta'], 1) ?></span>
                                        <div class="progress mt-1" style="height:4px">
                                            <?php $perc = $k['meta'] > 0 ? min(100, ($k['ultimo_valor'] / $k['meta']) * 100) : 0; ?>
                                            <div class="progress-bar" style="width:<?= $perc ?>%; background:var(--primary)"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: TAREFAS -->
        <div id="tab-tarefas" class="tab-content-custom">
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-list-check"></i> Plano de Acção / Tarefas</div>
                </div>
                <div class="card-body-custom">
                    <?php if (empty($tarefas)): ?>
                        <div class="text-center p-5">
                            <i class="fa fa-clipboard-check fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Nenhuma tarefa atribuída ainda.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($tarefas as $t): ?>
                        <div class="task-item task-priority-<?= $t['prioridade'] ?>">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($t['titulo']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($t['descricao']) ?></div>
                                <div class="mt-2" style="font-size:0.75rem">
                                    <span class="badge bg-light text-dark"><i class="fa fa-calendar me-1"></i> <?= $t['data_limite'] ? date('d/m/Y', strtotime($t['data_limite'])) : 'Sem prazo' ?></span>
                                    <span class="badge bg-light text-dark ms-1"><i class="fa fa-flag me-1"></i> <?= ucfirst($t['prioridade']) ?></span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <form action="/incubadora_ispsn/app/controllers/mentor_action.php" method="POST">
                                    <input type="hidden" name="action" value="atualizar_estado_tarefa">
                                    <input type="hidden" name="id_tarefa" value="<?= $t['id'] ?>">
                                    <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width:140px">
                                        <option value="pendente" <?= $t['status']=='pendente'?'selected':'' ?>>Pendente</option>
                                        <option value="concluida" <?= $t['status']=='concluida'?'selected':'' ?>>Concluída</option>
                                    </select>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 3: SESSÕES -->
        <div id="tab-sessoes" class="tab-content-custom">
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-comments"></i> Histórico de Sessões</div>
                </div>
                <div class="card-body-custom">
                    <?php if (empty($sessoes)): ?>
                        <p class="text-muted text-center p-4">Nenhuma sessão registada.</p>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:12px">
                            <?php foreach($sessoes as $s): ?>
                            <div style="padding:15px; border-radius:var(--radius); border:1px solid var(--border-color)">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong><i class="fa fa-user-tie me-1"></i> <?= htmlspecialchars($s['mentor_nome']) ?></strong>
                                    <span class="badge bg-light text-dark"><?= date('d/m/Y', strtotime($s['data_sessao'])) ?></span>
                                </div>
                                <p class="mb-2" style="font-size:0.9rem"><?= nl2br(htmlspecialchars($s['topicos'])) ?></p>
                                <?php if($s['proximos_passos']): ?>
                                    <div style="font-size:0.8rem; background:rgba(79, 70, 229, 0.05); padding:8px; border-radius:4px">
                                        <strong>Próximos passos:</strong> <?= htmlspecialchars($s['proximos_passos']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 4: AVALIAÇÕES -->
        <div id="tab-avaliacoes" class="tab-content-custom">
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-star"></i> Acompanhamento Periódico</div>
                </div>
                <div class="card-body-custom">
                    <?php if (empty($avaliacoesMentor)): ?>
                        <p class="text-muted text-center p-4">Nenhuma avaliação submetida ainda.</p>
                    <?php else: ?>
                        <?php foreach($avaliacoesMentor as $av): ?>
                        <div class="mb-4 p-3" style="border:1px solid var(--border-color); border-radius:var(--radius)">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0">Período: <?= htmlspecialchars($av['periodo']) ?></h6>
                                    <small class="text-muted">Por: <?= htmlspecialchars($av['mentor_nome']) ?> em <?= date('d/m/Y', strtotime($av['criado_em'])) ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold" style="color:var(--primary)">Progresso: <?= $av['progresso_geral'] ?>%</div>
                                    <div class="progress" style="height:6px; width:100px">
                                        <div class="progress-bar" style="width:<?= $av['progresso_geral'] ?>%; background:var(--primary)"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="small"><strong>Feedback Geral:</strong></div>
                                    <div class="small text-muted"><?= nl2br(htmlspecialchars($av['feedback'])) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="small"><strong>Recomendações:</strong></div>
                                    <div class="small text-muted"><?= nl2br(htmlspecialchars($av['recomendacoes'])) ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 5: CHAT -->
            <div id="tab-chat" class="tab-content-custom">
                <div class="card-custom">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <div class="card-title-custom"><i class="fa fa-comments"></i> Canal de Mentoria Direta</div>
                        <small class="text-muted">Partilhe dúvidas e orientações aqui</small>
                    </div>
                    <div class="card-body-custom p-0">
                        <div id="chat-container-ment" style="height: 450px; overflow-y: auto; padding: 20px; background: #f1f5f9;">
                            <?php 
                            $msgQuery = $mysqli->prepare("
                                SELECT m.*, u.nome, u.perfil 
                                FROM mensagens m 
                                JOIN usuarios u ON u.id = m.id_usuario 
                                WHERE m.id_projeto = ? 
                                ORDER BY m.criado_at ASC
                            ");
                            $msgQuery->bind_param('i', $idProjeto);
                            $msgQuery->execute();
                            $mensagens = $msgQuery->get_result()->fetch_all(MYSQLI_ASSOC);
                            
                            if (empty($mensagens)): 
                            ?>
                                <div class="text-center p-5">
                                    <div class="mb-3"><i class="fa fa-comments-blank fa-3x text-muted" style="opacity:0.3"></i></div>
                                    <p class="text-muted">Ainda não existem mensagens neste canal.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($mensagens as $m): 
                                    $isMe = ($m['id_usuario'] == $idUsuario);
                                    $color = ($m['perfil'] == 'mentor' ? '#8b5cf6' : ($m['perfil'] == 'admin' ? 'var(--primary)' : '#64748b'));
                                ?>
                                    <div class="d-flex <?= $isMe ? 'justify-content-end' : '' ?> mb-3">
                                        <div style="max-width: 85%;">
                                            <div class="mb-1" style="font-size: 0.65rem; color: #94a3b8; <?= $isMe ? 'text-align: right;' : '' ?>">
                                                <strong><?= htmlspecialchars($m['nome']) ?></strong> 
                                                <span class="badge" style="background:<?= $color ?>; font-size:0.5rem"><?= strtoupper($m['perfil']) ?></span>
                                            </div>
                                            <div class="p-3 shadow-sm <?= $isMe ? 'bg-primary text-white' : 'bg-white' ?>" 
                                                 style="border-radius: <?= $isMe ? '15px 15px 2px 15px' : '15px 15px 15px 2px' ?>; font-size: 0.9rem; line-height:1.5">
                                                <?= nl2br(htmlspecialchars($m['mensagem'])) ?>
                                                <div class="mt-2" style="font-size: 0.6rem; opacity: 0.8; text-align: right;">
                                                    <?= date('H:i', strtotime($m['criado_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 bg-white border-top">
                            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" class="d-flex gap-2">
                                <input type="hidden" name="action" value="enviar_mensagem">
                                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>#tab-chat">
                                <textarea name="mensagem" class="form-control-custom" rows="1" placeholder="Escreva aqui a sua mensagem..." required style="resize:none; padding:12px"></textarea>
                                <button type="submit" class="btn-primary-custom" style="padding: 10px 20px;">
                                    <i class="fa fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const cont = document.getElementById('chat-container-ment');
                    if(cont) cont.scrollTop = cont.scrollHeight;
                });
            </script>
        </div>

    </div>

    <!-- COLUNA DIREITA: FINANCEIRO + EQUIPA -->
    <div class="col-lg-4">
        <!-- FINANCEIRO -->
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-money-bill-wave"></i> Execução Financeira</div>
            </div>
            <div class="card-body-custom text-center">
                <div class="mb-4">
                    <span class="d-block text-muted mb-1">Total Aprovado</span>
                    <h3 class="fw-bold"><?= number_format($totalAprovado, 0, ',', '.') ?> <small>Kz</small></h3>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:0.8rem">
                        <span>Executado: <?= number_format($totalExecutado, 0, ',', '.') ?> Kz</span>
                        <span class="fw-bold"><?= $percentagemExec ?>%</span>
                    </div>
                    <div class="progress-custom" style="height:12px">
                        <div class="progress-bar-custom" style="width:<?= $percentagemExec ?>%; background:var(--success)"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CONTACTOS DO AUTOR -->
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-user"></i> Autor / Equipa</div>
            </div>
            <div class="card-body-custom">
                <h6 class="fw-bold mb-0"><?= htmlspecialchars($projeto['autor']) ?></h6>
                <p class="text-muted small mb-3">Responsável pelo Projecto</p>
                <div style="display:flex; flex-direction:column; gap:10px">
                    <a href="mailto:<?= $projeto['email_autor'] ?>" class="btn-ghost" style="text-align:left; font-size:0.85rem">
                        <i class="fa fa-envelope me-2"></i> <?= htmlspecialchars($projeto['email_autor']) ?>
                    </a>
                    <?php if($projeto['tel_autor']): ?>
                    <a href="tel:<?= $projeto['tel_autor'] ?>" class="btn-ghost" style="text-align:left; font-size:0.85rem">
                        <i class="fa fa-phone me-2"></i> <?= htmlspecialchars($projeto['tel_autor']) ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAREFA -->
<div class="modal fade" id="modalTarefa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/mentor_action.php" method="POST">
                <input type="hidden" name="action" value="criar_tarefa">
                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-tasks me-2"></i> Atribuir Nova Tarefa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Título da Tarefa *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Finalizar Plano de Negócios">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Descrição / Instruções</label>
                        <textarea name="descricao" class="form-control-custom" rows="3"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Data Limite (Prazo)</label>
                            <input type="date" name="data_limite" class="form-control-custom">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Prioridade</label>
                            <select name="prioridade" class="form-select-custom">
                                <option value="baixa">Baixa</option>
                                <option value="media" selected>Média</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Criar Tarefa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL AVALIAÇÃO -->
<div class="modal fade" id="modalAvaliacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/mentor_action.php" method="POST">
                <input type="hidden" name="action" value="avaliar_progresso">
                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-star me-2"></i> Avaliação de Acompanhamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label-custom">Período de Referência *</label>
                            <input type="text" name="periodo" class="form-control-custom" required placeholder="Ex: Abril 2026, Semana 1...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Progresso Geral (0-100) *</label>
                            <input type="number" name="progresso_geral" class="form-control-custom" required min="0" max="100" value="50">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Feedback Detalhado *</label>
                        <textarea name="feedback" class="form-control-custom" rows="3" required placeholder="Como descreve a evolução da equipa neste período?"></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Pontos Fortes</label>
                            <textarea name="pontos_fortes" class="form-control-custom" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Pontos a Melhorar</label>
                            <textarea name="pontos_melhorar" class="form-control-custom" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Recomendações Próximos Passos</label>
                        <textarea name="recomendacoes" class="form-control-custom" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom" style="background:#8B5CF6; border:none">Submeter Avaliação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL REUNIÃO -->
<div class="modal fade" id="modalReuniao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/mentor_action.php" method="POST">
                <input type="hidden" name="action" value="agendar_reuniao">
                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-calendar-plus me-2"></i> Agendar Reunião</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Assunto / Pauta *</label>
                        <input type="text" name="titulo" class="form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Data e Hora *</label>
                        <input type="datetime-local" name="data_reuniao" class="form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Link (Virtual) ou Local (Físico)</label>
                        <input type="text" name="link_reuniao" class="form-control-custom" placeholder="Ex: Google Meet ou Sala 2">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom" style="background:#d97706; border:none">Agendar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(evt, tabId) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content-custom");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].classList.remove("active");
    }
    tablinks = document.getElementsByClassName("nav-link-custom");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    document.getElementById(tabId).classList.add("active");
    evt.currentTarget.classList.add("active");
}
</script>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>

