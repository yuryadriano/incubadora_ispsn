<?php
// app/views/utilizador/reservas.php
// Catálogo Interativo de Espaços & Gestão de Reservas — Incubadora ISPSN

require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$realPerfil     = $_SESSION['usuario_perfil'] ?? 'utilizador';
$isAdminOrSuper = in_array($realPerfil, ['admin', 'superadmin'], true);
$isAdminPreview = ($isAdminOrSuper && !empty($_GET['id_usuario']));

if ($isAdminPreview) {
    $idUsuario = (int)$_GET['id_usuario'];
    $stmtU = $mysqli->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1");
    if ($stmtU) {
        $stmtU->bind_param('i', $idUsuario);
        $stmtU->execute();
        $uRow = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();
        $nomeUsuario = $uRow['nome'] ?? 'Estudante';
    }
    $perfil = 'utilizador';
} else {
    $idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
}

$userParam    = $isAdminPreview ? ('?id_usuario=' . (int)$idUsuario) : '';
$redirectSelf = '/incubadora_ispsn/app/views/utilizador/reservas.php' . $userParam;

$paginaActiva = 'reservas';
$tituloPagina = 'Reservas de Espaço';

// Buscar espaços disponíveis
$resEsp  = $mysqli->query("SELECT * FROM espacos WHERE status = 'disponivel' ORDER BY tipo, nome");
$espacos = $resEsp ? $resEsp->fetch_all(MYSQLI_ASSOC) : [];

