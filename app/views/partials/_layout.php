<?php
// app/views/partials/_layout.php
// Layout base partilhado por todos os dashboards
// Uso: include __DIR__ . '/_layout.php';
// Defina $paginaActiva e $tituloPagina antes de incluir

require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$perfil       = $_SESSION['usuario_perfil']   ?? 'utilizador';
$nomeUsuario  = $_SESSION['usuario_nome']     ?? 'Utilizador';
$idUsuario    = (int)($_SESSION['usuario_id'] ?? 0);

// Suporte para Simulação de Perfil (Developer Mode)
$isSimulando = false;
if ($perfil === 'superadmin' && !empty($_SESSION['contexto_simulado'])) {
    $perfil = $_SESSION['contexto_simulado'];
    $isSimulando = true;
}

// Contar notificações não lidas
$naoLidas = 0;
$stmtN = $mysqli->prepare("SELECT COUNT(*) FROM notificacoes WHERE id_usuario = ? AND lida = 0");
if ($stmtN) {
    $stmtN->bind_param('i', $idUsuario);
    $stmtN->execute();
    $stmtN->bind_result($naoLidas);
    $stmtN->fetch();
    $stmtN->close();
}

$iniciais = strtoupper(substr($nomeUsuario, 0, 1));
$paginaActiva = $paginaActiva ?? 'dashboard';
$tituloPagina = $tituloPagina ?? 'Painel';

