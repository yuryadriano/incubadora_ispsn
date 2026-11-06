<?php
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

$paginaActiva = 'reservas';
$tituloPagina = 'Reservas de Espaço';

// Buscar espaços disponíveis
$resEsp  = $mysqli->query("SELECT * FROM espacos WHERE status = 'disponivel' ORDER BY tipo, nome");
$espacos = $resEsp ? $resEsp->fetch_all(MYSQLI_ASSOC) : [];

// Buscar reservas do utilizador
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

$numConfirmadas = count(array_filter($minhasReservas, fn($r) => ($r['status'] ?? '') === 'confirmada'));
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

    <header class="sp-header">
        <div class="sp-header-top">
            <div class="sp-kicker">Infraestrutura &amp; Coworking</div>
            <span class="sp-chip <?= $numConfirmadas > 0 ? 'sp-chip-ok' : 'sp-chip-neutral' ?> sp-num">
                <?= (int)$numConfirmadas ?> reserva<?= $numConfirmadas !== 1 ? 's' : '' ?> confirmada<?= $numConfirmadas !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="sp-title">Reservas de Espaço</h1>
                <div class="sp-meta-row mt-1">
                    <span>Salas de reunião, auditório e postos de trabalho na incubadora.</span>
                </div>
            </div>
            <?php if (!$isAdminPreview): ?>
                <button type="button" class="sp-btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaReserva">
                    <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
                    <span>Nova Reserva</span>
                </button>
            <?php endif; ?>
        </div>
    </header>

    <div class="row g-4 align-items-start">
        <!-- HISTÓRICO DE RESERVAS -->
        <div class="col-lg-8">
            <section class="sp-surface-primary">
                <div class="sp-card-head">
                    <div class="sp-card-head-title">
                        <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        <div>
                            <div class="sp-kicker">Registo de Pedidos</div>
                            <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Histórico de Reservas</h2>
                        </div>
                    </div>
                </div>

                <?php if (empty($minhasReservas)): ?>
                    <div class="sp-empty-line">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <span>Ainda não existem reservas registadas para esta conta.</span>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="sp-table">
                            <thead>
                                <tr>
                                    <th>Espaço</th>
                                    <th>Data</th>
                                    <th>Horário</th>
                                    <th>Estado</th>
                                    <?php if (!$isAdminPreview): ?>
                                        <th class="text-end">Ações</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($minhasReservas as $r):
                                    $stR = (string)($r['status'] ?? '');
                                    $chipR = 'sp-chip-neutral';
                                    if ($stR === 'confirmada') {
                                        $chipR = 'sp-chip-ok';
                                    } elseif ($stR === 'pendente') {
                                        $chipR = 'sp-chip-warn';
                                    } elseif ($stR === 'cancelada' || $stR === 'rejeitada') {
                                        $chipR = 'sp-chip-err';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars((string)$r['espaco_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div style="font-size: var(--sp-fs-xs); color: var(--sp-text-muted);">
                                                <?= htmlspecialchars(ucfirst((string)$r['espaco_tipo']), ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td class="sp-num"><?= date('d/m/Y', strtotime((string)$r['data_reserva'])) ?></td>
                                        <td class="sp-num"><?= htmlspecialchars(substr((string)$r['hora_inicio'], 0, 5) . ' – ' . substr((string)$r['hora_fim'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="sp-chip <?= $chipR ?>">
                                                <?= htmlspecialchars(ucfirst($stR), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <?php if (!$isAdminPreview): ?>
                                            <td class="text-end">
                                                <?php if ($stR === 'pendente'): ?>
                                                    <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST" class="d-inline" onsubmit="return confirm('Cancelar esta reserva?')">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="gestao_reserva">
                                                        <input type="hidden" name="id_reserva" value="<?= (int)$r['id'] ?>">
                                                        <input type="hidden" name="novo_status" value="cancelada">
                                                        <button type="submit" class="sp-btn-secondary py-1 px-2 text-danger" style="min-height: 32px; font-size: var(--sp-fs-xs);">
                                                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                            <span>Cancelar</span>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color: var(--sp-text-muted);">—</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- REGRAS & LOCALIZAÇÃO -->
        <div class="col-lg-4">
            <aside class="sp-surface-aside">
                <div class="sp-aside-block">
                    <span class="sp-aside-icon" aria-hidden="true"><i class="fa-solid fa-building-circle-check"></i></span>
                    <div class="sp-aside-body">
                        <div class="sp-kicker mb-1">Regras de Utilização</div>
                        <ul class="ps-3 mb-0" style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec);">
                            <li class="mb-1">Solicite a reserva com antecedência mínima de 24 horas.</li>
                            <li class="mb-1">Duração máxima de 4 horas por pedido.</li>
                            <li>Em caso de impedimento, cancele o pedido pendente.</li>
                        </ul>
                    </div>
                </div>
                <div class="sp-aside-block">
                    <span class="sp-aside-icon" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
                    <div class="sp-aside-body">
                        <div class="sp-kicker mb-1">Localização &amp; Horário</div>
                        <div style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec);">
                            Piso 1 do Edifício Principal · Atendimento das 08h00 às 18h00.
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

</div>

<?php if (!$isAdminPreview): ?>
<!-- MODAL NOVA RESERVA -->
<div class="modal fade" id="modalNovaReserva" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="solicitar_reserva">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/utilizador/reservas.php">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-bookmark me-2" aria-hidden="true"></i>Solicitar Reserva de Espaço</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Espaço *</label>
                        <select name="id_espaco" class="form-select form-control-custom" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($espacos as $e): ?>
                                <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars((string)$e['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(ucfirst((string)$e['tipo']), ENT_QUOTES, 'UTF-8') ?>)</option>
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
                        <textarea name="objetivo" class="form-control-custom" rows="2" placeholder="Ex: Reunião de equipa para desenvolvimento do MVP"></textarea>
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
<?php endif; ?>

<?php include __DIR__ . '/../partials/_layout_end.php'; ?>
