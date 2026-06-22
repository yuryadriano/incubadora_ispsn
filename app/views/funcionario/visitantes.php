<?php
// app/views/funcionario/visitantes.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['funcionario', 'admin', 'superadmin']);

$tituloPagina = 'Controlo de Visitantes';
$paginaActiva = 'visitantes';

// --- KPIs ---
// 1. Visitantes presentes
$pres = $mysqli->query("SELECT COUNT(*) n FROM visitantes WHERE status = 'presente'")->fetch_assoc()['n'] ?? 0;
// 2. Entradas hoje
$hoje = $mysqli->query("SELECT COUNT(*) n FROM visitantes WHERE DATE(data_entrada) = CURDATE()")->fetch_assoc()['n'] ?? 0;
// 3. Média de visitas (por dia de atividade)
$diasAtivosQuery = $mysqli->query("SELECT COUNT(DISTINCT DATE(data_entrada)) d FROM visitantes");
$diasAtivos = $diasAtivosQuery ? (int)$diasAtivosQuery->fetch_assoc()['d'] : 1;
$diasAtivos = max(1, $diasAtivos);
$totalVisitas = $mysqli->query("SELECT COUNT(*) n FROM visitantes")->fetch_assoc()['n'] ?? 0;
$mediaVisitas = round($totalVisitas / $diasAtivos, 1);

