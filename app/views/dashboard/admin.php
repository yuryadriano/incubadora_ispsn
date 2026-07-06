<?php
// app/views/dashboard/admin.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin', 'superadmin']);

$tituloPagina = 'Dashboard Admin';
$paginaActiva = 'dashboard';

// ── Estatísticas globais ───────────────────
$stats = [];

// Total de projectos por estado
$r = $mysqli->query("SELECT estado, COUNT(*) n FROM projetos GROUP BY estado");
$porEstado = [];
while ($row = $r->fetch_assoc()) $porEstado[$row['estado']] = (int)$row['n'];

$labels_chart = array_keys($porEstado);
$values_chart = array_values($porEstado);

$stats['total_projectos']   = array_sum($porEstado);
$stats['submetidos']        = $porEstado['submetido']   ?? 0;
$stats['em_avaliacao']      = $porEstado['em_avaliacao'] ?? 0;
$stats['aprovados']         = $porEstado['aprovado']     ?? 0;
$stats['incubados']         = $porEstado['incubado']     ?? 0;
$stats['concluidos']        = $porEstado['concluido']    ?? 0;

// Total utilizadores activos
$r = $mysqli->query("SELECT COUNT(*) n FROM usuarios WHERE activo=1");
$stats['utilizadores'] = (int)$r->fetch_assoc()['n'];

// Mentorias activas
$stats['mentorias_activas'] = 0;
if ($mysqli->query("SHOW TABLES LIKE 'mentorias'")->num_rows) {
    $r = $mysqli->query("SELECT COUNT(*) n FROM mentorias WHERE estado='activa'");
    $stats['mentorias_activas'] = (int)$r->fetch_assoc()['n'];
}

// Financiamento total aprovado e executado
$stats['financiamento_total'] = 0;
$stats['execucao_total']      = 0;
if ($mysqli->query("SHOW TABLES LIKE 'financiamentos'")->num_rows) {
    $r = $mysqli->query("SELECT COALESCE(SUM(montante_aprovado),0) s, COALESCE(SUM(montante_executado),0) e FROM financiamentos");
    $row = $r->fetch_assoc();
    $stats['financiamento_total'] = (float)$row['s'];
    $stats['execucao_total']      = (float)$row['e'];
}

// Impactos globais (soma de KPIs específicos)
$rImpacto = $mysqli->query("
    SELECT 
        SUM(CASE WHEN k.nome LIKE '%faturação%' OR k.nome LIKE '%receita%' THEN r.valor ELSE 0 END) as total_faturacao,
        SUM(CASE WHEN k.nome LIKE '%usuários%' OR k.nome LIKE '%clientes%' THEN r.valor ELSE 0 END) as total_usuarios,
        SUM(CASE WHEN k.nome LIKE '%empregos%' THEN r.valor ELSE 0 END) as total_empregos
    FROM kpis k
    LEFT JOIN (SELECT id_kpi, valor FROM registos_kpi WHERE id IN (SELECT MAX(id) FROM registos_kpi GROUP BY id_kpi)) r ON r.id_kpi = k.id
");
$impacto = $rImpacto->fetch_assoc();
$stats['impacto_faturacao'] = (float)$impacto['total_faturacao'];
$stats['impacto_usuarios']  = (int)$impacto['total_usuarios'];
$stats['impacto_empregos']  = (int)$impacto['total_empregos'];

// Histórico de sessões (últimos 6 meses)
$sessoesMes = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $mesNome = ucfirst(date('M', strtotime("-$i months")));
    $r = $mysqli->query("SELECT COUNT(*) n FROM sessoes_mentoria WHERE data_sessao LIKE '$mes%'");
    $sessoesMes[$mesNome] = (int)$r->fetch_assoc()['n'];
}

