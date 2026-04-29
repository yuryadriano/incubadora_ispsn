<?php
// app/views/mentor/agenda.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['mentor', 'admin', 'superadmin']);

$idUsuario = (int)$_SESSION['usuario_id'];

// Buscar startups mentoradas para o dropdown de agendamento
$stmt = $mysqli->prepare("
    SELECT p.id, p.titulo 
    FROM projetos p
    JOIN mentorias m ON m.id_projeto = p.id
    WHERE m.id_mentor = (SELECT id FROM mentores WHERE id_usuario = ?)
    AND m.estado = 'activa'
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$minhasStartups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$reunioes = [];
$stmt = $mysqli->prepare("
    SELECT r.*, p.titulo as projeto_nome
    FROM reunioes r
    JOIN projetos p ON p.id = r.id_projeto
    WHERE r.id_mentor = ?
    ORDER BY r.data_reuniao ASC
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$reunioes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$tituloPagina = 'Agenda de Reuniões';
$paginaActiva = 'agenda';
require_once __DIR__ . '/../partials/_layout.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa-solid fa-calendar-days me-2" style="color:var(--primary)"></i>
            Agenda de Reuniões
        </div>
        <div class="page-header-sub">
            Visualize e faça a gestão de todas as suas reuniões marcadas.
        </div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalReuniao">
        <i class="fa-solid fa-plus"></i> Agendar Reunião
    </button>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom"><i class="fa-solid fa-list-ul"></i> Lista de Reuniões</div>
    </div>
    <div class="table-wrapper">
        <?php if (empty($reunioes)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-calendar-plus"></i></div>
                <div class="empty-state-title">Sem reuniões na agenda</div>
                <div class="empty-state-text">As reuniões que agendar com as suas startups aparecerão aqui.</div>
            </div>
        <?php else: ?>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Data / Hora</th>
                        <th>Assunto</th>
                        <th>Startup</th>
                        <th>Local / Link</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reunioes as $r): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?= date('d/m/Y', strtotime($r['data_reuniao'])) ?></div>
                            <small class="text-muted"><?= date('H:i', strtotime($r['data_reuniao'])) ?></small>
                        </td>
                        <td class="fw-bold"><?= htmlspecialchars($r['titulo']) ?></td>
                        <td><?= htmlspecialchars($r['projeto_nome']) ?></td>
                        <td>
                            <?php if ($r['link_reuniao']): ?>
                                <a href="<?= $r['link_reuniao'] ?>" target="_blank" class="badge bg-primary text-decoration-none">
                                    <i class="fa-solid fa-video me-1"></i> Entrar
                                </a>
                            <?php else: ?>
                                <span class="small text-muted"><?= htmlspecialchars($r['local'] ?: 'Não definido') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-estado badge-<?= $r['status'] ?>">
                                <?= ucfirst($r['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: AGENDAR REUNIÃO -->
<div class="modal fade" id="modalReuniao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="POST" action="/incubadora_ispsn/app/controllers/mentor_action.php">
                <input type="hidden" name="action" value="agendar_reuniao">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/mentor/agenda.php">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-calendar-plus me-2"></i>Agendar Nova Reunião</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Startup / Projecto *</label>
                        <select name="id_projeto" class="form-control-custom" required>
                            <option value="">— Seleccione a Startup —</option>
                            <?php foreach ($minhasStartups as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['titulo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Assunto / Pauta *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Revisão de Protótipo">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-7">
                            <label class="form-label-custom">Data e Hora *</label>
                            <input type="datetime-local" name="data_reuniao" class="form-control-custom" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label-custom">Tipo</label>
                            <select name="tipo_reuniao" class="form-control-custom" onchange="toggleReuniaoType(this.value)">
                                <option value="virtual">Virtual (Auto Link)</option>
                                <option value="presencial">Presencial</option>
                            </select>
                        </div>
                    </div>
                    <div id="div_virtual">
                        <label class="form-label-custom">Link da Reunião (Google Meet/Teams/Zoom)</label>
                        <input type="url" name="link_reuniao" class="form-control-custom" placeholder="https://meet.google.com/...">
                    </div>
                    <div id="div_presencial" style="display:none">
                        <label class="form-label-custom">Local da Reunião</label>
                        <input type="text" name="local" class="form-control-custom" placeholder="Ex: Sala de Reuniões ISPSN">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Agendar Agora</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleReuniaoType(val) {
    document.getElementById('div_virtual').style.display = val === 'virtual' ? 'block' : 'none';
    document.getElementById('div_presencial').style.display = val === 'presencial' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
