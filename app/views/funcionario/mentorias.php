<?php
// app/views/funcionario/mentorias.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['funcionario','admin','superadmin']);

$tituloPagina = 'Gestão de Mentorias';
$paginaActiva = 'mentorias';

// ── Dados ──────────────────────────────────
$mentorias = [];
$r = $mysqli->query("
    SELECT m.id, m.estado, m.data_inicio, m.data_fim,
           p.titulo projeto, p.id id_projeto,
           u.nome mentor_nome, u.email mentor_email,
           (SELECT COUNT(*) FROM sessoes_mentoria WHERE id_mentoria = m.id) n_sessoes
    FROM mentorias m
    JOIN projetos p  ON p.id = m.id_projeto
    JOIN mentores mt ON mt.id = m.id_mentor
    JOIN usuarios u  ON u.id = mt.id_usuario
    ORDER BY m.criado_em DESC
");
if ($r) while ($row = $r->fetch_assoc()) $mentorias[] = $row;

// Contadores
$totalActivas   = count(array_filter($mentorias, fn($m) => $m['estado']==='activa'));
$totalConcluidas= count(array_filter($mentorias, fn($m) => $m['estado']==='concluida'));

// Mentores disponíveis (para o formulário de nova mentoria)
$mentoresLista = [];
$r2 = $mysqli->query("SELECT mt.id, u.nome, mt.especialidade FROM mentores mt JOIN usuarios u ON u.id=mt.id_usuario WHERE mt.disponivel=1 ORDER BY u.nome");
if ($r2) while ($row = $r2->fetch_assoc()) $mentoresLista[] = $row;

// Projectos activos (para nova mentoria)
$projetosActivos = [];
$r3 = $mysqli->query("SELECT id, titulo FROM projetos WHERE estado IN ('em_analise','em_andamento') ORDER BY titulo");
if ($r3) while ($row = $r3->fetch_assoc()) $projetosActivos[] = $row;

// Flash messages
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

// Salvar nova mentoria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'criar_mentoria') {
    $idProjeto  = (int)($_POST['id_projeto'] ?? 0);
    $idMentor   = (int)($_POST['id_mentor']  ?? 0);
    $dataInicio = $_POST['data_inicio'] ?? null;
    $dataFim    = $_POST['data_fim']    ?? null;

    if ($idProjeto && $idMentor) {
        $stmt = $mysqli->prepare("INSERT INTO mentorias (id_projeto, id_mentor, data_inicio, data_fim) VALUES (?,?,?,?)");
        $stmt->bind_param('iiss', $idProjeto, $idMentor, $dataInicio, $dataFim);
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = 'Mentoria criada com sucesso!';
        } else {
            $_SESSION['flash_erro'] = 'Erro ao criar mentoria.';
        }
    } else {
        $_SESSION['flash_erro'] = 'Seleccione um projecto e um mentor.';
    }
    header('Location: /incubadora_ispsn/app/views/funcionario/mentorias.php');
    exit;
}

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- FLASH -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger  mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa fa-handshake me-2" style="color:#8B5CF6"></i>
            Gestão de Mentorias
        </div>
        <div class="page-header-sub">
            <?= $totalActivas ?> activa(s) · <?= $totalConcluidas ?> concluída(s)
        </div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovaMentoria">
        <i class="fa fa-plus"></i> Nova Mentoria
    </button>
</div>

<!-- KPIs rápidos -->
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-bottom:24px">
    <div class="kpi-card" style="--kpi-color:#8B5CF6">
        <div class="kpi-icon"><i class="fa fa-handshake"></i></div>
        <div class="kpi-value"><?= count($mentorias) ?></div>
        <div class="kpi-label">Total Mentorias</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--success)">
        <div class="kpi-icon"><i class="fa fa-circle-dot"></i></div>
        <div class="kpi-value"><?= $totalActivas ?></div>
        <div class="kpi-label">Activas</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--primary)">
        <div class="kpi-icon"><i class="fa fa-circle-check"></i></div>
        <div class="kpi-value"><?= $totalConcluidas ?></div>
        <div class="kpi-label">Concluídas</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--warning)">
        <div class="kpi-icon"><i class="fa fa-users"></i></div>
        <div class="kpi-value"><?= count($mentoresLista) ?></div>
        <div class="kpi-label">Mentores Disponíveis</div>
    </div>
</div>

<!-- TABELA DE MENTORIAS -->
<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom"><i class="fa fa-table-list"></i> Lista de Mentorias</div>
    </div>
    <?php if (empty($mentorias)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa fa-handshake"></i></div>
        <div class="empty-state-title">Nenhuma mentoria registada</div>
        <div class="empty-state-text">Clique em "Nova Mentoria" para associar um mentor a um projecto</div>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table-custom">
            <thead>
                <tr><th>Projecto</th><th>Mentor</th><th>Sessões</th><th>Início</th><th>Fim</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($mentorias as $m): ?>
            <tr>
                <td>
                    <a href="/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=<?= $m['id_projeto'] ?>"
                       style="font-weight:600;color:var(--primary);text-decoration:none;max-width:200px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= htmlspecialchars($m['projeto']) ?>
                    </a>
                </td>
                <td>
                    <div style="font-weight:500"><?= htmlspecialchars($m['mentor_nome']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($m['mentor_email']) ?></small>
                </td>
                <td>
                    <span style="font-weight:700;color:#8B5CF6"><?= $m['n_sessoes'] ?></span>
                    <small class="text-muted"> sessão(ões)</small>
                </td>
                <td><small class="text-muted"><?= $m['data_inicio'] ? date('d/m/Y', strtotime($m['data_inicio'])) : '—' ?></small></td>
                <td><small class="text-muted"><?= $m['data_fim']    ? date('d/m/Y', strtotime($m['data_fim']))    : '—' ?></small></td>
                <td><span class="badge-estado badge-<?= $m['estado'] ?>"><?= ucfirst($m['estado']) ?></span></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="action" value="mudar_estado_mentoria">
                        <select name="estado_m" class="form-control-custom" style="padding:5px 10px;font-size:0.78rem;min-width:130px"
                                onchange="this.form.submit()">
                            <option value="activa"    <?= $m['estado']==='activa'    ?'selected':'' ?>>Activa</option>
                            <option value="concluida" <?= $m['estado']==='concluida' ?'selected':'' ?>>Concluída</option>
                            <option value="cancelada" <?= $m['estado']==='cancelada' ?'selected':'' ?>>Cancelada</option>
                        </select>
                        <input type="hidden" name="id_mentoria" value="<?= $m['id'] ?>">
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL: Nova Mentoria -->
<div class="modal fade" id="modalNovaMentoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post">
                <input type="hidden" name="action" value="criar_mentoria">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-handshake me-2"></i>Nova Mentoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Projecto *</label>
                        <select name="id_projeto" class="form-control-custom" required>
                            <option value="">— Seleccionar projecto —</option>
                            <?php foreach ($projetosActivos as $pr): ?>
                            <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['titulo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Mentor *</label>
                        <select name="id_mentor" class="form-control-custom" required>
                            <option value="">— Seleccionar mentor —</option>
                            <?php foreach ($mentoresLista as $mt): ?>
                            <option value="<?= $mt['id'] ?>"><?= htmlspecialchars($mt['nome']) ?> · <?= htmlspecialchars($mt['especialidade']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label-custom">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control-custom" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label-custom">Data Fim (prevista)</label>
                            <input type="date" name="data_fim" class="form-control-custom">
                        </div>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-check"></i> Criar Mentoria</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
