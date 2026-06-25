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
$r3 = $mysqli->query("SELECT id, titulo FROM projetos WHERE estado IN ('aprovado','incubado','fundo_investimento') ORDER BY titulo");
if ($r3) while ($row = $r3->fetch_assoc()) $projetosActivos[] = $row;

// Flash messages
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
/* Custom Select Badges (Interactive Status Dropdowns) */
.status-select-custom {
    padding: 6px 28px 6px 12px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border: 1px solid transparent;
    cursor: pointer;
    outline: none;
    transition: all var(--transition);
    min-width: 135px;
    display: inline-block;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 10px;
}
.status-select-custom.select-activa { 
    background-color: #D1FAE5; 
    color: #065F46; 
    border-color: #A7F3D0;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23065F46' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
}
.status-select-custom.select-concluida { 
    background-color: #E0E7FF; 
    color: #3730A3; 
    border-color: #C7D2FE;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233730A3' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
}
.status-select-custom.select-cancelada { 
    background-color: #F1F5F9; 
    color: #64748B; 
    border-color: #E2E8F0;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
}
.status-select-custom:hover {
    filter: brightness(0.95);
    box-shadow: var(--shadow);
}

/* Session Pill */
.sessions-pill {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    background: rgba(139, 92, 246, 0.08);
    color: #8B5CF6;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    gap: 6px;
    border: 1px solid rgba(139, 92, 246, 0.15);
}

/* Mentor Avatar */
.mentor-avatar-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

/* Clean hover animation for project links */
.project-link-custom {
    font-weight: 600;
    color: var(--primary);
    text-decoration: none;
    max-width: 200px;
    display: inline-block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    transition: color var(--transition);
}
.project-link-custom:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}
</style>

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
                <tr>
                    <th>Projecto</th>
                    <th>Mentor</th>
                    <th>Sessões</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th style="min-width:150px">Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            $colors = ['#6366F1', '#EC4899', '#F59E0B', '#10B981', '#3B82F6'];
            foreach ($mentorias as $m): 
                $words = explode(' ', $m['mentor_nome']);
                $iniciais = mb_substr($words[0], 0, 1) . (isset($words[1]) ? mb_substr($words[1], 0, 1) : '');
                $color = $colors[ord($m['mentor_nome'][0] ?? 'A') % count($colors)];
            ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa fa-rocket" style="color:var(--primary);font-size:0.85rem"></i>
                        <a href="/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=<?= $m['id_projeto'] ?>"
                           class="project-link-custom"
                           title="<?= htmlspecialchars($m['projeto']) ?>">
                            <?= htmlspecialchars($m['projeto']) ?>
                        </a>
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <div class="mentor-avatar-circle" style="background:<?= $color ?>">
                            <?= strtoupper($iniciais) ?>
                        </div>
                        <div>
                            <div style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($m['mentor_nome']) ?></div>
                            <small class="text-muted" style="font-size:0.75rem"><?= htmlspecialchars($m['mentor_email']) ?></small>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="sessions-pill">
                        <i class="fa fa-comments"></i> <strong><?= $m['n_sessoes'] ?></strong> sessões
                    </span>
                </td>
                <td><small class="text-muted"><?= $m['data_inicio'] ? date('d/m/Y', strtotime($m['data_inicio'])) : '—' ?></small></td>
                <td><small class="text-muted"><?= $m['data_fim']    ? date('d/m/Y', strtotime($m['data_fim']))    : '—' ?></small></td>
                <td>
                    <form method="post" action="/incubadora_ispsn/app/controllers/mentoria_action.php" style="margin:0">
                        <input type="hidden" name="action" value="mudar_estado_mentoria">
                        <input type="hidden" name="id_mentoria" value="<?= $m['id'] ?>">
                        <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/funcionario/mentorias.php">
                        <select name="estado_m" class="status-select-custom select-<?= $m['estado'] ?>" onchange="this.form.submit()">
                            <option value="activa"    <?= $m['estado']==='activa'    ?'selected':'' ?>>Activa</option>
                            <option value="concluida" <?= $m['estado']==='concluida' ?'selected':'' ?>>Concluída</option>
                            <option value="cancelada" <?= $m['estado']==='cancelada' ?'selected':'' ?>>Cancelada</option>
                        </select>
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
            <form method="post" action="/incubadora_ispsn/app/controllers/mentoria_action.php">
                <input type="hidden" name="action" value="criar_mentoria">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/funcionario/mentorias.php">
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