// --- BUSCAR VISITANTES PRESENTES ---
$presentes = [];
$res = $mysqli->query("
    SELECT v.*, u.nome as visitado_nome, u.perfil as visitado_perfil
    FROM visitantes v
    LEFT JOIN usuarios u ON u.id = v.id_usuario_visitado
    WHERE v.status = 'presente'
    ORDER BY v.data_entrada DESC
");
if ($res) while ($row = $res->fetch_assoc()) $presentes[] = $row;

// --- BUSCAR HISTÓRICO (ÚLTIMOS 50) ---
$historico = [];
$resH = $mysqli->query("
    SELECT v.*, u.nome as visitado_nome, u.perfil as visitado_perfil
    FROM visitantes v
    LEFT JOIN usuarios u ON u.id = v.id_usuario_visitado
    WHERE v.status = 'saiu'
    ORDER BY v.data_entrada DESC
    LIMIT 50
");
if ($resH) while ($row = $resH->fetch_assoc()) $historico[] = $row;

// --- BUSCAR UTILIZADORES PARA O DROPDOWN (STARTUPS/MENTORES) ---
$usuarios = [];
$resU = $mysqli->query("SELECT id, nome, perfil FROM usuarios WHERE activo = 1 ORDER BY nome ASC");
if ($resU) while ($row = $resU->fetch_assoc()) $usuarios[] = $row;

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- FLASH MESSAGES -->
<?php if (isset($_SESSION['flash_ok'])): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4"><?= htmlspecialchars($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['flash_erro'])): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4"><?= htmlspecialchars($_SESSION['flash_erro']); unset($_SESSION['flash_erro']); ?></div>
<?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <div class="page-header-title" style="font-size: 1.4rem;">
            <i class="fa fa-users-line me-2" style="color:var(--primary)"></i>
            Portaria / Controlo de Visitantes
        </div>
        <div class="page-header-sub">Registo de entradas e saídas de convidados externos no coworking do ISPSN.</div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalCheckin">
        <i class="fa fa-plus me-2"></i> Registar Entrada (Check-In)
    </button>
</div>

<!-- KPI GRID -->
<div class="kpi-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="kpi-card" style="--kpi-color: var(--success)">
        <div class="kpi-icon"><i class="fa fa-door-open"></i></div>
        <div class="kpi-value"><?= $pres ?></div>
        <div class="kpi-label">Presentes no Coworking</div>
        <div class="kpi-trend trend-up"><span class="badge bg-success-subtle text-success">Ativos</span></div>
    </div>
    <div class="kpi-card" style="--kpi-color: var(--primary)">
        <div class="kpi-icon"><i class="fa fa-user-plus"></i></div>
        <div class="kpi-value"><?= $hoje ?></div>
        <div class="kpi-label">Entradas Registadas Hoje</div>
        <div class="kpi-trend" style="color:var(--primary)"><i class="fa fa-calendar-day me-1"></i>Hoje</div>
    </div>
    <div class="kpi-card" style="--kpi-color: var(--warning)">
        <div class="kpi-icon"><i class="fa fa-chart-simple"></i></div>
        <div class="kpi-value"><?= $mediaVisitas ?></div>
        <div class="kpi-label">Média de Visitas por Dia</div>
        <div class="kpi-trend text-warning"><i class="fa fa-calculator me-1"></i>Histórico</div>
    </div>
</div>

<!-- CONTAINER PRINCIPAL -->
<div class="row g-4">
    <!-- VISITANTES ATIVOS -->
    <div class="col-lg-7">
        <div class="card-custom h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <div class="card-title-custom"><i class="fa fa-people-arrows-left-right text-success"></i> Visitantes no Espaço</div>
                <span class="badge bg-success-subtle text-success rounded-pill px-3"><?= count($presentes) ?> ativos</span>
            </div>
            
            <div class="card-body-custom">
                <?php if (empty($presentes)): ?>
                    <div class="text-center p-5">
                        <i class="fa fa-users fa-3x text-muted opacity-25 mb-3"></i>
                        <h6 class="fw-bold">Nenhum visitante presente</h6>
                        <p class="text-muted small">Todos os visitantes registaram saída ou não há visitas ativas.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Convidado</th>
                                    <th>Doc. Identidade</th>
                                    <th>Visitando</th>
                                    <th>Entrada</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($presentes as $v): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-slate-800"><?= htmlspecialchars($v['nome']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($v['empresa_motivo'] ?? 'Visita geral') ?></small>
                                    </td>
                                    <td><code class="small text-dark"><?= htmlspecialchars($v['documento_identidade'] ?? 'N/A') ?></code></td>
                                    <td>
                                        <?php if ($v['id_usuario_visitado']): ?>
                                            <div class="fw-bold" style="font-size:0.85rem"><?= htmlspecialchars($v['visitado_nome']) ?></div>
                                            <span class="badge bg-light text-secondary" style="font-size:0.6rem; text-transform:uppercase;"><?= htmlspecialchars($v['visitado_perfil']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold" style="font-size:0.85rem"><?= date('H:i', strtotime($v['data_entrada'])) ?></div>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($v['data_entrada'])) ?></small>
                                    </td>
                                    <td>
                                        <form method="post" action="/incubadora_ispsn/app/controllers/operacoes_action.php">
                                            <input type="hidden" name="action" value="registar_saida_visitante">
                                            <input type="hidden" name="id_visitante" value="<?= $v['id'] ?>">
                                            <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/funcionario/visitantes.php">
                                            <button type="submit" class="btn btn-outline-danger btn-sm fw-bold px-3 rounded-3" style="font-size:0.75rem;">
                                                <i class="fa fa-door-closed me-1"></i> Saída
                                            </button>
                                        </form>
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

    <!-- HISTÓRICO DE VISITAS -->
    <div class="col-lg-5">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-history"></i> Histórico de Acessos</div>
            </div>
            <div class="card-body-custom" style="max-height: 480px; overflow-y: auto;">
                <?php if (empty($historico)): ?>
                    <p class="text-muted text-center p-4">Sem registos no histórico.</p>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($historico as $h): 
                            $duracao = 'N/A';
                            if ($h['data_entrada'] && $h['data_saida']) {
                                $diff = strtotime($h['data_saida']) - strtotime($h['data_entrada']);
                                $horas = floor($diff / 3600);
                                $minutos = floor(($diff % 3600) / 60);
                                $duracao = ($horas > 0 ? "{$horas}h " : "") . "{$minutos}m";
                            }
                        ?>
                            <div class="p-3 border rounded-4 bg-white" style="border-color:var(--border);">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold" style="font-size:0.9rem; color:var(--text-primary);"><?= htmlspecialchars($h['nome']) ?></div>
                                        <small class="text-muted d-block" style="font-size:0.75rem;"><?= htmlspecialchars($h['empresa_motivo'] ?? 'Visita geral') ?></small>
                                    </div>
                                    <span class="badge bg-light text-dark small"><?= date('d/m', strtotime($h['data_entrada'])) ?></span>
                                </div>
                                <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center" style="font-size:0.75rem; color:var(--text-secondary); border-color:#f1f5f9 !important;">
                                    <span>Visitou: <strong><?= htmlspecialchars($h['visitado_nome'] ?? 'Portaria Geral') ?></strong></span>
                                    <span>Tempo: <strong class="text-success"><i class="fa-regular fa-clock me-1"></i><?= $duracao ?></strong></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CHECK-IN -->
<div class="modal fade" id="modalCheckin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/operacoes_action.php">
                <input type="hidden" name="action" value="registar_visitante">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/funcionario/visitantes.php">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-user-plus me-2"></i> Registar Entrada de Visitante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Nome do Visitante *</label>
                        <input type="text" name="nome" class="form-control-custom" required placeholder="Nome completo do convidado">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Documento de Identidade (Nº Bilhete/Passaporte) *</label>
                        <input type="text" name="documento" class="form-control-custom" required placeholder="Ex: 00412356LA042">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Empresa / Motivo da Visita *</label>
                        <input type="text" name="motivo" class="form-control-custom" required placeholder="Ex: Reunião comercial, Mentoria, etc.">
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Utilizador Visitado *</label>
                        <select name="id_visitado" class="form-select form-control-custom" required>
                            <option value="">— Selecionar Pessoa Visitada —</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nome']) ?> (<?= ucfirst($u['perfil']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Confirmar Entrada (Check-In)</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