// Últimos 8 projectos submetidos
$ultimosProjetos = [];
$r = $mysqli->query("
    SELECT p.id, p.titulo, p.tipo, p.estado, p.criado_em, u.nome autor
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    ORDER BY p.criado_em DESC
    LIMIT 8
");
if ($r) while ($row = $r->fetch_assoc()) $ultimosProjetos[] = $row;

// Ranking das Top 5 Startups (Média de Avaliação)
$rankingStartups = [];
$r = $mysqli->query("
    SELECT p.id, p.titulo, AVG(a.pontuacao_total) as media
    FROM projetos p
    JOIN avaliacoes a ON a.id_projeto = p.id
    GROUP BY p.id
    ORDER BY media DESC
    LIMIT 5
");
if ($r) while ($row = $r->fetch_assoc()) $rankingStartups[] = $row;

// ── Dados Comparativos (Crescimento) ───────────
$mesAtual = date('Y-m');
$mesPassado = date('Y-m', strtotime('-1 month'));

// Exemplo: Novos projectos este mês vs mês passado
$rAtu = $mysqli->query("SELECT COUNT(*) n FROM projetos WHERE criado_em LIKE '$mesAtual%'")->fetch_assoc()['n'];
$rAnt = $mysqli->query("SELECT COUNT(*) n FROM projetos WHERE criado_em LIKE '$mesPassado%'")->fetch_assoc()['n'];
$crescimentoProjetos = $rAnt > 0 ? round((($rAtu - $rAnt) / $rAnt) * 100) : 100;

// ── Feed de Atividade Recente (Técnico) ──────────
$atividades = [];
$rAtiv = $mysqli->query("
    (SELECT 'sessao' as tipo, s.criado_em, p.titulo as info, u.nome as autor
     FROM sessoes_mentoria s
     JOIN mentorias m ON m.id = s.id_mentoria
     JOIN projetos p ON p.id = m.id_projeto
     JOIN mentores mt ON mt.id = m.id_mentor
     JOIN usuarios u ON u.id = mt.id_usuario)
    UNION ALL
    (SELECT 'projeto' as tipo, criado_em, titulo as info, (SELECT nome FROM usuarios WHERE id = criado_por) as autor
     FROM projetos)
    UNION ALL
    (SELECT 'relatorio' as tipo, criado_em, titulo as info, (SELECT nome FROM usuarios WHERE id = id_autor) as autor
     FROM relatorios)
    ORDER BY criado_em DESC
    LIMIT 6
");
if ($rAtiv) while ($row = $rAtiv->fetch_assoc()) $atividades[] = $row;

require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
    .activity-feed {
        position: relative;
        padding-left: 20px;
    }
    .activity-feed::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--border);
    }
    .activity-item {
        position: relative;
        padding-bottom: 20px;
        padding-left: 15px;
    }
    .activity-item::before {
        content: '';
        position: absolute;
        left: -24px;
        top: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--primary);
        border: 2px solid white;
        box-shadow: 0 0 0 2px var(--border);
    }
    .activity-time { font-size: 0.65rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; }
    .activity-text { font-size: 0.8rem; color: var(--text-primary); margin-top: 2px; }
    .activity-text b { color: var(--primary); }

    .growth-badge {
        font-size: 0.65rem;
        padding: 2px 8px;
        border-radius: 50px;
        font-weight: 800;
        margin-left: 8px;
    }
    .growth-up { background: #f0fdf4; color: #166534; }
    .growth-down { background: #fef2f2; color: #991b1b; }
</style>

<!-- ── PAGE HEADER ──────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa fa-shuttle-space me-2" style="color:var(--primary)"></i>
            Centro de Comando da Incubadora
        </div>
        <div class="page-header-sub">
            Gestão do funil de aprovação de Startups e Mentoria
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/incubadora_ispsn/app/views/admin/projetos.php" class="btn-ghost">
            <i class="fa fa-inbox"></i> Ver Projectos
        </a>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
            <i class="fa fa-user-plus"></i> Novo Utilizador
        </button>
    </div>
</div>

<!-- ── KPI GRID ──────────────────────────── -->
<div class="kpi-grid mb-4">
    <div class="kpi-card" style="--kpi-color: var(--primary)">
        <div class="kpi-icon"><i class="fa fa-rocket"></i></div>
        <div class="kpi-value"><?= $stats['total_projectos'] ?></div>
        <div class="kpi-label">Total de Startups</div>
        <div class="kpi-trend">
            <span class="growth-badge <?= $crescimentoProjetos >= 0 ? 'growth-up' : 'growth-down' ?>">
                <i class="fa fa-arrow-<?= $crescimentoProjetos >= 0 ? 'up' : 'down' ?>"></i> <?= abs($crescimentoProjetos) ?>%
            </span>
            <small class="ms-1 text-muted">vs mês ant.</small>
        </div>
    </div>

    <div class="kpi-card" style="--kpi-color: var(--warning)">
        <div class="kpi-icon"><i class="fa fa-hourglass-half"></i></div>
        <div class="kpi-value text-warning"><?= $stats['submetidos'] ?></div>
        <div class="kpi-label">Submetidos (Novos)</div>
        <div class="kpi-trend" style="color:var(--warning)">
            <i class="fa fa-circle-dot"></i> Aguardam triagem
        </div>
    </div>

    <div class="kpi-card" style="--kpi-color: #3B82F6">
        <div class="kpi-icon"><i class="fa-solid fa-microchip"></i></div>
        <div class="kpi-value text-primary"><?= $stats['em_avaliacao'] ?></div>
        <div class="kpi-label">Em Avaliação</div>
        <div class="kpi-trend" style="color:var(--primary)">
            <i class="fa fa-list-check"></i> Análise técnica
        </div>
    </div>

    <div class="kpi-card" style="--kpi-color: var(--success)">
        <div class="kpi-icon"><i class="fa fa-seedling"></i></div>
        <div class="kpi-value text-success"><?= $stats['incubados'] ?></div>
        <div class="kpi-label">Em Incubação</div>
        <div class="kpi-trend trend-up">
            <i class="fa fa-arrow-trend-up"></i> Acompanhamento activo
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Coluna da Esquerda: Últimas Submissões (8 colunas) -->
    <div class="col-lg-8">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="card-title-custom">
                    <i class="fa fa-clock-rotate-left"></i>
                    Últimas Submissões
                </div>
                <a href="/incubadora_ispsn/app/views/admin/projetos.php" class="btn-ghost" style="font-size:0.8rem;padding:6px 12px">
                    Ver todos <i class="fa fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body-custom p-0">
                <?php if (empty($ultimosProjetos)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa fa-inbox"></i></div>
                    <div class="empty-state-title">Nenhum projecto submetido</div>
                    <div class="empty-state-text">Os projectos submetidos pelos estudantes aparecerão aqui</div>
                </div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Startup / Ideia</th>
                                <th>Sector</th>
                                <th>Fundador</th>
                                <th>Estado</th>
                                <th>Data</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ultimosProjetos as $p): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                        <?= htmlspecialchars($p['titulo']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;background:var(--surface-2);padding:3px 9px;border-radius:20px;color:var(--text-secondary);font-weight:500">
                                        <?= strtoupper($p['tipo']) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($p['autor']) ?></small></td>
                                <td>
                                    <?php
                                    $corBadge = [
                                        'submetido'    => 'secondary',
                                        'em_avaliacao' => 'warning',
                                        'aprovado'     => 'success',
                                        'rejeitado'    => 'danger',
                                        'incubado'     => 'primary',
                                        'fundo_investimento' => 'success',
                                        'concluido'    => 'success'
                                    ][$p['estado']] ?? 'info';
                                    ?>
                                    <span class="badge-estado badge-<?= $corBadge ?>">
                                        <?= ucfirst(str_replace('_',' ',$p['estado'])) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= date('d/m/Y', strtotime($p['criado_em'])) ?></small></td>
                                <td>
                                    <a href="/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=<?= $p['id'] ?>"
                                       class="btn-ghost" style="padding:5px 10px;font-size:0.78rem">
                                        <i class="fa fa-eye"></i>
                                    </a>
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
    
    <!-- Coluna da Direita: Atividade e Atalhos (4 colunas) -->
    <div class="col-lg-4">
        <!-- Atalhos de Operação -->
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa-solid fa-gears text-secondary"></i> Atalhos de Operação</div>
            </div>
            <div class="card-body-custom">
                <div class="d-grid gap-2">
                    <a href="/incubadora_ispsn/app/views/admin/usuarios.php" class="btn-ghost d-flex align-items-center justify-content-between p-3 border rounded">
                        <span><i class="fa fa-users-gear me-2"></i> Gestão de Mentores</span>
                        <i class="fa fa-chevron-right opacity-50"></i>
                    </a>
                    <a href="/incubadora_ispsn/app/views/admin/relatorios.php" class="btn-ghost d-flex align-items-center justify-content-between p-3 border rounded">
                        <span><i class="fa fa-file-export me-2"></i> Relatórios Globais</span>
                        <i class="fa fa-chevron-right opacity-50"></i>
                    </a>
                    <a href="/incubadora_ispsn/app/views/admin/gestao_espacos.php" class="btn-ghost d-flex align-items-center justify-content-between p-3 border rounded">
                        <span><i class="fa fa-building-circle-check me-2"></i> Ocupação de Espaços</span>
                        <i class="fa fa-chevron-right opacity-50"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Atividade Recente -->
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa-solid fa-bolt-lightning text-primary"></i> Atividade Recente</div>
            </div>
            <div class="card-body-custom">
                <div class="activity-feed">
                    <?php if(empty($atividades)): ?>
                        <p class="text-muted small">Nenhuma atividade registada hoje.</p>
                    <?php else: ?>
                        <?php foreach($atividades as $ativ): 
                            $icon = $ativ['tipo'] == 'sessao' ? 'fa-handshake' : ($ativ['tipo'] == 'projeto' ? 'fa-rocket' : 'fa-file-lines');
                            $acao = $ativ['tipo'] == 'sessao' ? 'registou uma <b>sessão</b> com' : ($ativ['tipo'] == 'projeto' ? 'submeteu o <b>projeto</b>' : 'enviou o <b>relatório</b>');
                        ?>
                        <div class="activity-item">
                            <div class="activity-time"><?= date('H:i', strtotime($ativ['criado_em'])) ?> • <?= date('d M', strtotime($ativ['criado_em'])) ?></div>
                            <div class="activity-text">
                                <b><?= htmlspecialchars($ativ['autor']) ?></b> <?= $acao ?> <b><?= htmlspecialchars($ativ['info']) ?></b>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── JAVASCRIPT PARA GRÁFICOS ─────────── -->
<?php ob_start(); ?>
<script>
// Animação de contadores (Técnico e Criativo)
document.querySelectorAll('.kpi-value').forEach(el => {
    const target = parseFloat(el.innerText.replace(/[^0-9.]/g, ''));
    if (isNaN(target)) return;
    let count = 0;
    const speed = 2000 / target;
    const update = () => {
        count += target / 100;
        if (count < target) {
            el.innerText = Math.ceil(count) + (el.innerText.includes('K') ? 'K' : (el.innerText.includes('M') ? 'M' : ''));
            setTimeout(update, 1);
        } else {
            el.innerText = target + (el.innerText.includes('K') ? 'K' : (el.innerText.includes('M') ? 'M' : ''));
        }
    }
    update();
});
</script>
<?php $extraJs = ob_get_clean(); ?>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
