<?php
// app/views/dashboard/superadmin.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['superadmin']);

$tituloPagina = 'Command Center — Incubadora V2';
$paginaActiva = 'dashboard';

// ── Colectar TODOS os dados do sistema ────
$r = $mysqli->query("SELECT perfil, COUNT(*) n FROM usuarios GROUP BY perfil");
$porPerfil = [];
while ($row = $r->fetch_assoc()) $porPerfil[$row['perfil']] = (int)$row['n'];
$totalUsers = array_sum($porPerfil);

$r = $mysqli->query("SELECT estado, COUNT(*) n FROM projetos GROUP BY estado");
$porEstadoP = [];
while ($row = $r->fetch_assoc()) $porEstadoP[$row['estado']] = (int)$row['n'];
$totalStartups = array_sum($porEstadoP);

$finTotal = 0;
if ($mysqli->query("SHOW TABLES LIKE 'financiamentos'")->num_rows) {
    $r = $mysqli->query("SELECT COALESCE(SUM(montante_aprovado),0) s FROM financiamentos");
    $finTotal = (float)$r->fetch_assoc()['s'];
}

$mentTotal = 0;
if ($mysqli->query("SHOW TABLES LIKE 'mentorias'")->num_rows) {
    $r = $mysqli->query("SELECT COUNT(*) n FROM mentorias");
    $mentTotal = (int)$r->fetch_assoc()['n'];
}

$ultimosUsers = [];
$r = $mysqli->query("SELECT id, nome, email, perfil, tipo_utilizador, activo, criado_em FROM usuarios ORDER BY criado_em DESC LIMIT 4");
if ($r) while ($row = $r->fetch_assoc()) $ultimosUsers[] = $row;

$idUsuario    = (int)($_SESSION['usuario_id'] ?? 0);

// Lógica de Simulação de Perfil para Super Admin
if (isset($_GET['simular_perfil']) && $_SESSION['usuario_perfil'] === 'superadmin') {
    $_SESSION['contexto_simulado'] = $_GET['simular_perfil'];
    header("Location: /incubadora_ispsn/public/index.php");
    exit;
}
if (isset($_GET['parar_simulacao'])) {
    unset($_SESSION['contexto_simulado']);
    header("Location: /incubadora_ispsn/app/views/dashboard/superadmin.php");
    exit;
}

require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
/* NOVO DESIGN INOVADOR - INSPIRADO NO PIPELINE E SAAS MODERNO */

.header-tabs {
    display: flex;
    gap: 20px;
    border-bottom: 2px solid var(--border);
    margin-bottom: 30px;
}
.header-tab {
    padding: 10px 15px;
    font-weight: 600;
    color: var(--text-muted);
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.3s;
}
.header-tab.active {
    color: var(--primary-dark);
    border-bottom-color: var(--primary);
}
.header-tab:hover:not(.active) { color: var(--text-primary); }

/* PIPELINE VISUALIZATION (Inspirado na sua imagem 2) */
.pipeline-container {
    background: var(--surface);
    border-radius: 20px;
    box-shadow: var(--shadow-md);
    padding: 40px 30px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    position: relative;
    border: 1px solid var(--border);
}
.pipeline-line {
    position: absolute;
    top: 90px;
    left: 10%;
    right: 10%;
    height: 6px;
    background: linear-gradient(90deg, #10B981, var(--primary), #8B5CF6, #F59E0B);
    border-radius: 10px;
    z-index: 1;
}

.pipeline-node {
    z-index: 2;
    background: var(--bg);
    width: 25%;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}
.pipeline-circle {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: var(--surface);
    border: 4px solid var(--primary);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    font-weight: 800;
    transition: transform 0.3s;
}
.pipeline-circle:hover { transform: scale(1.1); }
.pipeline-val { font-size: 1.8rem; line-height: 1; color: var(--text-primary); }
.pipeline-lbl { font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); margin-top: 5px; }

.pipeline-title { font-weight: 800; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 8px;}
.pipeline-desc { font-size: 0.8rem; color: var(--text-secondary); max-width: 180px;}

/* SAAS PANELS (Inspirado na sua imagem 1) */
.saas-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }

