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

    <!-- HERO DE RESERVAS & INFRAESTRUTURA -->
    <header class="std-command-hero">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 position-relative" style="z-index:2;">
            <span class="std-live-pill" style="background: rgba(245,158,11,0.2); color: #FCD34D; border: 1px solid rgba(245,158,11,0.45);">
                <span class="std-live-dot" style="background: #FBBF24;"></span>
                <span>Infraestrutura &amp; Coworking ISPSN</span>
            </span>
            <span class="std-live-pill" style="background: rgba(255,255,255,0.08); color: #E2E8F0; border: 1px solid rgba(255,255,255,0.14);">
                <i class="fa-solid fa-building-circle-check" style="color:#34D399;"></i>
                <span><?= count($espacos) ?> espaço(s) disponível(eis)</span>
            </span>
        </div>
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 position-relative" style="z-index:2;">
            <div>
                <h1 style="font-size: 1.65rem; font-weight: 800; color: #FFFFFF; margin: 4px 0 6px;">
                    Reservas de Espaço &amp; Salas de Mentoria
                </h1>
                <div style="font-size: 0.84rem; color: #CBD5E1;">
                    Escolha uma sala de reunião, laboratório ou posto de coworking abaixo para reservar em 1 clique.
                </div>
            </div>
            <button type="button" class="std-hero-btn std-hero-btn-amber" onclick="abrirReservaEspaco(0)">
                <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
                <span>Solicitar Nova Reserva</span>
            </button>
        </div>
    </header>

    <!-- 3 KPI CARDS DE INFRAESTRUTURA -->
    <div class="kpi-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <div class="kpi-card amber">
            <div class="kpi-top">
                <span class="kpi-label">Espaços Disponíveis</span>
                <div class="kpi-icon amber"><i class="fa-solid fa-door-open"></i></div>
            </div>
            <div class="kpi-value sp-num"><?= count($espacos) ?></div>
            <div class="kpi-footer">
                <span class="kpi-badge up">Ativos</span>
                <span>Salas, Labs &amp; Coworking</span>
            </div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-top">
                <span class="kpi-label">Reservas Confirmadas</span>
                <div class="kpi-icon green"><i class="fa-solid fa-calendar-check"></i></div>
            </div>
            <div class="kpi-value sp-num"><?= (int)$numConfirmadas ?></div>
            <div class="kpi-footer">
                <span class="kpi-badge up">Aprovadas</span>
                <span>Prontas para check-in</span>
            </div>
        </div>
        <div class="kpi-card blue">
            <div class="kpi-top">
                <span class="kpi-label">Pedidos em Análise</span>
                <div class="kpi-icon blue"><i class="fa-solid fa-hourglass-half"></i></div>
            </div>
            <div class="kpi-value sp-num"><?= (int)$numPendentes ?></div>
            <div class="kpi-footer">
                <span class="kpi-badge warn">Recepção</span>
                <span><?= count($minhasReservas) ?> pedido(s) no total</span>
            </div>
        </div>
    </div>

    <!-- CATÁLOGO VISUAL INTERATIVO DE ESPAÇOS DISPONÍVEIS -->
    <section class="sp-surface-primary mb-4">
        <div class="sp-card-head">
            <div class="sp-card-head-title">
                <span class="sp-icon-badge"><i class="fa-solid fa-layer-group"></i></span>
                <div>
                    <div class="sp-kicker">Reserva Rápida em 1 Clique</div>
                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Catálogo de Espaços da Incubadora</h2>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="sp-btn-secondary py-1 px-3 active" onclick="filtrarEspacos('todos', this)" style="min-height: 32px; font-size: 0.76rem;">Todos</button>
                <button type="button" class="sp-btn-secondary py-1 px-3" onclick="filtrarEspacos('sala', this)" style="min-height: 32px; font-size: 0.76rem;">Salas</button>
                <button type="button" class="sp-btn-secondary py-1 px-3" onclick="filtrarEspacos('coworking', this)" style="min-height: 32px; font-size: 0.76rem;">Coworking</button>
                <button type="button" class="sp-btn-secondary py-1 px-3" onclick="filtrarEspacos('lab', this)" style="min-height: 32px; font-size: 0.76rem;">Laboratórios</button>
            </div>
        </div>

        <?php if (empty($espacos)): ?>
            <div class="sp-empty-line">
                <i class="fa-solid fa-building-lock"></i>
                <span>Não existem espaços disponíveis registados neste momento.</span>
            </div>
        <?php else: ?>
            <div class="std-space-grid">
                <?php foreach ($espacos as $esp):
                    $tipoE = strtolower((string)($esp['tipo'] ?? 'sala'));
                    $iconE = str_contains($tipoE, 'lab') ? 'fa-flask' : (str_contains($tipoE, 'cowork') ? 'fa-laptop-code' : (str_contains($tipoE, 'audit') ? 'fa-chalkboard-user' : 'fa-people-roof'));
                ?>
                    <div class="std-space-card item-espaco-card" data-tipo="<?= htmlspecialchars($tipoE, ENT_QUOTES, 'UTF-8') ?>">
                        <div>
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <span class="sp-icon-badge" style="width: 38px; height: 38px;">
                                    <i class="fa-solid <?= $iconE ?>"></i>
                                </span>
                                <span class="sp-chip sp-chip-ok">Disponível</span>
                            </div>
                            <h3 class="fw-bold mb-1" style="font-size: 0.98rem; color: var(--sp-text);">
                                <?= htmlspecialchars((string)$esp['nome'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <div style="font-size: 0.78rem; color: var(--sp-text-muted);">
                                <i class="fa-solid fa-tag me-1"></i><?= htmlspecialchars(ucfirst((string)$esp['tipo']), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($esp['capacidade'])): ?>
                                    · <i class="fa-solid fa-users ms-1 me-1"></i>Até <?= (int)$esp['capacidade'] ?> lugares
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="button" class="sp-btn-primary w-100 py-1" style="min-height: 36px; font-size: 0.8rem;" onclick="abrirReservaEspaco(<?= (int)$esp['id'] ?>)">
                            <i class="fa-solid fa-calendar-plus"></i>
                            <span>Reservar Este Espaço</span>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="row g-4 align-items-start">
        <!-- HISTÓRICO DE RESERVAS -->
        <div class="col-lg-8">
            <section class="sp-surface-primary">
                <div class="sp-card-head">
                    <div class="sp-card-head-title">
                        <span class="sp-icon-badge" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        <div>
                            <div class="sp-kicker">Registo de Pedidos</div>
                            <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">As Minhas Reservas (<?= count($minhasReservas) ?>)</h2>
                        </div>
                    </div>
                </div>

                <?php if (empty($minhasReservas)): ?>
                    <div class="sp-empty-line">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <span>Ainda não existem reservas registadas. Clique num espaço acima para agendar.</span>
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
                                    <th class="text-end">Ações</th>
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
                                                <?php if (!empty($r['objetivo'])): ?>
                                                    · <?= htmlspecialchars((string)$r['objetivo'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="sp-num"><?= date('d/m/Y', strtotime((string)$r['data_reserva'])) ?></td>
                                        <td class="sp-num"><?= htmlspecialchars(substr((string)$r['hora_inicio'], 0, 5) . ' – ' . substr((string)$r['hora_fim'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="sp-chip <?= $chipR ?>">
                                                <?= htmlspecialchars(ucfirst($stR), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($stR === 'pendente'): ?>
                                                <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST" class="d-inline" onsubmit="return confirm('Cancelar esta reserva pendente?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="gestao_reserva">
                                                    <input type="hidden" name="id_reserva" value="<?= (int)$r['id'] ?>">
                                                    <input type="hidden" name="novo_status" value="cancelada">
                                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectSelf, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="sp-btn-secondary py-1 px-2 text-danger" style="min-height: 32px; font-size: var(--sp-fs-xs);">
                                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                        <span>Cancelar</span>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color: var(--sp-text-muted);">—</span>
                                            <?php endif; ?>
                                        </td>
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
                            <li class="mb-1">Duração recomendada até 4 horas por sessão.</li>
                            <li>Em caso de impedimento, cancele o pedido pendente na tabela ao lado.</li>
                        </ul>
                    </div>
                </div>
                <div class="sp-aside-block">
                    <span class="sp-aside-icon" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
                    <div class="sp-aside-body">
                        <div class="sp-kicker mb-1">Localização &amp; Horário</div>
                        <div style="font-size: var(--sp-fs-xs); color: var(--sp-text-sec);">
                            Piso 1 do Edifício Principal ISPSN · Atendimento das 08h00 às 18h00.
                        </div>
                    </div>
                </div>
            </aside>
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
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php include __DIR__ . '/../partials/_layout_end.php'; ?>