// Menus por perfil
$menus = [
    'utilizador' => [
        ['icon'=>'fa-house',           'label'=>'Painel Geral',     'href'=>'/incubadora_ispsn/public/index.php',            'id'=>'dashboard'],
        ['icon'=>'fa-rocket',          'label'=>'Minha Startup',    'href'=>'/incubadora_ispsn/app/views/utilizador/meu_projeto.php','id'=>'projetos'],
        ['icon'=>'fa-bookmark',        'label'=>'Reservas de Espaço','href'=>'/incubadora_ispsn/app/views/utilizador/reservas.php','id'=>'reservas'],
        ['icon'=>'fa-trophy',          'label'=>'Ranking Startups', 'href'=>'/incubadora_ispsn/app/views/admin/ranking.php',    'id'=>'ranking'],
        ['icon'=>'fa-user',            'label'=>'Meu Perfil',       'href'=>'/incubadora_ispsn/app/views/auth/perfil.php',   'id'=>'perfil'],
    ],
    'admin' => [
        ['icon'=>'fa-house',           'label'=>'Comando Central',  'href'=>'/incubadora_ispsn/public/index.php',             'id'=>'dashboard'],
        ['icon'=>'fa-inbox',           'label'=>'Candidaturas',     'href'=>'/incubadora_ispsn/app/views/admin/candidaturas.php','id'=>'candidaturas'],
        ['icon'=>'fa-rocket',          'label'=>'Startups',         'href'=>'/incubadora_ispsn/app/views/admin/projetos.php', 'id'=>'projetos'],
        ['icon'=>'fa-bookmark',        'label'=>'Gestão de Espaços','href'=>'/incubadora_ispsn/app/views/admin/gestao_espacos.php','id'=>'gestao_espacos'],
        ['icon'=>'fa-trophy',          'label'=>'Ranking Startups', 'href'=>'/incubadora_ispsn/app/views/admin/ranking.php',    'id'=>'ranking'],
        ['icon'=>'fa-star',            'label'=>'Avaliações',       'href'=>'/incubadora_ispsn/app/views/admin/avaliacoes.php','id'=>'avaliacoes'],
        ['icon'=>'fa-users',           'label'=>'Utilizadores',     'href'=>'/incubadora_ispsn/app/views/admin/usuarios.php', 'id'=>'usuarios'],
        ['icon'=>'fa-chart-bar',       'label'=>'KPIs Globais',     'href'=>'/incubadora_ispsn/app/views/admin/kpis.php',     'id'=>'kpis'],
        ['icon'=>'fa-file-chart-line', 'label'=>'Impacto',          'href'=>'/incubadora_ispsn/app/views/admin/relatorios.php','id'=>'relatorios'],
        ['icon'=>'fa-globe',           'label'=>'Ver Website',      'href'=>'/incubadora_ispsn/public/website/',                     'id'=>'website'],
        ['icon'=>'fa-screwdriver-wrench','label'=>'Gestão Website',   'href'=>'/incubadora_ispsn/app/views/admin/website.php',        'id'=>'gestao_website'],
        ['icon'=>'fa-user',            'label'=>'Meu Perfil',       'href'=>'/incubadora_ispsn/app/views/auth/perfil.php',           'id'=>'perfil'],
    ],
    'funcionario' => [
        ['icon'=>'fa-house',           'label'=>'Comando Central',  'href'=>'/incubadora_ispsn/public/index.php',                 'id'=>'dashboard'],
        ['icon'=>'fa-bookmark',        'label'=>'Gestão de Espaços','href'=>'/incubadora_ispsn/app/views/admin/gestao_espacos.php','id'=>'gestao_espacos'],
        ['icon'=>'fa-handshake',       'label'=>'Mentorias',        'href'=>'/incubadora_ispsn/app/views/funcionario/mentorias.php','id'=>'mentorias'],
        ['icon'=>'fa-money-bill-wave', 'label'=>'Financiamentos',   'href'=>'/incubadora_ispsn/app/views/funcionario/financiamentos.php','id'=>'financiamentos'],
        ['icon'=>'fa-rocket',          'label'=>'Startups',         'href'=>'/incubadora_ispsn/app/views/admin/projetos.php',    'id'=>'projetos'],
        ['icon'=>'fa-file-lines',      'label'=>'Relatórios',       'href'=>'/incubadora_ispsn/app/views/admin/relatorios.php',  'id'=>'relatorios'],
        ['icon'=>'fa-user',            'label'=>'Meu Perfil',       'href'=>'/incubadora_ispsn/app/views/auth/perfil.php',   'id'=>'perfil'],
    ],
    'superadmin' => [
        // ── Geral ──────────────────────────
        ['icon'=>'fa-house',           'label'=>'Comando Central',  'href'=>'/incubadora_ispsn/public/index.php',                      'id'=>'dashboard'],
        // ── Candidaturas ───────────────────
        ['icon'=>'fa-inbox',           'label'=>'Candidaturas',     'href'=>'/incubadora_ispsn/app/views/admin/candidaturas.php',      'id'=>'candidaturas'],
        // ── Funil Startups ─────────────────
        ['icon'=>'fa-rocket',          'label'=>'Todas Startups',   'href'=>'/incubadora_ispsn/app/views/admin/projetos.php',        'id'=>'projetos'],
        ['icon'=>'fa-bookmark',        'label'=>'Gestão de Espaços','href'=>'/incubadora_ispsn/app/views/admin/gestao_espacos.php','id'=>'gestao_espacos'],
        ['icon'=>'fa-trophy',          'label'=>'Ranking Startups', 'href'=>'/incubadora_ispsn/app/views/admin/ranking.php',    'id'=>'ranking'],
        ['icon'=>'fa-star',            'label'=>'Avaliações',       'href'=>'/incubadora_ispsn/app/views/admin/avaliacoes.php',      'id'=>'avaliacoes'],
        ['icon'=>'fa-plus-circle',     'label'=>'Simular Submissão','href'=>'/incubadora_ispsn/app/views/dashboard/utilizador.php',  'id'=>'meu_painel'],
        // ── Gestão Operacional ─────────────
        ['icon'=>'fa-handshake',       'label'=>'Mentorias',        'href'=>'/incubadora_ispsn/app/views/funcionario/mentorias.php', 'id'=>'mentorias'],
        ['icon'=>'fa-money-bill-wave', 'label'=>'Financiamentos',   'href'=>'/incubadora_ispsn/app/views/funcionario/financiamentos.php','id'=>'financiamentos'],
        // ── Análise ────────────────────────
        ['icon'=>'fa-chart-bar',       'label'=>'KPIs Globais',     'href'=>'/incubadora_ispsn/app/views/admin/kpis.php',            'id'=>'kpis'],
        ['icon'=>'fa-file-chart-line', 'label'=>'Impacto',          'href'=>'/incubadora_ispsn/app/views/admin/relatorios.php',      'id'=>'relatorios'],
        // ── Administração ──────────────────
        ['icon'=>'fa-users',           'label'=>'Utilizadores',     'href'=>'/incubadora_ispsn/app/views/admin/usuarios.php',        'id'=>'usuarios'],
        // ── Website ────────────────────────
        ['icon'=>'fa-globe',           'label'=>'Ver Website',      'href'=>'/incubadora_ispsn/public/website/',                     'id'=>'website'],
        ['icon'=>'fa-screwdriver-wrench','label'=>'Gestão Website',   'href'=>'/incubadora_ispsn/app/views/admin/website.php',        'id'=>'gestao_website'],
        ['icon'=>'fa-user',            'label'=>'Meu Perfil',       'href'=>'/incubadora_ispsn/app/views/auth/perfil.php',           'id'=>'perfil'],
    ],
    'mentor' => [
        ['icon'=>'fa-solid fa-house',           'label'=>'Painel Mentor',    'href'=>'/incubadora_ispsn/public/index.php',                      'id'=>'dashboard'],
        ['icon'=>'fa-solid fa-rocket',          'label'=>'Minhas Startups',  'href'=>'/incubadora_ispsn/app/views/mentor/startups.php',        'id'=>'projetos'],
        ['icon'=>'fa-solid fa-calendar-check', 'label'=>'Sessões Realizadas','href'=>'/incubadora_ispsn/app/views/mentor/sessoes.php',         'id'=>'sessoes'],
        ['icon'=>'fa-solid fa-calendar-days',  'label'=>'Agenda de Reuniões','href'=>'/incubadora_ispsn/app/views/mentor/agenda.php',          'id'=>'agenda'],
        ['icon'=>'fa-solid fa-file-lines',      'label'=>'Relatórios',       'href'=>'/incubadora_ispsn/app/views/mentor/relatorios.php',      'id'=>'relatorios'],
        ['icon'=>'fa-solid fa-user',            'label'=>'Meu Perfil',       'href'=>'/incubadora_ispsn/app/views/auth/perfil.php',           'id'=>'perfil'],
    ],
];