.saas-card {
    background: var(--surface);
    border-radius: 15px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    padding: 24px;
}
.saas-header {
    display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;
}
.saas-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
.btn-saas {
    background: var(--primary); color: #fff; padding: 6px 14px; border-radius: 6px;
    font-size: 0.8rem; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer; border:none;
}
.btn-saas:hover { background: var(--primary-dark); color:#fff; }

.doughnut-wrapper { display: flex; justify-content: center; height: 200px; position:relative; }

/* MINI LIST */
.user-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 0; border-bottom: 1px solid var(--border);
}
.user-row:last-child { border-bottom: none; }

/* QUICK ACCESS GRID (Inspirado na sua imagem) */
.quick-access-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.quick-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 20px 10px;
    text-align: center;
    border: 1px solid var(--border);
    border-top: 4px solid var(--primary);
    transition: all 0.3s;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-sm);
}
.quick-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-md);
}
.quick-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    font-size: 1.4rem;
    color: white;
}
.quick-label {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text-primary);
    margin-bottom: 4px;
}
.quick-stats {
    font-size: 0.75rem;
    color: var(--primary);
    font-weight: 600;
}
.quick-sub {
    font-size: 0.65rem;
    color: var(--text-muted);
}
</style>

<!-- TAB NAVIGATION (SaaS Look) -->
<div class="header-tabs">
    <div class="header-tab active" id="tab-geral" onclick="switchTab('geral')"><i class="fa fa-chart-line me-2"></i> Visão Geral</div>
    <div class="header-tab" id="tab-acesso" onclick="switchTab('acesso')"><i class="fa fa-th-large me-2"></i> ACESSO RÁPIDO AOS MÓDULOS</div>
    <div class="header-tab" onclick="window.location.href='/incubadora_ispsn/app/views/admin/projetos.php'"><i class="fa fa-list me-2"></i> Lista de Startups</div>
</div>

<!-- ABA 1: VISÃO GERAL -->
<div id="content-geral">
    <!-- BEAUTIFUL WORKFLOW PIPELINE -->
    <h5 class="fw-bold mb-3 tooltip-custom" style="color:var(--text-secondary)">
        <i class="fa fa-arrow-progress me-2"></i> Pipeline de Incubação de Startups
    </h5>
    <div class="pipeline-container">
    <div class="pipeline-line"></div>
    
    <div class="pipeline-node">
        <div class="pipeline-circle" style="border-color:#F59E0B">
            <span class="pipeline-val" style="color:#F59E0B"><?= ($porEstadoP['submetido'] ?? 0) + ($porEstadoP['em_avaliacao'] ?? 0) ?></span>
            <span class="pipeline-lbl">Startups</span>
        </div>
        <div class="pipeline-title" style="color:#B45309">Triagem / Seleção</div>
        <div class="pipeline-desc">
            <ul style="text-align:left;padding-left:15px;margin:0">
                <li>Nova Submissão</li>
                <li>Avaliação Técnica</li>
            </ul>
        </div>
    </div>

    <div class="pipeline-node">
        <div class="pipeline-circle" style="border-color:var(--primary)">
            <span class="pipeline-val" style="color:var(--primary)"><?= $porEstadoP['aprovado'] ?? 0 ?></span>
            <span class="pipeline-lbl">Startups</span>
        </div>
        <div class="pipeline-title" style="color:var(--primary-dark)">Aprovadas</div>
        <div class="pipeline-desc">
            <ul style="text-align:left;padding-left:15px;margin:0">
                <li>Acordo de Incubação</li>
                <li>Ficha de Admissão</li>
            </ul>
        </div>
    </div>

    <div class="pipeline-node">
        <div class="pipeline-circle" style="border-color:#10B981">
            <span class="pipeline-val" style="color:#10B981"><?= $porEstadoP['incubado'] ?? 0 ?></span>
            <span class="pipeline-lbl">Startups</span>
        </div>
        <div class="pipeline-title" style="color:#065F46">Em Incubação 🚀</div>
        <div class="pipeline-desc">
            <ul style="text-align:left;padding-left:15px;margin:0">
                <li>Mentoria Activa</li>
                <li>Apoio Operacional</li>
            </ul>
        </div>
    </div>

    <div class="pipeline-node">
        <div class="pipeline-circle" style="border-color:#8B5CF6">
            <span class="pipeline-val" style="color:#8B5CF6"><?= ($porEstadoP['concluido'] ?? 0) + ($porEstadoP['fundo_investimento'] ?? 0) ?></span>
            <span class="pipeline-lbl">Startups</span>
        </div>
        <div class="pipeline-title" style="color:#5B21B6">Graduadas / Capital</div>
        <div class="pipeline-desc">
            <ul style="text-align:left;padding-left:15px;margin:0">
                <li>Fundo de Investimento</li>
                <li>Exit / Graduação</li>
            </ul>
        </div>
    </div>
