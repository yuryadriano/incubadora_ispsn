<?php
require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$idUsuario = $_SESSION['usuario_id'];

// Buscar espaços disponíveis
$espacos = $mysqli->query("SELECT * FROM espacos WHERE status = 'disponivel' ORDER BY tipo, nome")->fetch_all(MYSQLI_ASSOC);

// Buscar minhas reservas
$stmt = $mysqli->prepare("
    SELECT r.*, e.nome as espaco_nome, e.tipo as espaco_tipo 
    FROM reservas_espaco r 
    JOIN espacos e ON e.id = r.id_espaco 
    WHERE r.id_usuario = ? 
    ORDER BY r.data_reserva DESC, r.hora_inicio DESC
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$minhasReservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../partials/_layout.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Coworking & Espaços</div>
        <div class="page-header-sub">Reserve o seu lugar para trabalhar ou reunir com a sua equipa.</div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovaReserva">
        <i class="fa fa-calendar-plus me-1"></i> Nova Reserva
    </button>
</div>

<div class="row g-4">
    <!-- MINHAS RESERVAS -->
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-clock-rotate-left"></i> O Meu Histórico de Reservas</div>
            </div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Espaço</th>
                                <th>Data</th>
                                <th>Horário</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($minhasReservas)): ?>
                                <tr><td colspan="5" class="text-center py-4">Ainda não fez nenhuma reserva.</td></tr>
                            <?php else: ?>
                                <?php foreach($minhasReservas as $r): 
                                    $statusClass = ($r['status'] == 'confirmada' ? 'success' : ($r['status'] == 'pendente' ? 'warning' : 'danger'));
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($r['espaco_nome']) ?></div>
                                        <small class="text-muted"><?= ucfirst($r['espaco_tipo']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($r['data_reserva'])) ?></td>
                                    <td><?= substr($r['hora_inicio'],0,5) ?> - <?= substr($r['hora_fim'],0,5) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $statusClass ?>-subtle text-<?= $statusClass ?> px-3 py-2 rounded-pill" style="font-size:0.7rem">
                                            <?= strtoupper($r['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($r['status'] == 'pendente'): ?>
                                            <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST" onsubmit="return confirm('Cancelar esta reserva?')">
                                                <input type="hidden" name="action" value="gestao_reserva">
                                                <input type="hidden" name="id_reserva" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="novo_status" value="cancelada">
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
    </div>

    <!-- INFO DO ESPAÇO -->
    <div class="col-lg-4">
        <div class="card-custom bg-primary text-white mb-4">
            <div class="card-body-custom">
                <h5 class="fw-bold mb-3"><i class="fa fa-building-circle-check"></i> Regras de Utilização</h5>
                <ul class="list-unstyled small mb-0" style="opacity:0.9">
                    <li class="mb-2"><i class="fa fa-check-circle me-2"></i> Reservas devem ser feitas com 24h de antecedência.</li>
                    <li class="mb-2"><i class="fa fa-check-circle me-2"></i> O tempo máximo por reserva é de 4 horas.</li>
                    <li class="mb-2"><i class="fa fa-check-circle me-2"></i> Mantenha o silêncio nas áreas de coworking.</li>
                    <li><i class="fa fa-check-circle me-2"></i> Em caso de não comparência, cancele a reserva.</li>
                </ul>
            </div>
        </div>

        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-map-location-dot"></i> Onde Estamos?</div>
            </div>
            <div class="card-body-custom">
                <p class="small text-muted mb-0">Localize-se no Piso 1 do Edifício Principal. O nosso recepcionista está disponível das 08h às 18h para lhe dar acesso aos espaços.</p>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NOVA RESERVA -->
<div class="modal fade" id="modalNovaReserva" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST">
                <input type="hidden" name="action" value="solicitar_reserva">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-bookmark me-2"></i> Reservar Espaço</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Escolha o Espaço *</label>
                        <select name="id_espaco" class="form-select form-control-custom" required>
                            <option value="">Selecione...</option>
                            <?php foreach($espacos as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= $e['nome'] ?> (<?= ucfirst($e['tipo']) ?>)</option>
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

<?php include __DIR__ . '/../partials/_layout_end.php'; ?>
