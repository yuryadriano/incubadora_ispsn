<?php
require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

if (!in_array($_SESSION['usuario_perfil'], ['admin', 'superadmin', 'funcionario'])) {
    header("Location: /incubadora_ispsn/public/index.php");
    exit;
}

$tituloPagina = "Centro de Operações Físicas";
include __DIR__ . '/../partials/_layout.php';

// --- BUSCA DE DADOS PARA AS ABAS ---
$equipv = $mysqli->query("SELECT * FROM equipamentos ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$visitHoje = $mysqli->query("SELECT v.*, u.nome as visitado FROM visitantes v LEFT JOIN usuarios u ON u.id = v.id_usuario_visitado WHERE v.status = 'presente' ORDER BY v.data_entrada DESC")->fetch_all(MYSQLI_ASSOC);
$todosUsuarios = $mysqli->query("SELECT id, nome FROM usuarios WHERE activo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);

// --- BUSCAR RESERVAS PENDENTES ---
$reservasPendentes = $mysqli->query("
    SELECT r.*, e.nome as espaco_nome, u.nome as usuario_nome
    FROM reservas_espaco r
    JOIN espacos e ON e.id = r.id_espaco
    JOIN usuarios u ON u.id = r.id_usuario
    WHERE r.status = 'pendente'
    ORDER BY r.data_reserva ASC, r.hora_inicio ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<style>
    :root {
        --glass: rgba(255, 255, 255, 0.9);
        --glass-border: rgba(255, 255, 255, 0.3);
    }
    .reception-header {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        color: white;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .stat-card-premium {
        background: var(--glass);
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 20px;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .stat-card-premium:hover { transform: translateY(-5px); }
    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        font-size: 1.2rem;
    }
    .nav-reception-modern {
        background: #f8fafc;
        padding: 8px;
        border-radius: 14px;
        display: inline-flex;
        gap: 5px;
        border: 1px solid #e2e8f0;
        margin-bottom: 25px;
    }
    .nav-reception-modern .nav-link {
        border: none;
        border-radius: 10px;
        padding: 10px 20px;
        font-weight: 600;
        font-size: 0.85rem;
        color: #64748b;
        transition: all 0.2s;
    }
    .nav-reception-modern .nav-link.active {
        background: white;
        color: var(--primary);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .table-premium thead th {
        background: #f8fafc;
        text-transform: uppercase;
        font-size: 0.65rem;
        letter-spacing: 0.05em;
        font-weight: 700;
        color: #64748b;
        border-top: none;
        padding: 15px 20px;
    }
    .table-premium tbody td { padding: 18px 20px; border-bottom: 1px solid #f1f5f9; }
    .status-pill {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .avatar-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .pulse-online {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
</style>

<!-- HEADER MODERNO -->
<div class="reception-header">
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <h3 class="fw-800 mb-1">Centro de Operações Físicas</h3>
            <p class="text-white text-opacity-75 mb-0">Gestão integrada de espaços, património e controlo de acessos em tempo real.</p>
        </div>
        <div class="col-md-5 text-end">
            <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-10 p-2 rounded-pill px-3">
                <div class="pulse-online"></div>
                <span style="font-size:0.75rem; font-weight:600">SISTEMA LIVE MONITORING</span>
            </div>
        </div>
    </div>

    <!-- CARDS DE ESTATÍSTICA GLASS -->
    <div class="row g-3">
        <div class="col-md-3">
            <div class="stat-card-premium">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fa fa-door-open"></i></div>
                <div class="small text-muted font-weight-600">Total Espaços</div>
                <div class="h4 fw-800 mb-0 text-dark" id="statTotal">0</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-premium">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fa fa-users"></i></div>
                <div class="small text-muted font-weight-600">Capacidade Total</div>
                <div class="h4 fw-800 mb-0 text-dark" id="statCapTotal">0</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-premium">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fa fa-user-clock"></i></div>
                <div class="small text-muted font-weight-600">Ocupação Atual</div>
                <div class="h4 fw-800 mb-0 text-dark" id="statInUse">0</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-premium">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fa fa-chart-line"></i></div>
                <div class="small text-muted font-weight-600">Taxa de Uso</div>
                <div class="h4 fw-800 mb-0 text-dark" id="statFree">0%</div>
            </div>
        </div>
    </div>
</div>

<div class="nav nav-tabs nav-reception-modern" id="receptionTab" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="espacos-tab" data-bs-toggle="tab" data-bs-target="#espacos" type="button"><i class="fa fa-building-user me-2"></i>Ocupação Escritórios</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="equipamentos-tab" data-bs-toggle="tab" data-bs-target="#equipamentos" type="button"><i class="fa fa-laptop-code me-2"></i>Inventário Assets</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="solicitacoes-tab" data-bs-toggle="tab" data-bs-target="#solicitacoes" type="button">
            <i class="fa fa-clock-rotate-left me-2"></i>Solicitações
            <?php if(count($reservasPendentes) > 0): ?>
                <span class="badge bg-danger rounded-pill ms-1" style="font-size:0.6rem"><?= count($reservasPendentes) ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="visitantes-tab" data-bs-toggle="tab" data-bs-target="#visitantes" type="button"><i class="fa fa-id-card-clip me-2"></i>Visitantes</button>
    </li>
</ul>

<div class="tab-content" id="receptionTabContent">
    
    <!-- ABA 1: ESPAÇOS -->
    <div class="tab-pane fade show active" id="espacos" role="tabpanel">
        <div class="card-custom border-0 shadow-sm overflow-hidden" style="background:white; border-radius:18px">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center p-4">
                <div>
                    <h5 class="fw-800 mb-0 text-dark">Monitor de Coworking</h5>
                    <p class="small text-muted mb-0">Listagem de postos de trabalho e salas de reunião.</p>
                </div>
                <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNovoEspaco">
                    <i class="fa fa-plus-circle me-2"></i> Adicionar Recurso
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-premium mb-0">
                    <thead>
                        <tr>
                            <th>Recurso / Espaço</th>
                            <th>Status de Ocupação</th>
                            <th>Ocupante Atual</th>
                            <th>Horário</th>
                            <th class="text-end">Operações</th>
                        </tr>
                    </thead>
                    <tbody id="occupancyList" class="border-top-0">
                        <tr><td colspan="5" class="text-center py-5">
                            <div class="spinner-border text-primary spinner-border-sm me-2"></div> Sincronizando dados mestre...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ABA 2: EQUIPAMENTOS -->
    <div class="tab-pane fade" id="equipamentos" role="tabpanel">
        <!-- (Conteúdo existente de equipamentos...) -->
        <div class="card-custom border-0 shadow-sm">
            <div class="card-header-custom bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <div class="card-title-custom"><i class="fa fa-boxes-stacked me-2 text-primary"></i> Inventário de Hardware</div>
                 <button class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalNovoEquipamento">
                    <i class="fa fa-plus me-1"></i> Novo Asset
                </button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="bg-light small text-uppercase">
                        <tr>
                            <th class="ps-4">Item</th>
                            <th>Série/Património</th>
                            <th>Estado</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($equipv as $eq): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= htmlspecialchars($eq['nome']) ?></td>
                            <td><code><?= htmlspecialchars($eq['codigo_patrimonio']) ?></code></td>
                            <td><span class="badge bg-light text-dark rounded-pill"><?= ucfirst($eq['estado']) ?></span></td>
                            <td><button class="btn btn-sm btn-light">Emprestar</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ABA 3: SOLICITACOES PENDENTES -->
    <div class="tab-pane fade" id="solicitacoes" role="tabpanel">
        <div class="card-custom border-0 shadow-sm">
            <div class="card-header-custom bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <div class="card-title-custom"><i class="fa fa-clock me-2 text-warning"></i> Pedidos de Reserva Pendentes</div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="bg-light small text-uppercase">
                        <tr>
                            <th class="ps-4">Utilizador</th>
                            <th>Espaço</th>
                            <th>Data e Hora</th>
                            <th>Objetivo</th>
                            <th class="pe-4 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reservasPendentes)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">Não há solicitações pendentes no momento.</td></tr>
                        <?php else: ?>
                            <?php foreach($reservasPendentes as $rp): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold"><?= htmlspecialchars($rp['usuario_nome']) ?></div>
                                        <div class="small text-muted">ID: #<?= $rp['id_usuario'] ?></div>
                                    </td>
                                    <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($rp['espaco_nome']) ?></span></td>
                                    <td>
                                        <div class="small fw-bold"><?= date('d/m/Y', strtotime($rp['data_reserva'])) ?></div>
                                        <div class="small text-muted"><?= substr($rp['hora_inicio'],0,5) ?> - <?= substr($rp['hora_fim'],0,5) ?></div>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($rp['objetivo']) ?></td>
                                    <td class="pe-4 text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST">
                                                <input type="hidden" name="action" value="gestao_reserva">
                                                <input type="hidden" name="id_reserva" value="<?= $rp['id'] ?>">
                                                <input type="hidden" name="novo_status" value="confirmada">
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">Confirmar</button>
                                            </form>
                                            <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST">
                                                <input type="hidden" name="action" value="gestao_reserva">
                                                <input type="hidden" name="id_reserva" value="<?= $rp['id'] ?>">
                                                <input type="hidden" name="novo_status" value="cancelada">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">Recusar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ABA 3: VISITANTES -->
    <div class="tab-pane fade" id="visitantes" role="tabpanel">
        <div class="card-custom border-0 shadow-sm">
             <div class="card-header-custom bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <div class="card-title-custom"><i class="fa fa-id-badge me-2 text-primary"></i> Log de Visitantes Externos</div>
                <button class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalNovoVisitante">
                    <i class="fa fa-user-plus me-1"></i> Registar Entrada
                </button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th class="ps-4">Nome</th><th>Motivo</th><th>Entrada</th><th class="pe-4">Saída</th></tr></thead>
                    <tbody>
                        <?php foreach($visitHoje as $v): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= htmlspecialchars($v['nome']) ?></td>
                            <td><?= htmlspecialchars($v['empresa_motivo']) ?></td>
                            <td><?= date('H:i', strtotime($v['data_entrada'])) ?></td>
                            <td class="pe-4"><button class="btn btn-sm btn-outline-danger shadow-sm">Finalizar</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: NOVO RECURSO FÍSICO -->
<div class="modal fade" id="modalNovoEspaco" tabindex="-1">
    <div class="modal-dialog">
        <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="adicionar_espaco">
            <div class="modal-header">
                <h5 class="modal-title">Registar Novo Espaço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nome do Espaço</label>
                    <input type="text" name="nome" class="form-control" placeholder="Ex: Mesa 05, Sala X" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="mesa">Mesa de Trabalho</option>
                        <option value="sala_reuniao">Sala de Reunião</option>
                        <option value="auditorio">Auditório</option>
                        <option value="laboratorio">Laboratório</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Capacidade (Pessoas)</label>
                    <input type="number" name="capacidade" class="form-control" value="1" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Gravar Recurso</button>
            </div>
        </form>
    </div>
</div>

<script>
    async function refreshData() {
        try {
            const response = await fetch('/incubadora_ispsn/app/controllers/get_ocupacao.php');
            const data = await response.json();
            renderList(data);
        } catch (e) { console.error("Sync error"); }
    }

    function renderList(data) {
        let html = '';
        let inUse = 0;
        let capTotal = 0;

        data.forEach(item => {
            const isOcupado = item.id_reserva !== null;
            const cap = parseInt(item.capacidade) || 1;
            capTotal += cap;
            if(isOcupado) inUse++;

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-light p-2 rounded-3 text-primary">
                                <i class="fa ${item.tipo === 'mesa' ? 'fa-desktop' : 'fa-handshake'}"></i>
                            </div>
                            <div>
                                <div class="fw-800 text-dark mb-0">${item.espaco_nome}</div>
                                <div class="small text-muted text-uppercase fw-600" style="font-size:0.6rem; letter-spacing:0.1em">${item.tipo}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="status-pill ${isOcupado ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'}">
                            <span class="status-indicator" style="background:${isOcupado ? '#ef4444' : '#22c55e'}; margin-right:0"></span>
                            ${isOcupado ? 'OCUPADO' : 'LIVRE'}
                        </div>
                    </td>
                    <td>
                        ${isOcupado 
                            ? `<div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle">${(item.usuario_nome || 'U').charAt(0).toUpperCase()}</div>
                                <div class="fw-bold text-dark" style="font-size:0.85rem">${item.usuario_nome}</div>
                               </div>` 
                            : '<span class="text-muted opacity-50 small">—</span>'}
                    </td>
                    <td>
                        ${isOcupado 
                            ? `<span class="badge bg-light text-dark border fw-bold">${item.hora_inicio.substr(0,5)} — ${item.hora_fim.substr(0,5)}</span>` 
                            : '<span class="small text-muted">Aguardando...</span>'}
                    </td>
                    <td class="text-end">
                        ${isOcupado && !item.check_in_at 
                            ? `<form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST">
                                <input type="hidden" name="action" value="check_in">
                                <input type="hidden" name="id_reserva" value="${item.id_reserva}">
                                <button type="submit" class="btn btn-sm btn-dark rounded-3 px-3 shadow-sm" style="font-size:0.75rem">
                                    Confirmar <i class="fa fa-arrow-right ms-1"></i>
                                </button>
                               </form>`
                            : isOcupado 
                                ? `<span class="text-success small fw-800 d-inline-flex align-items-center gap-1">
                                    <i class="fa fa-check-circle" style="font-size:1rem"></i> Registado
                                   </span>`
                                : `<button class="btn btn-sm btn-outline-primary rounded-3 text-uppercase fw-800" style="font-size:0.65rem">Reservar</button>`
                        }
                    </td>
                </tr>
            `;
        });

        document.getElementById('occupancyList').innerHTML = html;
        document.getElementById('statTotal').textContent = data.length;
        document.getElementById('statCapTotal').textContent = capTotal;
        document.getElementById('statInUse').textContent = inUse;
        document.getElementById('statFree').textContent = Math.round((inUse/data.length)*100) + '%';
    }

    setInterval(refreshData, 10000);
    refreshData();
</script>

<?php include __DIR__ . '/../partials/_layout_end.php'; ?>