</div>

<!-- CHARTS AND LISTS (SaaS Grid) -->
<div class="saas-grid">
    
    <!-- Esquerda: Distribuição Rápida de Atividades -->
    <div class="saas-card">
        <div class="saas-header">
            <div class="saas-title">Saúde Operacional (Distribuição)</div>
            <a href="/incubadora_ispsn/app/views/admin/kpis.php" class="btn-ghost btn-sm align-items-center d-flex"><i class="fa fa-eye me-1"></i> Expandir</a>
        </div>
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="doughnut-wrapper">
                    <canvas id="saasChart" width="200" height="200"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                    <span class="text-muted"><span style="color:#10B981">■</span> Mentorias Ativas</span>
                    <strong style="color:var(--text-primary)"><?= $mentTotal ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                    <span class="text-muted"><span style="color:var(--primary)">■</span> Capital Concedido</span>
                    <strong style="color:var(--text-primary)"><?= number_format($finTotal/1000, 0) ?>K Kz</strong>
                </div>
                <div class="d-flex justify-content-between pb-2">
                    <span class="text-muted"><span style="color:#F59E0B">■</span> Startups Rejeitadas</span>
                    <strong style="color:var(--text-primary)"><?= $porEstadoP['rejeitado'] ?? 0 ?></strong>
                </div>
                <div class="mt-4 text-center">
                   <a href="/incubadora_ispsn/app/views/funcionario/financiamentos.php" class="btn-saas" style="width:100%"><i class="fa fa-plus me-1"></i> Criar Nova Ficha Financeira</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Direita: Lista Rápida (Equipa/Users) -->
    <div class="saas-card" style="display:flex;flex-direction:column">
        <div class="saas-header">
            <div class="saas-title">Empreendedores e Equipa</div>
            <a href="/incubadora_ispsn/app/views/admin/usuarios.php" class="btn-ghost btn-sm"><i class="fa fa-gear"></i></a>
        </div>
        <div style="flex:1">
            <?php foreach ($ultimosUsers as $u): ?>
                <div class="user-row">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem">
                            <?= strtoupper(substr($u['nome'], 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:0.85rem"><?= htmlspecialchars(explode(' ', $u['nome'])[0]) ?></div>
                            <div style="font-size:0.7rem;color:var(--text-muted)"><?= ucfirst($u['tipo_utilizador']) ?></div>
                        </div>
                    </div>
                    <?php if ($u['activo']): ?>
                        <span style="color:#10B981;font-size:0.7rem;font-weight:600"><i class="fa fa-check"></i> Activo</span>
                    <?php else: ?>
                        <span style="color:#EF4444;font-size:0.7rem;font-weight:600"><i class="fa fa-xmark"></i> Bloqueado</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <a href="/incubadora_ispsn/app/views/admin/usuarios.php" class="btn-ghost w-100 text-center mt-3" style="font-size:0.8rem;justify-content:center">Administrar Contas</a>
    </div>
</div>
</div><!-- /content-geral -->

<!-- ABA 2: ACESSO RÁPIDO -->
<div id="content-acesso" style="display:none">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0" style="color:var(--text-secondary)">
            <i class="fa fa-th-large me-2"></i> Módulos do Sistema
        </h5>
        <span class="badge bg-light text-muted border" style="font-size:0.7rem">Estado em tempo real</span>
    </div>

    <div class="quick-access-grid">
        <!-- Módulo Estudante -->
        <div class="quick-card" style="border-top-color: #3B82F6;">
            <div class="quick-icon" style="background:#3B82F6"><i class="fa fa-user-graduate"></i></div>
            <div class="quick-label">Módulo Estudante</div>
            <div class="quick-stats"><?= $porPerfil['utilizador'] ?? 0 ?> Activos</div>
            <div class="quick-sub mb-3">Gerir Projectos e KPIs</div>
            <div class="d-flex gap-2">
                <a href="/incubadora_ispsn/app/views/dashboard/utilizador.php" class="btn-saas px-2 py-1" style="font-size:0.65rem">Abrir</a>
                <a href="?simular_perfil=utilizador" class="btn-ghost p-1 text-primary" style="font-size:0.65rem" title="Simular como Utilizador"><i class="fa fa-mask"></i> Simular</a>
            </div>
        </div>

        <!-- Módulo Recepcionista -->
        <div class="quick-card" style="border-top-color: #EF4444;">
            <div class="quick-icon" style="background:#EF4444"><i class="fa fa-bell-concierge"></i></div>
            <div class="quick-label">Recepcionista</div>
            <div class="quick-stats">Reservas & Acesso</div>
            <div class="quick-sub mb-3">Controlo de Espaços</div>
            <div class="d-flex gap-2">
                <a href="/incubadora_ispsn/app/views/admin/gestao_espacos.php" class="btn-saas px-2 py-1" style="font-size:0.65rem; background:#EF4444">Abrir</a>
                <a href="?simular_perfil=funcionario" class="btn-ghost p-1 text-danger" style="font-size:0.65rem" title="Simular como Funcionário"><i class="fa fa-mask"></i> Simular</a>
            </div>
        </div>

        <!-- Módulo Mentor -->
        <div class="quick-card" style="border-top-color: #8B5CF6;">
            <div class="quick-icon" style="background:#8B5CF6"><i class="fa fa-chalkboard-teacher"></i></div>
            <div class="quick-label">Módulo Mentor</div>
            <div class="quick-stats"><?= $mentTotal ?> Mentorias</div>
            <div class="quick-sub mb-3">Acompanhamento e Feedback</div>
            <div class="d-flex gap-2">
                <a href="/incubadora_ispsn/app/views/dashboard/mentor.php" class="btn-saas px-2 py-1" style="font-size:0.65rem; background:#8B5CF6">Abrir</a>
                <a href="?simular_perfil=mentor" class="btn-ghost p-1" style="font-size:0.65rem; color:#8B5CF6" title="Simular como Mentor"><i class="fa fa-mask"></i> Simular</a>
            </div>
        </div>

        <!-- Módulo Admin -->
        <div class="quick-card" style="border-top-color: #10B981;">
            <div class="quick-icon" style="background:#10B981"><i class="fa fa-user-tie"></i></div>
            <div class="quick-label">Módulo Admin</div>
            <div class="quick-stats"><?= $totalStartups ?> Startups</div>
            <div class="quick-sub mb-3">Gestão Administrativa</div>
            <div class="d-flex gap-2">
                <a href="/incubadora_ispsn/app/views/admin/projetos.php" class="btn-saas px-2 py-1" style="font-size:0.65rem; background:#10B981">Abrir</a>
                <a href="?simular_perfil=admin" class="btn-ghost p-1 text-success" style="font-size:0.65rem" title="Simular como Admin"><i class="fa fa-mask"></i> Simular</a>
            </div>
        </div>

        <!-- Módulo Super Admin / Configs -->
        <a href="/incubadora_ispsn/app/views/admin/usuarios.php" class="quick-card" style="border-top-color: #1E293B;">
            <div class="quick-icon" style="background:#1E293B"><i class="fa fa-screwdriver-wrench"></i></div>
            <div class="quick-label">Desenvolvedor</div>
            <div class="quick-stats">Controlo Total</div>
            <div class="quick-sub">Acessos e Segurança</div>
        </a>
    </div>
</div><!-- /content-acesso -->

<?php ob_start(); ?>
<script src='https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'></script>
<script>
    const ctx = document.getElementById('saasChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Mentores','Admin','Empreendedores'],
                datasets: [{
                    data: [<?= $porPerfil['funcionario'] ?? 0 ?>, <?= ($porPerfil['admin'] ?? 0) + ($porPerfil['superadmin'] ?? 0) ?>, <?= $porPerfil['utilizador'] ?? 0 ?>],
                    backgroundColor: ['#10B981', '#3730A3', '#FBBF24'],
                    borderWidth: 0
                }]
            },
            options: { cutout: '60%', plugins: { legend: { position: 'bottom', labels:{font:{size:11}} } } }
        });
    }

    function switchTab(tab) {
        // Tabs
        document.getElementById('tab-geral').classList.remove('active');
        document.getElementById('tab-acesso').classList.remove('active');
        
        // Contents
        document.getElementById('content-geral').style.display = 'none';
        document.getElementById('content-acesso').style.display = 'none';
        
        if (tab === 'geral') {
            document.getElementById('tab-geral').classList.add('active');
            document.getElementById('content-geral').style.display = 'block';
        } else {
            document.getElementById('tab-acesso').classList.add('active');
            document.getElementById('content-acesso').style.display = 'block';
        }
    }
</script>
<?php $extraJs = ob_get_clean(); ?>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