// Buscar reservas do utilizador
$stmt = $mysqli->prepare("
    SELECT r.*, e.nome AS espaco_nome, e.tipo AS espaco_tipo, e.capacidade AS espaco_cap
    FROM reservas_espaco r
    JOIN espacos e ON e.id = r.id_espaco
    WHERE r.id_usuario = ?
    ORDER BY r.data_reserva DESC, r.hora_inicio DESC
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$minhasReservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$numConfirmadas = count(array_filter($minhasReservas, fn($r) => ($r['status'] ?? '') === 'confirmada'));
$numPendentes   = count(array_filter($minhasReservas, fn($r) => ($r['status'] ?? '') === 'pendente'));
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

include __DIR__ . '/../partials/_layout.php';
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

    <!-- PAGE HEADER NATIVO DO SISTEMA -->
    <div class="page-header">
        <div>
            <div class="page-header-title">
                <i class="fa-solid fa-bookmark me-2" style="color:var(--primary)"></i>
                Reservas de Espaço &amp; Salas de Mentoria
            </div>
            <div class="page-header-sub">
                Infraestrutura &amp; Coworking ISPSN · <?= count($espacos) ?> espaço(s) disponível(eis) para agendamento.
            </div>
        </div>
        <button type="button" class="btn-primary-custom" onclick="abrirReservaEspaco(0)">
            <i class="fa-solid fa-calendar-plus"></i> Solicitar Nova Reserva
        </button>
    </div>

    <!-- 3 KPI CARDS DE INFRAESTRUTURA (ESTILO NATIVO GLOBAL.CSS) -->
    <div class="kpi-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <div class="kpi-card" style="--kpi-color:#D97706">
            <div class="kpi-top">
                <span class="kpi-label">Espaços Disponíveis</span>
                <div class="kpi-icon amber"><i class="fa-solid fa-door-open"></i></div>
            </div>
            <div class="kpi-value"><?= count($espacos) ?></div>
            <div class="kpi-footer">
                <span class="kpi-badge up">Ativos</span>
                <span>Salas, Labs &amp; Coworking</span>
            </div>
        </div>
        <div class="kpi-card" style="--kpi-color:#10B981">
            <div class="kpi-top">
                <span class="kpi-label">Reservas Confirmadas</span>
                <div class="kpi-icon green"><i class="fa-solid fa-calendar-check"></i></div>
            </div>
            <div class="kpi-value"><?= (int)$numConfirmadas ?></div>
            <div class="kpi-footer">
                <span class="kpi-badge up">Aprovadas</span>
                <span>Prontas para check-in</span>
            </div>
        </div>
        <div class="kpi-card" style="--kpi-color:#3B82F6">
            <div class="kpi-top">
                <span class="kpi-label">Pedidos em Análise</span>
                <div class="kpi-icon blue"><i class="fa-solid fa-hourglass-half"></i></div>
            </div>
            <div class="kpi-value"><?= (int)$numPendentes ?></div>
            <div class="kpi-footer">
                <span class="kpi-badge warn">Recepção</span>
                <span><?= count($minhasReservas) ?> pedido(s) no total</span>
            </div>
        </div>
    </div>

    <!-- CATÁLOGO VISUAL INTERATIVO DE ESPAÇOS DISPONÍVEIS -->
    <div class="card-custom mb-4">
        <div class="card-header-custom">
            <div class="card-title-custom">
                <i class="fa-solid fa-layer-group" style="color:var(--primary)"></i>
                Catálogo de Espaços da Incubadora
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn-outline-custom btn-sm" onclick="filtrarEspacos('todos', this)">Todos</button>
                <button type="button" class="btn-outline-custom btn-sm" onclick="filtrarEspacos('sala', this)">Salas</button>
                <button type="button" class="btn-outline-custom btn-sm" onclick="filtrarEspacos('coworking', this)">Coworking</button>
                <button type="button" class="btn-outline-custom btn-sm" onclick="filtrarEspacos('lab', this)">Laboratórios</button>
            </div>
        </div>
        <div class="card-body-custom">
            <?php if (empty($espacos)): ?>
                <div class="empty-state py-4">
                    <div class="empty-state-icon"><i class="fa-solid fa-building-lock"></i></div>
                    <div class="empty-state-title">Sem espaços registados</div>
                    <div class="empty-state-desc">Não existem espaços disponíveis registados neste momento.</div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($espacos as $esp):
                        $tipoE = strtolower((string)($esp['tipo'] ?? 'sala'));
                        $iconE = str_contains($tipoE, 'lab') ? 'fa-flask' : (str_contains($tipoE, 'cowork') ? 'fa-laptop-code' : (str_contains($tipoE, 'audit') ? 'fa-chalkboard-user' : 'fa-people-roof'));
                    ?>
                        <div class="col-md-4 item-espaco-card" data-tipo="<?= htmlspecialchars($tipoE, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="p-3 rounded-3 border h-100 d-flex flex-column justify-content-between" style="background:var(--surface);">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <span class="kpi-icon amber" style="width:38px;height:38px;"><i class="fa-solid <?= $iconE ?>"></i></span>
                                        <span class="badge-estado badge-aprovado">Disponível</span>
                                    </div>
                                    <div class="fw-bold mb-1" style="font-size:0.95rem;color:var(--text);">
                                        <?= htmlspecialchars((string)$esp['nome'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="text-muted mb-3" style="font-size:0.78rem;">
                                        <i class="fa-solid fa-tag me-1"></i><?= htmlspecialchars(ucfirst((string)$esp['tipo']), ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($esp['capacidade'])): ?>
                                            · <i class="fa-solid fa-users ms-1 me-1"></i>Até <?= (int)$esp['capacidade'] ?> lugares
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-primary-custom btn-sm w-100 justify-content-center" onclick="abrirReservaEspaco(<?= (int)$esp['id'] ?>)">
                                    <i class="fa-solid fa-calendar-plus"></i> Reservar Este Espaço
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 align-items-start">
        <!-- HISTÓRICO DE RESERVAS -->
        <div class="col-lg-8">
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom">
                        <i class="fa-solid fa-clock-rotate-left" style="color:var(--primary)"></i>
                        As Minhas Reservas (<?= count($minhasReservas) ?>)
                    </div>
                </div>
                <div class="card-body-custom p-0">
                    <?php if (empty($minhasReservas)): ?>
                        <div class="empty-state py-4">
                            <div class="empty-state-icon"><i class="fa-regular fa-calendar"></i></div>
                            <div class="empty-state-title">Sem reservas registadas</div>
                            <div class="empty-state-desc">Clique num espaço acima para agendar a sua primeira reserva.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead style="background:var(--surface-2);font-size:0.75rem;text-transform:uppercase;color:var(--text-muted)">
                                    <tr>
                                        <th class="ps-4">Espaço</th>
                                        <th>Data</th>
                                        <th>Horário</th>
                                        <th>Estado</th>
                                        <th class="text-end pe-4">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($minhasReservas as $r):
                                        $stR = (string)($r['status'] ?? '');
                                        $badgeR = 'badge-submetido';
                                        if ($stR === 'confirmada') {
                                            $badgeR = 'badge-aprovado';
                                        } elseif ($stR === 'pendente') {
                                            $badgeR = 'badge-avaliacao';
                                        } elseif ($stR === 'cancelada' || $stR === 'rejeitada') {
                                            $badgeR = 'badge-rejeitado';
                                        }
                                    ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold" style="font-size:0.88rem;"><?= htmlspecialchars((string)$r['espaco_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <div class="text-muted" style="font-size:0.75rem;">
                                                    <?= htmlspecialchars(ucfirst((string)$r['espaco_tipo']), ENT_QUOTES, 'UTF-8') ?>
                                                    <?php if (!empty($r['objetivo'])): ?>
                                                        · <?= htmlspecialchars((string)$r['objetivo'], ENT_QUOTES, 'UTF-8') ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td style="font-size:0.84rem;"><?= date('d/m/Y', strtotime((string)$r['data_reserva'])) ?></td>
                                            <td style="font-size:0.84rem;"><?= htmlspecialchars(substr((string)$r['hora_inicio'], 0, 5) . ' – ' . substr((string)$r['hora_fim'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <span class="badge-estado <?= $badgeR ?>">
                                                    <?= htmlspecialchars(ucfirst($stR), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <?php if ($stR === 'pendente'): ?>
                                                    <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST" class="d-inline" onsubmit="return confirm('Cancelar esta reserva pendente?')">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="gestao_reserva">
                                                        <input type="hidden" name="id_reserva" value="<?= (int)$r['id'] ?>">
                                                        <input type="hidden" name="novo_status" value="cancelada">
                                                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectSelf, ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="btn-outline-custom btn-sm text-danger">
                                                            <i class="fa-solid fa-trash"></i> Cancelar
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
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

        <!-- REGRAS & LOCALIZAÇÃO -->
        <div class="col-lg-4">
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom">
                        <i class="fa-solid fa-circle-info" style="color:var(--primary)"></i>
                        Regras &amp; Localização
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <div class="fw-bold mb-1" style="font-size:0.82rem;"><i class="fa-solid fa-building-circle-check text-warning me-1"></i> Regras de Utilização</div>
                        <ul class="ps-3 mb-0 text-muted" style="font-size:0.78rem;">
                            <li class="mb-1">Solicite a reserva com antecedência mínima de 24 horas.</li>
                            <li class="mb-1">Duração recomendada até 4 horas por sessão.</li>
                            <li>Em caso de impedimento, cancele o pedido pendente na tabela ao lado.</li>
                        </ul>
                    </div>
                    <hr>
                    <div>
                        <div class="fw-bold mb-1" style="font-size:0.82rem;"><i class="fa-solid fa-location-dot text-warning me-1"></i> Localização &amp; Horário</div>
                        <div class="text-muted" style="font-size:0.78rem;">
                            Piso 1 do Edifício Principal ISPSN · Atendimento das 08h00 às 18h00.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- MODAL NOVA RESERVA (SEMPRE ATIVO) -->
<div class="modal fade" id="modalNovaReserva" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="solicitar_reserva">
                <input type="hidden" name="id_usuario_alvo" value="<?= (int)$idUsuario ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectSelf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-bookmark me-2" aria-hidden="true"></i>Solicitar Reserva de Espaço</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Espaço *</label>
                        <select name="id_espaco" id="selectEspacoModal" class="form-select form-control-custom" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($espacos as $e): ?>
                                <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars((string)$e['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(ucfirst((string)$e['tipo']), ENT_QUOTES, 'UTF-8') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Data *</label>
                        <input type="date" name="data_reserva" class="form-control-custom" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Hora Início *</label>
                            <input type="time" name="hora_inicio" class="form-control-custom" value="09:00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Hora Fim *</label>
                            <input type="time" name="hora_fim" class="form-control-custom" value="11:00" required>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Objetivo da Reserva</label>
                        <textarea name="objetivo" class="form-control-custom" rows="2" placeholder="Ex: Reunião de equipa para desenvolvimento do MVP"></textarea>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-check me-1"></i> Confirmar Pedido</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirReservaEspaco(idEspaco) {
    const sel = document.getElementById('selectEspacoModal');
    if (sel && idEspaco > 0) {
        sel.value = String(idEspaco);
    }
    new bootstrap.Modal(document.getElementById('modalNovaReserva')).show();
}

function filtrarEspacos(filtro, btn) {
    document.querySelectorAll('.item-espaco-card').forEach(card => {
        const t = card.getAttribute('data-tipo') || '';
        if (filtro === 'todos' || t.includes(filtro)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php include __DIR__ . '/../partials/_layout_end.php'; ?>
