<?php
// app/views/dashboard/funcionario.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['funcionario','admin','superadmin']);

$tituloPagina = 'Painel Operacional';
$paginaActiva = 'dashboard';

// ── Estatísticas operacionais ──────────────
$stats = [];

// Mentorias
$stats['mentorias_activas']    = 0;
$stats['mentorias_concluidas'] = 0;
if ($mysqli->query("SHOW TABLES LIKE 'mentorias'")->num_rows) {
    $r = $mysqli->query("SELECT estado, COUNT(*) n FROM mentorias GROUP BY estado");
    while ($row = $r->fetch_assoc()) {
        if ($row['estado'] === 'activa')    $stats['mentorias_activas']    = (int)$row['n'];
        if ($row['estado'] === 'concluida') $stats['mentorias_concluidas'] = (int)$row['n'];
    }
}

// Financiamentos
$stats['fin_aprovado']   = 0;
$stats['fin_executado']  = 0;
$stats['fin_pendentes']  = 0;
if ($mysqli->query("SHOW TABLES LIKE 'financiamentos'")->num_rows) {
    $r = $mysqli->query("SELECT COALESCE(SUM(montante_aprovado),0) ap, COALESCE(SUM(montante_executado),0) ex FROM financiamentos WHERE estado='activo'");
    $row = $r->fetch_assoc();
    $stats['fin_aprovado']  = (float)($row['ap'] ?? 0);
    $stats['fin_executado'] = (float)($row['ex'] ?? 0);

    $r2 = $mysqli->query("SELECT COUNT(*) n FROM financiamentos WHERE estado='pendente'");
    $stats['fin_pendentes'] = (int)$r2->fetch_assoc()['n'];
}

// Projectos activos
$r3 = $mysqli->query("SELECT COUNT(*) n FROM projetos WHERE estado IN ('aprovado','em_andamento')");
$stats['projectos_activos'] = (int)($r3 ? $r3->fetch_assoc()['n'] : 0);