$itemsMenu = $menus[$perfil] ?? $menus['utilizador'];

$labelsPerfil = [
    'superadmin'  => ['label'=>'Super Admin',  'cor'=>'badge-superadmin'],
    'admin'       => ['label'=>'Administrador','cor'=>'badge-admin'],
    'funcionario' => ['label'=>'Funcionário',  'cor'=>'badge-funcionario'],
    'mentor'      => ['label'=>'Mentor Externo', 'cor'=>'badge-mentor'],
    'utilizador'  => ['label'=>'Empreendedor', 'cor'=>'badge-utilizador'],
];
$badgeInfo = $labelsPerfil[$perfil] ?? $labelsPerfil['utilizador'];
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($tituloPagina) ?> — Incubadora ISPSN</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Sistema de Gestão da Incubadora Académica do ISPSN">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome 6 -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<!-- Global Stylesheet -->
<link rel="stylesheet" href="/incubadora_ispsn/assets/css/global.css">
</head>
<body>

<!-- ═══════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- Logo & Notificações -->
    <div class="sidebar-brand-wrapper" style="padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 10px;">
        <div class="d-flex align-items-center justify-content-between">
            <a href="/incubadora_ispsn/public/website/" style="text-decoration: none; flex: 1;">
                <div class="nav-logo" style="background: #fff; padding: 10px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); width: 100%; max-width: 220px; margin: 0 auto; gap: 10px;">
                    <img src="/incubadora_ispsn/assets/img/logo_icon.png" alt="Icon" style="height: 45px; width: auto;">
                    <div class="logo-text-wrapper">
                        <span class="t1" style="font-size: 0.55rem;">Incubadora de</span>
                        <span class="t2" style="font-size: 1.1rem;">EMPRESAS</span>
                        <span class="t3" style="font-size: 1.1rem;">SOL NASCENTE</span>
                    </div>
                </div>
            </a>

            <!-- Dropdown de Notificações -->
            <div class="dropdown">
                <button class="btn-notif-sidebar" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa fa-bell"></i>
                    <span id="notif-badge" class="badge-dot d-none"></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="notifDropdown" style="width:280px; border:none; border-radius:var(--radius); padding:0; overflow:hidden; margin-top:10px;">
                    <li class="p-3 border-bottom d-flex justify-content-between align-items-center" style="background:var(--surface-1)">
                        <span class="fw-bold" style="font-size:0.85rem">Notificações</span>
                        <button class="btn-ghost btn-sm p-0" onclick="marcarLidas()" style="font-size:0.7rem; color:var(--primary)">Limpar</button>
                    </li>
                    <div id="notif-list" style="max-height:300px; overflow-y:auto;">
                        <li class="p-4 text-center text-muted small">Carregando...</li>
                    </div>
                </ul>
            </div>
        </div>
    </div>

    <!-- Perfil do utilizador -->
    <div class="sidebar-user">
        <div class="user-avatar"><?= $iniciais ?></div>
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars(explode(' ', $nomeUsuario)[0]) ?></div>
            <span class="user-badge <?= $badgeInfo['cor'] ?>"><?= $badgeInfo['label'] ?></span>
        </div>
    </div>

    <!-- Navegação -->
    <nav class="sidebar-nav">
        <?php foreach ($itemsMenu as $item): ?>
        <a href="<?= $item['href'] ?>"
           class="nav-item <?= ($paginaActiva === $item['id']) ? 'active' : '' ?>">
            <i class="<?= $item['icon'] ?> nav-icon"></i>
            <span><?= $item['label'] ?></span>
            <?php if ($item['id'] === 'projetos' && $perfil !== 'utilizador'): ?>
                <!-- badge dinâmico pode ir aqui -->
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Footer sidebar -->
    <div class="sidebar-footer">
        <a href="/incubadora_ispsn/public/logout.php" class="nav-item logout-item">
            <i class="fa fa-right-from-bracket nav-icon"></i>
            <span>Terminar Sessão</span>
        </a>
    </div>
