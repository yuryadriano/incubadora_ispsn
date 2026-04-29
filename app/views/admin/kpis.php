<?php
// app/views/admin/kpis.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin','superadmin']);

$tituloPagina = 'Dashboard de KPIs';
$paginaActiva = 'kpis';

// ── DADOS PARA GRÁFICOS ────────────────────

// 1. Projectos por Área Temática
$rAreas = $mysqli->query("SELECT area_tematica, COUNT(*) n FROM projetos GROUP BY area_tematica");
$areasLbl = []; $areasData = [];
if ($rAreas) while ($row = $rAreas->fetch_assoc()) {
    $areasLbl[] = ucfirst($row['area_tematica'] ?? 'Desconhecida');
    $areasData[] = (int)$row['n'];
}

// 2. Projectos por Estado
$rEstados = $mysqli->query("SELECT estado, COUNT(*) n FROM projetos GROUP BY estado");
$estadosLbl = []; $estadosData = []; $estadosCores = [];
$coresMap = ['em_analise'=>'#F59E0B','em_andamento'=>'#3B82F6','concluido'=>'#10B981','cancelado'=>'#EF4444'];
if ($rEstados) while ($row = $rEstados->fetch_assoc()) {
    $est = $row['estado'];
    $estadosLbl[] = ucfirst(str_replace('_',' ',$est));
    $estadosData[] = (int)$row['n'];
    $estadosCores[] = $coresMap[$est] ?? '#9ca3af';
}

// 3. Tipos de Projectos Submetidos
$rTipos = $mysqli->query("SELECT tipo, COUNT(*) n FROM projetos GROUP BY tipo");
$tiposLbl = []; $tiposData = [];
if ($rTipos) while ($row = $rTipos->fetch_assoc()) {
    $tiposLbl[] = strtoupper($row['tipo']);
    $tiposData[] = (int)$row['n'];
}

// 4. Actividade de Mentorias
$rMentorias = $mysqli->query("SELECT estado, COUNT(*) n FROM mentorias GROUP BY estado");
$mentoriasLbl = []; $mentoriasData = [];
if ($rMentorias) while ($row = $rMentorias->fetch_assoc()) {
    $mentoriasLbl[] = ucfirst($row['estado']);
    $mentoriasData[] = (int)$row['n'];
}

// 5. Crescimento Financeiro (Simulado com Totais)
$finAprovado = 0; $finExec = 0;
$rFin = $mysqli->query("SELECT SUM(montante_aprovado) a, SUM(montante_executado) e FROM financiamentos");
if ($rFin && $row = $rFin->fetch_assoc()) {
    $finAprovado = (float)$row['a'];
    $finExec = (float)$row['e'];
}

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- PAGE HEADER -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <div class="page-header-title">
            <i class="fa fa-chart-line me-2" style="color:var(--primary)"></i>
            Dashboard de KPIs Startups
        </div>
        <div class="page-header-sub">Métricas de crescimento, áreas em foco e execução da incubadora.</div>
    </div>
    <button class="btn-ghost" onclick="window.print()">
        <i class="fa fa-print"></i> Imprimir Gráficos
    </button>
</div>

<!-- GRID PARA GRÁFICOS -->
<div class="row g-4 mb-4">
    <!-- Projectos por Estado -->
    <div class="col-md-6">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-pie-chart"></i> Projectos por Estado</div>
            </div>
            <div class="card-body-custom d-flex justify-content-center align-items-center" style="position:relative;height:300px">
                <canvas id="chartEstados"></canvas>
            </div>
        </div>
    </div>

    <!-- Áreas Temáticas -->
    <div class="col-md-6">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-chart-bar"></i> Volume por Área Temática</div>
            </div>
            <div class="card-body-custom" style="position:relative;height:300px">
                <canvas id="chartAreas"></canvas>
            </div>
        </div>
    </div>

    <!-- Tipos e Mentorias -->
    <div class="col-md-4">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-layer-group"></i> Tipos de Trabalhos</div>
            </div>
            <div class="card-body-custom d-flex justify-content-center align-items-center" style="position:relative;height:250px">
                <canvas id="chartTipos"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-handshake"></i> Estado das Mentorias</div>
            </div>
            <div class="card-body-custom d-flex justify-content-center align-items-center" style="position:relative;height:250px">
                <canvas id="chartMentorias"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-money-bill-trend-up"></i> Dispersão Orçamental</div>
            </div>
            <div class="card-body-custom d-flex flex-column justify-content-center p-4">
                <h6 class="text-muted fw-bold mb-3">APROVADO vs EXECUTADO</h6>
                <div class="d-flex justify-content-between mb-1">
                    <span style="font-weight:700;color:var(--primary)">Aprovado Total</span>
                    <span style="font-weight:800"><?= number_format($finAprovado, 2, ',', '.') ?> Kz</span>
                </div>
                <div class="progress-custom mb-3" style="height:10px">
                    <div class="progress-bar-custom" style="width:100%"></div>
                </div>

                <div class="d-flex justify-content-between mb-1">
                    <span style="font-weight:700;color:var(--success)">Executado Total</span>
                    <span style="font-weight:800"><?= number_format($finExec, 2, ',', '.') ?> Kz</span>
                </div>
                <?php $pctExec = $finAprovado>0 ? ($finExec/$finAprovado*100) : 0; ?>
                <div class="progress-custom" style="height:10px">
                    <div class="progress-bar-custom" style="width:<?= $pctExec ?>%;background:var(--success)"></div>
                </div>
                <div class="text-end mt-1"><small class="text-muted fw-bold"><?= round($pctExec) ?>% Executado</small></div>
            </div>
        </div>
    </div>
</div>

<?php 
// Passar variaveis PHP para JavaScript
$extraJs = "
<script src='https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'></script>
<script>
    // Configurações Globais
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.color = '#64748B';

    // Gráfico de Estados
    new Chart(document.getElementById('chartEstados'), {
        type: 'doughnut',
        data: {
            labels: " . json_encode($estadosLbl) . ",
            datasets: [{
                data: " . json_encode($estadosData) . ",
                backgroundColor: " . json_encode($estadosCores) . ",
                borderWidth: 0
            }]
        },
        options: { cutout: '65%', plugins: { legend: { position: 'right' } }, maintainAspectRatio: false }
    });

    // Gráfico de Áreas
    new Chart(document.getElementById('chartAreas'), {
        type: 'bar',
        data: {
            labels: " . json_encode($areasLbl) . ",
            datasets: [{
                label: 'Projectos',
                data: " . json_encode($areasData) . ",
                backgroundColor: '#8B5CF6',
                borderRadius: 4
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { grid: { display: false } } },
            maintainAspectRatio: false
        }
    });

    // Gráfico de Tipos
    new Chart(document.getElementById('chartTipos'), {
        type: 'pie',
        data: {
            labels: " . json_encode($tiposLbl) . ",
            datasets: [{
                data: " . json_encode($tiposData) . ",
                backgroundColor: ['#3B82F6', '#EC4899', '#10B981'],
                borderWidth: 0
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } }, maintainAspectRatio: false }
    });

    // Gráfico de Mentorias
    new Chart(document.getElementById('chartMentorias'), {
        type: 'doughnut',
        data: {
            labels: " . json_encode($mentoriasLbl) . ",
            datasets: [{
                data: " . json_encode($mentoriasData) . ",
                backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                borderWidth: 0
            }]
        },
        options: { cutout: '60%', plugins: { legend: { position: 'bottom' } }, maintainAspectRatio: false }
    });
</script>
";
require_once __DIR__ . '/../partials/_layout_end.php'; 
?>