// Sessões de mentoria recentes
$sessoes = [];
if ($mysqli->query("SHOW TABLES LIKE 'sessoes_mentoria'")->num_rows &&
    $mysqli->query("SHOW TABLES LIKE 'mentorias'")->num_rows) {
    $r = $mysqli->query("
        SELECT s.data_sessao, s.duracao_min, s.topicos,
               p.titulo projeto, u.nome mentor
        FROM sessoes_mentoria s
        JOIN mentorias m ON m.id = s.id_mentoria
        JOIN projetos p  ON p.id = m.id_projeto
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u  ON u.id = mt.id_usuario
        ORDER BY s.data_sessao DESC LIMIT 6
    ");
    if ($r) while ($row = $r->fetch_assoc()) $sessoes[] = $row;
}

// Financiamentos recentes
$financiamentos = [];
if ($mysqli->query("SHOW TABLES LIKE 'financiamentos'")->num_rows) {
    $r = $mysqli->query("
        SELECT f.fonte, f.montante_aprovado, f.montante_executado, f.estado,
               p.titulo projeto
        FROM financiamentos f
        JOIN projetos p ON p.id = f.id_projeto
        ORDER BY f.criado_em DESC LIMIT 6
    ");
    if ($r) while ($row = $r->fetch_assoc()) $financiamentos[] = $row;
}

// Percentagem de execução financeira
$pctExec = ($stats['fin_aprovado'] > 0)
    ? min(100, round($stats['fin_executado'] / $stats['fin_aprovado'] * 100))
    : 0;

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa fa-briefcase me-2" style="color:var(--success)"></i>
            Painel Operacional
        </div>
        <div class="page-header-sub">
            Gestão de mentorias, financiamentos e acompanhamento de projectos activos
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/incubadora_ispsn/app/views/funcionario/mentorias.php" class="btn-ghost">
            <i class="fa fa-handshake"></i> Mentorias
        </a>
        <a href="/incubadora_ispsn/app/views/funcionario/financiamentos.php" class="btn-primary-custom">
            <i class="fa fa-money-bill-wave"></i> Financiamentos
        </a>
    </div>
</div>

<!-- KPI GRID -->
<div class="kpi-grid">

    <div class="kpi-card" style="--kpi-color:#10B981">
        <div class="kpi-icon"><i class="fa fa-diagram-project"></i></div>
        <div class="kpi-value"><?= $stats['projectos_activos'] ?></div>
        <div class="kpi-label">Projectos Activos</div>
        <div class="kpi-trend trend-up"><i class="fa fa-circle-dot"></i> Em incubação</div>
    </div>

    <div class="kpi-card" style="--kpi-color:#8B5CF6">
        <div class="kpi-icon"><i class="fa fa-handshake"></i></div>
        <div class="kpi-value"><?= $stats['mentorias_activas'] ?></div>
        <div class="kpi-label">Mentorias Activas</div>
        <div class="kpi-trend" style="color:#8B5CF6">
            <i class="fa fa-check-circle"></i> <?= $stats['mentorias_concluidas'] ?> concluídas
        </div>
    </div>

    <div class="kpi-card" style="--kpi-color:#EC4899">
        <div class="kpi-icon"><i class="fa fa-money-bill-wave"></i></div>
        <div class="kpi-value"><?= number_format($stats['fin_aprovado']/1000,0) ?>K</div>
        <div class="kpi-label">Kz Aprovados</div>
        <div class="kpi-trend trend-up"><i class="fa fa-arrow-trend-up"></i> Total activos</div>
    </div>

    <div class="kpi-card" style="--kpi-color:#F59E0B">
        <div class="kpi-icon"><i class="fa fa-coins"></i></div>
        <div class="kpi-value"><?= $pctExec ?>%</div>
        <div class="kpi-label">Execução Financeira</div>
        <div class="kpi-trend" style="color:var(--warning)">
            <i class="fa fa-chart-bar"></i> <?= number_format($stats['fin_executado']/1000,0) ?>K executados
        </div>
    </div>

    <div class="kpi-card" style="--kpi-color:#EF4444">
        <div class="kpi-icon"><i class="fa fa-triangle-exclamation"></i></div>
        <div class="kpi-value"><?= $stats['fin_pendentes'] ?></div>
        <div class="kpi-label">Financiamentos Pendentes</div>
        <div class="kpi-trend trend-down"><i class="fa fa-clock"></i> Aguardam aprovação</div>
    </div>

</div>

<!-- EXECUÇÃO FINANCEIRA BAR -->
<?php if ($stats['fin_aprovado'] > 0): ?>
<div class="card-custom mb-4">
    <div class="card-body-custom">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span style="font-size:0.85rem;font-weight:600;color:var(--text-secondary)">
                <i class="fa fa-chart-line me-2" style="color:var(--primary)"></i>
                Taxa de Execução Financeira Global
            </span>
            <span style="font-weight:800;font-size:1.1rem;color:var(--primary)"><?= $pctExec ?>%</span>
        </div>
        <div class="progress-custom">
            <div class="progress-bar-custom" style="width:<?= $pctExec ?>%"></div>
        </div>
        <div class="d-flex justify-content-between mt-2">
            <small class="text-muted">Executado: <strong><?= number_format($stats['fin_executado'],2) ?> Kz</strong></small>
            <small class="text-muted">Aprovado: <strong><?= number_format($stats['fin_aprovado'],2) ?> Kz</strong></small>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- SESSÕES + FINANCIAMENTOS -->
<div class="row g-4">

    <!-- Sessões de mentoria recentes -->
    <div class="col-lg-6">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="card-title-custom">
                    <i class="fa fa-calendar-check"></i> Sessões Recentes
                </div>
                <a href="/incubadora_ispsn/app/views/funcionario/mentorias.php" class="btn-ghost" style="font-size:0.78rem;padding:5px 11px">Ver todas</a>
            </div>
            <div class="card-body-custom p-0">
                <?php if (empty($sessoes)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa fa-calendar-xmark"></i></div>
                    <div class="empty-state-title">Sem sessões registadas</div>
                    <div class="empty-state-text">As sessões de mentoria registadas aparecerão aqui</div>
                </div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table class="table-custom">
                        <thead><tr><th>Projecto</th><th>Mentor</th><th>Data</th><th>Duração</th></tr></thead>
                        <tbody>
                        <?php foreach ($sessoes as $s): ?>
                        <tr>
                            <td style="font-weight:500;font-size:0.83rem"><?= htmlspecialchars(mb_strimwidth($s['projeto'],0,28,'…')) ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($s['mentor']) ?></small></td>
                            <td><small class="text-muted"><?= date('d/m/Y', strtotime($s['data_sessao'])) ?></small></td>
                            <td><small style="color:var(--primary);font-weight:600"><?= $s['duracao_min'] ?>min</small></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Financiamentos recentes -->
    <div class="col-lg-6">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="card-title-custom">
                    <i class="fa fa-money-bill-wave"></i> Financiamentos
                </div>
                <a href="/incubadora_ispsn/app/views/funcionario/financiamentos.php" class="btn-ghost" style="font-size:0.78rem;padding:5px 11px">Ver todos</a>
            </div>
            <div class="card-body-custom p-0">
                <?php if (empty($financiamentos)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa fa-money-bill-trend-up"></i></div>
                    <div class="empty-state-title">Sem financiamentos</div>
                    <div class="empty-state-text">Os financiamentos registados aparecerão aqui</div>
                </div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table class="table-custom">
                        <thead><tr><th>Projecto</th><th>Fonte</th><th>Aprovado</th><th>Estado</th></tr></thead>
                        <tbody>
                        <?php foreach ($financiamentos as $f): ?>
                        <tr>
                            <td style="font-weight:500;font-size:0.83rem"><?= htmlspecialchars(mb_strimwidth($f['projeto'],0,22,'…')) ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($f['fonte']) ?></small></td>
                            <td><small style="font-weight:600"><?= number_format($f['montante_aprovado'],0) ?> Kz</small></td>
                            <td><span class="badge-estado badge-<?= $f['estado'] ?>"><?= ucfirst($f['estado']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