</aside>

<!-- ═══════════════════════════════════════════
     OVERLAY MOBILE
═══════════════════════════════════════════ -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- ═══════════════════════════════════════════
     CONTEÚDO PRINCIPAL
═══════════════════════════════════════════ -->
<main class="main-content" id="mainContent">

    <!-- TOP BAR -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="btn-menu" id="btnMenu" onclick="toggleSidebar()">
                <i class="fa fa-bars"></i>
            </button>
            <div class="topbar-breadcrumb">
                <span class="breadcrumb-home"><i class="fa fa-house"></i></span>
                <i class="fa fa-chevron-right breadcrumb-sep"></i>
                <span><?= htmlspecialchars($tituloPagina) ?></span>
            </div>
        </div>
        <div class="topbar-right">
            <!-- Notificações -->
            <button class="topbar-btn" id="btnNotif" title="Notificações">
                <i class="fa fa-bell"></i>
                <?php if ($naoLidas > 0): ?>
                    <span class="notif-badge"><?= $naoLidas ?></span>
                <?php endif; ?>
            </button>

            <!-- Avatar -->
            <div class="topbar-avatar" title="<?= htmlspecialchars($nomeUsuario) ?>">
                <?= $iniciais ?>
            </div>
        </div>
    </header>

    <!-- ZONA DE CONTEÚDO — cada página injeta o seu conteúdo aqui -->
    <div class="page-content">

    <?php if ($isSimulando): ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center mb-4 shadow-sm" style="border-left: 5px solid #f59e0b; border-radius:12px">
            <div>
                <i class="fa fa-mask me-2"></i> <strong>MODO DE SIMULAÇÃO ACTIVO:</strong> 
                Você está a visualizar o sistema como <u><?= strtoupper($perfil) ?></u>.
            </div>
            <a href="/incubadora_ispsn/app/views/dashboard/superadmin.php?parar_simulacao=1" class="btn btn-dark btn-sm">
                <i class="fa fa-right-from-bracket me-1"></i> Voltar ao Meu Painel
            </a>
        </div>
    <?php endif; ?>
