<?php
// app/views/utilizador/reservas.php
// Gestão de Reservas de Espaço — 1 Único Cartão Limpo

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

$resEsp  = $mysqli->query("SELECT * FROM espacos WHERE status = 'disponivel' ORDER BY tipo, nome");
$espacos = $resEsp ? $resEsp->fetch_all(MYSQLI_ASSOC) : [];

$stmt = $mysqli->prepare("
    SELECT r.*, e.nome AS espaco_nome, e.tipo AS espaco_tipo
    FROM reservas_espaco r
    JOIN espacos e ON e.id = r.id_espaco
    WHERE r.id_usuario = ?
    ORDER BY r.data_reserva DESC, r.hora_inicio DESC
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$minhasReservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

include __DIR__ . '/../partials/_layout.php';
?>

<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<div class="page-header">
    <div>
        <div class="page-header-title">Reservas de Espaço</div>
        <div class="page-header-sub">Agende salas de reunião, laboratórios ou espaços de coworking.</div>
    </div>
    <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovaReserva">
        <i class="fa fa-calendar-plus"></i> Nova Reserva
    </button>
</div>

<!-- 1 ÚNICO CARTÃO LIMPO COM AS RESERVAS -->
<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">
            <i class="fa fa-bookmark" style="color:var(--primary)"></i>
            As Minhas Reservas (<?= count($minhasReservas) ?>)
        </div>
        <span class="text-muted small"><?= count($espacos) ?> espaço(s) disponível(eis)</span>
    </div>
    <div class="card-body-custom p-0">
        <?php if (empty($minhasReservas)): ?>
            <div class="empty-state py-5">
                <div class="empty-state-icon"><i class="fa fa-calendar" style="color:var(--primary)"></i></div>
                <div class="empty-state-title">Sem reservas registadas</div>
                <div class="empty-state-text mb-4">Clique em "Nova Reserva" para agendar uma sala ou espaço de coworking.</div>
                <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovaReserva">
                    <i class="fa fa-calendar-plus"></i> Solicitar Reserva
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:var(--surface-2); font-size:0.74rem; text-transform:uppercase; color:var(--text-muted);">
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
                                    <div class="fw-bold" style="font-size:0.88rem;"><?= htmlspecialchars((string)$r['espaco_nome']) ?></div>
                                    <div class="text-muted small">
                                        <?= htmlspecialchars(ucfirst((string)$r['espaco_tipo'])) ?>
                                        <?php if (!empty($r['objetivo'])): ?>
                                            · <?= htmlspecialchars((string)$r['objetivo']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= date('d/m/Y', strtotime((string)$r['data_reserva'])) ?></td>
                                <td><?= htmlspecialchars(substr((string)$r['hora_inicio'], 0, 5) . ' – ' . substr((string)$r['hora_fim'], 0, 5)) ?></td>
                                <td><span class="badge-estado <?= $badgeR ?>"><?= htmlspecialchars(ucfirst($stR)) ?></span></td>
                                <td class="text-end pe-4">
                                    <?php if ($stR === 'pendente'): ?>
                                        <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST" class="d-inline" onsubmit="return confirm('Cancelar esta reserva pendente?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="gestao_reserva">
                                            <input type="hidden" name="id_reserva" value="<?= (int)$r['id'] ?>">
                                            <input type="hidden" name="novo_status" value="cancelada">
                                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectSelf) ?>">
                                            <button type="submit" class="btn-ghost text-danger" style="padding:4px 10px; font-size:0.76rem;">
                                                Cancelar
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

<!-- MODAL NOVA RESERVA -->
<div class="modal fade" id="modalNovaReserva" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="solicitar_reserva">
                <input type="hidden" name="id_usuario_alvo" value="<?= (int)$idUsuario ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectSelf) ?>">

                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold m-0">Solicitar Reserva de Espaço</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Espaço *</label>
                        <select name="id_espaco" class="form-select form-control-custom" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($espacos as $e): ?>
                                <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars((string)$e['nome']) ?> (<?= htmlspecialchars(ucfirst((string)$e['tipo'])) ?>)</option>
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
                        <label class="form-label-custom">Objetivo</label>
                        <textarea name="objetivo" class="form-control-custom" rows="2" placeholder="Ex: Reunião de equipa"></textarea>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Confirmar Pedido</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/_layout_end.php'; ?>
