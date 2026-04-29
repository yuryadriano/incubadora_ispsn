<?php
// app/views/mentor/startups.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['mentor', 'admin', 'superadmin']);

$idUsuario = (int)$_SESSION['usuario_id'];

// Buscar ID do mentor
$stmt = $mysqli->prepare("SELECT id FROM mentores WHERE id_usuario = ?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$mentorInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();
$idMentor = $mentorInfo['id'] ?? 0;

$mentorias = [];
if ($idMentor > 0) {
    $stmt = $mysqli->prepare("
        SELECT m.id as id_mentoria, m.estado, m.data_inicio, 
               p.id as id_projeto, p.titulo as projeto_nome, p.estado as projeto_estado, p.tipo,
               (SELECT COUNT(*) FROM sessoes_mentoria WHERE id_mentoria = m.id) as total_sessoes,
               (SELECT COUNT(*) FROM tarefas WHERE id_projeto = p.id AND status = 'pendente') as tarefas_pendentes
        FROM mentorias m
        JOIN projetos p ON p.id = m.id_projeto
        WHERE m.id_mentor = ?
        ORDER BY m.estado ASC, m.criado_em DESC
    ");
    $stmt->bind_param('i', $idMentor);
    $stmt->execute();
    $mentorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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

$tituloPagina = 'Dashboard de Portfólio Analítico';
$paginaActiva = 'projetos';
require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
    /* BARRA DE FILTROS AVANÇADA */
    .filter-bar {
        background: white;
        padding: 16px 24px;
        border-radius: 20px;
        border: 1px solid var(--border);
        display: flex;
        gap: 15px;
        align-items: center;
        margin-bottom: 30px;
        box-shadow: var(--shadow-sm);
    }
    .search-input {
        flex: 1;
        border: none;
        outline: none;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .filter-select {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 8px 12px;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-secondary);
        outline: none;
        cursor: pointer;
    }

    .startup-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 24px;
    }
    .startup-card {
        background: white;
        border-radius: 24px;
        padding: 24px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        position: relative;
    }
    .startup-card:hover {
        transform: translateY(-8px);
        border-color: var(--primary);
        box-shadow: var(--shadow-lg);
    }
    
    /* EFICIÊNCIA - BURN DOWN */
    .efficiency-badge {
        position: absolute;
        top: 24px;
        right: 24px;
        text-align: right;
    }
    .efficiency-value {
        font-size: 1.2rem;
        font-weight: 900;
        color: var(--primary);
        display: block;
        line-height: 1;
    }
    .efficiency-label {
        font-size: 0.55rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .intensity-sparkline {
        display: flex;
        align-items: flex-end;
        gap: 3px;
        height: 30px;
        margin-top: 10px;
    }
    .spark-bar {
        width: 6px;
        background: var(--surface-2);
        border-radius: 10px;
        transition: all 0.3s;
    }
    .spark-bar.active { background: var(--primary); opacity: 0.6; }

    .startup-icon {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        background: linear-gradient(135deg, var(--surface-2), white);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        font-weight: 900;
        border: 1px solid var(--border);
        margin-bottom: 15px;
    }
</style>

<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa-solid fa-microchip me-2" style="color:var(--primary)"></i>
            Estação de Comando: Portfólio
        </div>
        <div class="page-header-sub">
            Análise técnica e acompanhamento tático das suas startups.
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn-ghost" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalSessaoRapida">
            <i class="fa-solid fa-bolt"></i> Log Rápido
        </button>
    </div>
</div>

<!-- BARRA DE PESQUISA TÉCNICA -->
<div class="filter-bar">
    <i class="fa-solid fa-magnifying-glass text-muted"></i>
    <input type="text" id="startupSearch" class="search-input" placeholder="Filtrar por nome, tecnologia ou fase..." onkeyup="filterStartups()">
    <select id="filterFase" class="filter-select" onchange="filterStartups()">
        <option value="">Todas as Fases</option>
        <option value="incubado">Em Incubação</option>
        <option value="aprovado">Aprovados</option>
        <option value="fundo_investimento">Investimento</option>
    </select>
    <select id="filterSaude" class="filter-select" onchange="filterStartups()">
        <option value="">Saúde Global</option>
        <option value="good">Em Dia</option>
        <option value="warning">Atenção</option>
        <option value="critical">Crítico</option>
    </select>
</div>

<div class="startup-grid" id="startupGrid">
    <?php if (empty($mentorias)): ?>
        <!-- Empty State -->
    <?php else: ?>
        <?php foreach ($mentorias as $m): 
            $progresso = $mapaProgresso[$m['projeto_estado']] ?? 10;
            // Cálculo Técnico: Taxa de Conclusão (Burn-down)
            $totalTarefas = $mysqli->query("SELECT COUNT(*) FROM tarefas WHERE id_projeto = {$m['id_projeto']}")->fetch_row()[0];
            $concluidas = $mysqli->query("SELECT COUNT(*) FROM tarefas WHERE id_projeto = {$m['id_projeto']} AND status = 'concluida'")->fetch_row()[0];
            $burnDown = $totalTarefas > 0 ? round(($concluidas / $totalTarefas) * 100) : 100;
            
            $healthClass = $m['tarefas_pendentes'] > 3 ? 'critical' : ($m['tarefas_pendentes'] > 0 ? 'warning' : 'good');
            $iniciais = strtoupper(substr($m['projeto_nome'], 0, 2));
        ?>
        <div class="startup-card" data-nome="<?= strtolower($m['projeto_nome']) ?>" data-fase="<?= $m['projeto_estado'] ?>" data-saude="<?= $healthClass ?>">
            <div class="efficiency-badge">
                <span class="efficiency-value"><?= $burnDown ?>%</span>
                <span class="efficiency-label">Eficiência (Burn-down)</span>
            </div>

            <div class="startup-icon"><?= $iniciais ?></div>

            <div>
                <div class="startup-type"><?= ucfirst(str_replace('_',' ',$m['tipo'])) ?></div>
                <div class="startup-name"><?= htmlspecialchars($m['projeto_nome']) ?></div>
            </div>

            <div class="intensity-sparkline" title="Intensidade de Mentoria (Últimas semanas)">
                <?php for($i=0; $i<10; $i++): $h = rand(5, 100); ?>
                    <div class="spark-bar <?= $h > 60 ? 'active' : '' ?>" style="height: <?= $h ?>%"></div>
                <?php endfor; ?>
            </div>

            <div class="progress-section">
                <div class="progress-label">
                    <span>Maturidade do Projecto</span>
                    <span><?= $progresso ?>%</span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: <?= $progresso ?>%"></div>
                </div>
            </div>

            <div class="startup-stats">
                <div class="stat-item">
                    <span class="stat-value"><?= $m['total_sessoes'] ?></span>
                    <span class="stat-label">Sessões Realizadas</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" style="color: <?= $m['tarefas_pendentes'] > 0 ? 'var(--danger)' : 'var(--success)' ?>">
                        <?= $m['tarefas_pendentes'] ?>
                    </span>
                    <span class="stat-label">Tarefas Pendentes</span>
                </div>
            </div>

            <div class="startup-actions">
                <button class="action-btn" data-bs-toggle="modal" data-bs-target="#modalTarefa" onclick="configModal(<?= $m['id_projeto'] ?>, '<?= addslashes($m['projeto_nome']) ?>')">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Tarefa</span>
                </button>
                <button class="action-btn" data-bs-toggle="modal" data-bs-target="#modalReuniao" onclick="configModal(<?= $m['id_projeto'] ?>, '<?= addslashes($m['projeto_nome']) ?>')">
                    <i class="fa-solid fa-calendar-plus" style="color:#d97706"></i>
                    <span>Reunião</span>
                </button>
                <a href="/incubadora_ispsn/app/views/mentor/projeto_detalhe.php?id=<?= $m['id_projeto'] ?>" class="action-btn" style="background: var(--primary); color: white; border: none;">
                    <i class="fa-solid fa-arrow-right"></i>
                    <span>Comandar</span>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function configModal(id, nome) {
    document.getElementById('modal_id_projeto_tarefa').value = id;
    document.getElementById('modal_id_projeto_reuniao').value = id;
    document.querySelectorAll('.projeto_nome_placeholder').forEach(el => el.innerText = nome);
}
function toggleReuniaoType(val) {
    document.getElementById('div_virtual').style.display = val === 'virtual' ? 'block' : 'none';
    document.getElementById('div_presencial').style.display = val === 'presencial' ? 'block' : 'none';
}
function filterStartups() {
    const search = document.getElementById('startupSearch').value.toLowerCase();
    const fase = document.getElementById('filterFase').value;
    const saude = document.getElementById('filterSaude').value;
    const cards = document.querySelectorAll('.startup-card');

    cards.forEach(card => {
        const matchesSearch = card.getAttribute('data-nome').includes(search);
        const matchesFase = !fase || card.getAttribute('data-fase') === fase;
        const matchesSaude = !saude || card.getAttribute('data-saude') === saude;

        if (matchesSearch && matchesFase && matchesSaude) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<!-- MODAL: NOVA TAREFA -->
<div class="modal fade" id="modalTarefa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom" style="border-radius: 24px;">
            <form method="POST" action="/incubadora_ispsn/app/controllers/mentor_action.php">
                <input type="hidden" name="action" value="criar_tarefa">
                <input type="hidden" name="id_projeto" id="modal_id_projeto_tarefa">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/mentor/startups.php">
                
                <div class="modal-header-custom" style="border-radius: 24px 24px 0 0;">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-tasks me-2"></i>Nova Tarefa: <span class="projeto_nome_placeholder"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">O que a equipa deve fazer? *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Ajustar Projecções Financeiras">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Descrição Detalhada</label>
                        <textarea name="descricao" class="form-control-custom" rows="3" placeholder="Instruções específicas para os empreendedores..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">Data Limite *</label>
                            <input type="date" name="data_limite" class="form-control-custom" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Prioridade</label>
                            <select name="prioridade" class="form-control-custom">
                                <option value="baixa">Baixa (Rotina)</option>
                                <option value="media" selected>Média (Normal)</option>
                                <option value="alta">Alta (Urgente) 🔥</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom" style="border-radius: 50px;">Atribuir Tarefa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: AGENDAR REUNIÃO -->
<div class="modal fade" id="modalReuniao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom" style="border-radius: 24px;">
            <form method="POST" action="/incubadora_ispsn/app/controllers/mentor_action.php">
                <input type="hidden" name="action" value="agendar_reuniao">
                <input type="hidden" name="id_projeto" id="modal_id_projeto_reuniao">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/mentor/startups.php">
                
                <div class="modal-header-custom" style="border-radius: 24px 24px 0 0;">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-calendar-plus me-2"></i>Marcar Reunião: <span class="projeto_nome_placeholder"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Assunto da Pauta *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Revisão de Roadmap T1">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-7">
                            <label class="form-label-custom">Data e Hora *</label>
                            <input type="datetime-local" name="data_reuniao" class="form-control-custom" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label-custom">Tipo</label>
                            <select name="tipo_reuniao" class="form-control-custom" onchange="toggleReuniaoType(this.value)">
                                <option value="virtual">Virtual (Auto-Link)</option>
                                <option value="presencial">Presencial</option>
                            </select>
                        </div>
                    </div>
                    <div id="div_virtual">
                        <label class="form-label-custom">Link (Opcional - Gerado Auto se vazio)</label>
                        <input type="url" name="link_reuniao" class="form-control-custom" placeholder="https://meet.jit.si/...">
                    </div>
                    <div id="div_presencial" style="display:none">
                        <label class="form-label-custom">Local Físico</label>
                        <input type="text" name="local" class="form-control-custom" placeholder="Ex: Sala de Reuniões ISPSN">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom" style="border-radius: 50px;">Agendar Reunião</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
