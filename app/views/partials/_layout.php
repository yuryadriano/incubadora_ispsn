<?php
// app/views/partials/_layout.php
// Layout base partilhado por todos os dashboards
// Uso: include __DIR__ . '/_layout.php';
// Defina $paginaActiva e $tituloPagina antes de incluir

require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$realPerfilSessao = $_SESSION['usuario_perfil'] ?? 'utilizador';
if (in_array($realPerfilSessao, ['admin', 'superadmin'], true) && !empty($_GET['id_usuario']) && ($perfil ?? '') !== 'mentor') {
    $idCandPrev = (int)$_GET['id_usuario'];
    $stmtPrev = $mysqli->prepare("SELECT id, nome, perfil FROM usuarios WHERE id = ? LIMIT 1");
    if ($stmtPrev) {
        $stmtPrev->bind_param('i', $idCandPrev);
        $stmtPrev->execute();
        $uPrev = $stmtPrev->get_result()->fetch_assoc();
        $stmtPrev->close();
        if ($uPrev && ($uPrev['perfil'] ?? '') === 'utilizador') {
            $idUsuario   = (int)$uPrev['id'];
            $nomeUsuario = $uPrev['nome'];
            $perfil      = 'utilizador';
        }
    }
}

$perfil       = $perfil ?? $realPerfilSessao;
$nomeUsuario  = $nomeUsuario ?? $_SESSION['usuario_nome'] ?? 'Utilizador';
$idUsuario    = $idUsuario ?? (int)($_SESSION['usuario_id'] ?? 0);

$isSimulando = ($perfil === 'utilizador' && in_array($realPerfilSessao, ['admin', 'superadmin'], true) && $idUsuario > 0);
$qsEstudante = $isSimulando ? ('?id_usuario=' . (int)$idUsuario) : '';
$hrefPainelEstudante = $isSimulando
    ? ('/incubadora_ispsn/app/views/dashboard/utilizador.php' . $qsEstudante)
    : '/incubadora_ispsn/public/index.php';

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
        ['icon'=>'fa-house',           'label'=>'Painel Geral',     'href'=>$hrefPainelEstudante,                                              'id'=>'dashboard'],
        ['icon'=>'fa-rocket',          'label'=>'Minha Startup',    'href'=>'/incubadora_ispsn/app/views/utilizador/meu_projeto.php' . $qsEstudante,'id'=>'projetos'],
        ['icon'=>'fa-bookmark',        'label'=>'Reservas de Espaço','href'=>'/incubadora_ispsn/app/views/utilizador/reservas.php' . $qsEstudante,  'id'=>'reservas'],
        ['icon'=>'fa-trophy',          'label'=>'Ranking Startups', 'href'=>'/incubadora_ispsn/app/views/admin/ranking.php' . $qsEstudante,        'id'=>'ranking'],
        ['icon'=>'fa-user',            'label'=>'Meu Perfil',       'href'=>'/incubadora_ispsn/app/views/auth/perfil.php' . $qsEstudante,          'id'=>'perfil'],
    ],
    'admin' => [
        ['icon'=>'fa-house',           'label'=>'Comando Central',  'href'=>'/incubadora_ispsn/public/index.php',             'id'=>'dashboard'],
        ['icon'=>'fa-inbox',           'label'=>'Candidaturas',     'href'=>'/incubadora_ispsn/app/views/admin/candidaturas.php','id'=>'candidaturas'],
        ['icon'=>'fa-rocket',          'label'=>'Startups',         'href'=>'/incubadora_ispsn/app/views/admin/projetos.php', 'id'=>'projetos'],
        ['icon'=>'fa-bookmark',        'label'=>'Gestão de Espaços','href'=>'/incubadora_ispsn/app/views/admin/gestao_espacos.php','id'=>'gestao_espacos'],
        ['icon'=>'fa-id-card',          'label'=>'Controlo Visitantes','href'=>'/incubadora_ispsn/app/views/funcionario/visitantes.php','id'=>'visitantes'],
        ['icon'=>'fa-trophy',          'label'=>'Ranking Startups', 'href'=>'/incubadora_ispsn/app/views/admin/ranking.php',    'id'=>'ranking'],
        ['icon'=>'fa-star',            'label'=>'Avaliações',       'href'=>'/incubadora_ispsn/app/views/admin/avaliacoes.php','id'=>'avaliacoes'],
        ['icon'=>'fa-users',           'label'=>'Utilizadores',     'href'=>'/incubadora_ispsn/app/views/admin/usuarios.php', 'id'=>'usuarios'],
        ['icon'=>'fa-chart-bar',       'label'=>'KPIs Globais',     'href'=>'/incubadora_ispsn/app/views/admin/kpis.php',     'id'=>'kpis'],
        ['icon'=>'fa-chart-line',      'label'=>'Impacto',          'href'=>'/incubadora_ispsn/app/views/admin/relatorios.php','id'=>'relatorios'],
        ['icon'=>'fa-globe',           'label'=>'Ver Website',      'href'=>'/incubadora_ispsn/public/website/',                     'id'=>'website'],
        ['icon'=>'fa-screwdriver-wrench','label'=>'Gestão Website',   'href'=>'/incubadora_ispsn/app/views/admin/website.php',        'id'=>'gestao_website'],
        ['icon'=>'fa-user',            'label'=>'Meu Perfil',       'href'=>'/incubadora_ispsn/app/views/auth/perfil.php',           'id'=>'perfil'],
    ],
    'funcionario' => [
        ['icon'=>'fa-house',           'label'=>'Comando Central',  'href'=>'/incubadora_ispsn/public/index.php',                 'id'=>'dashboard'],
        ['icon'=>'fa-bookmark',        'label'=>'Gestão de Espaços','href'=>'/incubadora_ispsn/app/views/admin/gestao_espacos.php','id'=>'gestao_espacos'],
        ['icon'=>'fa-id-card',          'label'=>'Controlo Visitantes','href'=>'/incubadora_ispsn/app/views/funcionario/visitantes.php','id'=>'visitantes'],
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
        ['icon'=>'fa-id-card',          'label'=>'Controlo Visitantes','href'=>'/incubadora_ispsn/app/views/funcionario/visitantes.php','id'=>'visitantes'],
        ['icon'=>'fa-trophy',          'label'=>'Ranking Startups', 'href'=>'/incubadora_ispsn/app/views/admin/ranking.php',    'id'=>'ranking'],
        ['icon'=>'fa-star',            'label'=>'Avaliações',       'href'=>'/incubadora_ispsn/app/views/admin/avaliacoes.php',      'id'=>'avaliacoes'],
        ['icon'=>'fa-bullseye',        'label'=>'Gestão de Metas',  'href'=>'/incubadora_ispsn/app/views/admin/gestao_metas.php',   'id'=>'gestao_metas'],
        // ── Gestão Operacional ─────────────
        ['icon'=>'fa-handshake',       'label'=>'Mentorias',        'href'=>'/incubadora_ispsn/app/views/funcionario/mentorias.php', 'id'=>'mentorias'],
        ['icon'=>'fa-money-bill-wave', 'label'=>'Financiamentos',   'href'=>'/incubadora_ispsn/app/views/funcionario/financiamentos.php','id'=>'financiamentos'],
        // ── Análise ────────────────────────
        ['icon'=>'fa-chart-bar',       'label'=>'KPIs Globais',     'href'=>'/incubadora_ispsn/app/views/admin/kpis.php',            'id'=>'kpis'],
        ['icon'=>'fa-chart-line',      'label'=>'Impacto',          'href'=>'/incubadora_ispsn/app/views/admin/relatorios.php',      'id'=>'relatorios'],
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

<script>
(function() {
    const theme = localStorage.getItem('theme') || 'light';
    if (theme === 'dark') {
        document.documentElement.classList.add('dark-mode');
    }
})();
</script>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome 6 -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<!-- Global Stylesheet -->
<link rel="stylesheet" href="/incubadora_ispsn/assets/css/global.css?v=<?= @filemtime(__DIR__ . '/../../../assets/css/global.css') ?: ASSET_VERSION ?>">
<?php
// Libertar imediatamente o lock de ficheiro de sessão em pedidos GET quando não há mensagens flash pendentes para limpar
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && empty($_SESSION['flash_ok'])
    && empty($_SESSION['flash_erro'])
    && empty($_SESSION['flash_aviso'])
    && empty($_SESSION['convite_link'])
    && empty($_SESSION['wa_redirect'])
    && function_exists('close_session_early')) {
    close_session_early();
}
?>
<?php if ($perfil === 'utilizador'): ?>
<style>
/* ══════════════════════════════════════════════════════════════════
   SISTEMA VISUAL MODERNO DO MÓDULO DO ESTUDANTE (SaaS Dashboard)
   Alinhado com os painéis de Mentor e Admin + suporte Dark Mode
   ══════════════════════════════════════════════════════════════════ */
.std-painel {
  --sp-bg:             var(--bg, #F8FAFC);
  --sp-surface:        var(--surface, #FFFFFF);
  --sp-surface-muted:  var(--surface-2, #F1F5F9);
  --sp-surface-tint:   #FFFBEB;
  --sp-border:         var(--border, #E2E8F0);
  --sp-border-strong:  #CBD5E1;
  --sp-text:           var(--text-primary, #0F172A);
  --sp-text-sec:       var(--text-secondary, #475569);
  --sp-text-muted:     var(--text-muted, #64748B);
  --sp-accent:         #D97706;
  --sp-accent-strong:  #B45309;
  --sp-accent-bg:      rgba(217, 119, 6, 0.12);
  --sp-accent-border:  #F59E0B;
  --sp-blue:           #3B82F6;
  --sp-blue-bg:        rgba(59, 130, 246, 0.12);
  --sp-purple:         #8B5CF6;
  --sp-purple-bg:      rgba(139, 92, 246, 0.12);
  --sp-btn-bg:         #D97706;
  --sp-btn-fg:         #FFFFFF;
  --sp-btn-hover:      #B45309;
  --sp-ok-text:        #059669;
  --sp-ok-bg:          #ECFDF5;
  --sp-ok-border:      #A7F3D0;
  --sp-warn-text:      #B45309;
  --sp-warn-bg:        #FFFBEB;
  --sp-warn-border:    #FDE68A;
  --sp-err-text:       #DC2626;
  --sp-err-bg:         #FEF2F2;
  --sp-err-border:     #FECACA;
  --sp-font-sans:      'Inter', system-ui, -apple-system, sans-serif;
  --sp-font-mono:      ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  --sp-fs-xs:          0.75rem;
  --sp-fs-sm:          0.84375rem;
  --sp-fs-base:        0.925rem;
  --sp-fs-md:          1.05rem;
  --sp-fs-lg:          1.2rem;
  --sp-fs-xl:          1.55rem;
  --sp-radius-sm:      10px;
  --sp-radius:         16px;
  --sp-shadow:         0 4px 16px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.03);

  font-family: var(--sp-font-sans);
  color: var(--sp-text);
  font-size: var(--sp-fs-base);
  line-height: 1.55;
}
body.dark-mode .std-painel,
html.dark-mode .std-painel {
  --sp-bg:             #0F172A;
  --sp-surface:        #1E293B;
  --sp-surface-muted:  #0F172A;
  --sp-surface-tint:   rgba(245, 158, 11, 0.1);
  --sp-border:         #334155;
  --sp-border-strong:  #475569;
  --sp-text:           #F8FAFC;
  --sp-text-sec:       #CBD5E1;
  --sp-text-muted:     #94A3B8;
  --sp-accent:         #FBBF24;
  --sp-accent-strong:  #F59E0B;
  --sp-accent-bg:      rgba(245, 158, 11, 0.16);
  --sp-accent-border:  rgba(245, 158, 11, 0.45);
  --sp-btn-bg:         #F59E0B;
  --sp-btn-fg:         #0F172A;
  --sp-btn-hover:      #FBBF24;
  --sp-ok-text:        #34D399;
  --sp-ok-bg:          rgba(16, 185, 129, 0.14);
  --sp-ok-border:      rgba(16, 185, 129, 0.35);
  --sp-warn-text:      #FCD34D;
  --sp-warn-bg:        rgba(245, 158, 11, 0.14);
  --sp-warn-border:    rgba(245, 158, 11, 0.35);
  --sp-err-text:       #F87171;
  --sp-err-bg:         rgba(239, 68, 68, 0.14);
  --sp-err-border:     rgba(239, 68, 68, 0.35);
  --sp-shadow:         0 8px 24px rgba(0, 0, 0, 0.35);
}
.std-painel .sp-num { font-variant-numeric: tabular-nums; }
.std-painel .sp-code { font-family: var(--sp-font-mono); font-variant-numeric: tabular-nums; font-size: var(--sp-fs-sm); }

/* Barra de Pré-visualização Admin (Estilo Indigo SaaS igual ao Painel de Mentor) */
.std-preview-banner {
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
  padding: 12px 20px; margin-bottom: 22px;
  background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
  border: 1px solid #C7D2FE; border-left: 4px solid #4F46E5;
  border-radius: 12px; color: #312E81; font-size: 0.85rem;
  box-shadow: 0 2px 10px rgba(79, 70, 229, 0.08);
}
body.dark-mode .std-preview-banner {
  background: linear-gradient(135deg, rgba(79, 70, 229, 0.18), rgba(67, 56, 202, 0.12));
  border-color: rgba(99, 102, 241, 0.4); color: #E0E7FF;
}
.std-preview-select {
  padding: 6px 12px; border-radius: 8px; border: 1px solid #A5B4FC;
  background: #FFFFFF; color: #1E1B4B; font-size: 0.82rem; font-weight: 600; cursor: pointer;
}
body.dark-mode .std-preview-select {
  background: #1E293B; color: #F8FAFC; border-color: #4F46E5;
}

/* Hero / Command Center Dinâmico da Startup (Contraste Navy + Amber SaaS) */
.std-command-hero {
  background: linear-gradient(135deg, #0F172A 0%, #1E293B 62%, #292524 100%);
  border: 1px solid rgba(245, 158, 11, 0.32);
  border-radius: 18px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.08);
  padding: 26px 30px;
  margin-bottom: 22px;
  position: relative;
  overflow: hidden;
  color: #F8FAFC;
}
.std-command-hero::before {
  content: '';
  position: absolute;
  top: -80px;
  right: -60px;
  width: 280px;
  height: 280px;
  background: radial-gradient(circle, rgba(245, 158, 11, 0.22) 0%, rgba(59, 130, 246, 0.08) 55%, transparent 72%);
  pointer-events: none;
}
.std-command-hero::after {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 4px;
  background: linear-gradient(90deg, #F59E0B 0%, #10B981 50%, #3B82F6 100%);
}
.std-hero-glass {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 14px;
  padding: 14px 18px;
  backdrop-filter: blur(8px);
}
.std-hero-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 9px 16px; border-radius: 10px;
  font-size: 0.82rem; font-weight: 700; text-decoration: none;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.09); color: #F8FAFC;
  cursor: pointer; transition: all 0.18s ease;
}
.std-hero-btn:hover {
  background: rgba(255, 255, 255, 0.18); color: #FFFFFF;
  transform: translateY(-2px);
}
.std-hero-btn-amber {
  background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
  border-color: #FBBF24; color: #0F172A;
  box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
}
.std-hero-btn-amber:hover {
  background: linear-gradient(135deg, #FBBF24 0%, #F59E0B 100%);
  color: #0F172A;
}

/* KPI Cards Interativos Clicáveis */
.std-kpi-interactive {
  cursor: pointer;
  position: relative;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.std-kpi-interactive:hover {
  transform: translateY(-4px);
  border-color: var(--sp-accent) !important;
  box-shadow: 0 10px 24px rgba(217, 119, 6, 0.12);
}

/* Stepper Circular Conectado & Interativo (Jornada de Maturidade) */
.std-journey-wrap {
  background: var(--sp-surface);
  border: 1px solid var(--sp-border);
  border-radius: var(--sp-radius);
  box-shadow: var(--sp-shadow);
  padding: 22px 26px;
  margin-bottom: 22px;
}
.std-journey-track {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  position: relative;
  margin: 22px 0 18px;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 6px;
}
.std-journey-track::before {
  content: '';
  position: absolute;
  top: 23px;
  left: 40px;
  right: 40px;
  height: 4px;
  background: var(--sp-border);
  z-index: 1;
  border-radius: 4px;
}
.std-journey-node {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  min-width: 105px;
  flex: 1;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  color: inherit;
  transition: transform 0.18s ease;
}
.std-journey-node:hover { transform: translateY(-3px); }
.std-journey-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--sp-surface);
  border: 3px solid var(--sp-border-strong);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
  color: var(--sp-text-muted);
  margin-bottom: 9px;
  transition: all 0.25s ease;
  box-shadow: 0 3px 10px rgba(15, 23, 42, 0.06);
}
.std-journey-node.is-done .std-journey-circle {
  background: #10B981;
  border-color: #10B981;
  color: #FFFFFF;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}
.std-journey-node.is-current .std-journey-circle {
  background: #D97706;
  border-color: #F59E0B;
  color: #FFFFFF;
  box-shadow: 0 0 0 6px rgba(245, 158, 11, 0.2), 0 6px 16px rgba(217, 119, 6, 0.35);
}
.std-journey-node.is-selected .std-journey-circle {
  outline: 3px solid #3B82F6;
  outline-offset: 3px;
}
.std-journey-label {
  font-size: 0.78rem;
  font-weight: 800;
  color: var(--sp-text);
  line-height: 1.2;
}
.std-journey-sub {
  font-size: 0.68rem;
  font-weight: 600;
  color: var(--sp-text-muted);
  margin-top: 2px;
}
.std-journey-drawer {
  background: var(--sp-surface-muted);
  border: 1px solid var(--sp-border);
  border-left: 4px solid var(--sp-accent);
  border-radius: 12px;
  padding: 16px 20px;
  transition: all 0.2s ease;
}

/* Catálogo Interativo de Espaços & Pódio de Ranking */
.std-space-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 14px;
}
.std-space-card {
  background: var(--sp-surface);
  border: 1px solid var(--sp-border);
  border-radius: 14px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 12px;
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
.std-space-card:hover {
  transform: translateY(-3px);
  border-color: var(--sp-accent);
  box-shadow: var(--sp-shadow);
}
.std-podium-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
  margin-bottom: 22px;
}
.std-podium-card {
  background: var(--sp-surface);
  border: 1px solid var(--sp-border);
  border-radius: 16px;
  padding: 20px;
  position: relative;
  overflow: hidden;
  box-shadow: var(--sp-shadow);
  transition: transform 0.2s ease;
}
.std-podium-card:hover { transform: translateY(-4px); }
.std-podium-card.rank-1 {
  border-top: 4px solid #F59E0B;
  background: linear-gradient(180deg, var(--sp-warn-bg) 0%, var(--sp-surface) 65%);
}
.std-podium-card.rank-2 { border-top: 4px solid #94A3B8; }
.std-podium-card.rank-3 { border-top: 4px solid #D97706; }

/* Hero / Cabeçalho Dinâmico da Startup */
.std-hero-card {
  background: var(--sp-surface);
  border: 1px solid var(--sp-border);
  border-radius: var(--sp-radius);
  box-shadow: var(--sp-shadow);
  padding: 24px 28px;
  margin-bottom: 22px;
  position: relative;
  overflow: hidden;
}
.std-hero-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
  background: linear-gradient(90deg, #D97706 0%, #F59E0B 45%, #3B82F6 100%);
}
.std-live-pill {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 5px 12px; border-radius: 999px;
  font-size: 0.74rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
}
.std-live-dot {
  width: 8px; height: 8px; border-radius: 50%; display: inline-block;
  animation: stdPulseDot 2s infinite;
}
@keyframes stdPulseDot {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.35); opacity: 0.55; }
}

/* Pipeline Stepper Interativo */
.std-pipeline {
  display: grid; gap: 10px;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--sp-border);
}
.std-pipe-step {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; border-radius: 11px;
  background: var(--sp-surface-muted); border: 1px solid var(--sp-border);
  transition: transform 0.2s ease, border-color 0.2s ease;
}
.std-pipe-step.is-done {
  background: var(--sp-ok-bg); border-color: var(--sp-ok-border); color: var(--sp-ok-text);
}
.std-pipe-step.is-active {
  background: var(--sp-warn-bg); border-color: var(--sp-accent-border); color: var(--sp-warn-text);
  box-shadow: 0 2px 10px rgba(217, 119, 6, 0.12);
}
.std-pipe-icon {
  width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 0.78rem; font-weight: 800;
  background: rgba(100, 116, 139, 0.15); color: var(--sp-text-muted);
}
.std-pipe-step.is-done .std-pipe-icon { background: #10B981; color: #FFFFFF; }
.std-pipe-step.is-active .std-pipe-icon { background: #D97706; color: #FFFFFF; }

/* Checklist Interativa de Prontidão do Dossiê */
.std-check-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px; }
.std-check-card {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
  padding: 14px 16px; border-radius: 12px;
  background: var(--sp-surface-muted); border: 1px solid var(--sp-border);
  transition: border-color 0.2s ease, transform 0.2s ease;
}
.std-check-card:hover { transform: translateY(-2px); border-color: var(--sp-accent-border); }
.std-check-card.done { background: var(--sp-ok-bg); border-color: var(--sp-ok-border); }

/* Timeline de Atividade em Tempo Real */
.std-feed-item {
  display: flex; gap: 12px; align-items: flex-start;
  padding: 11px 0; border-bottom: 1px solid var(--sp-border);
}
.std-feed-item:last-child { border-bottom: none; }
.std-feed-dot {
  width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 0.78rem;
}

/* Compatibilidade com classes sp-* existentes nas páginas secundárias */
.std-painel .sp-header {
  background: var(--sp-surface); border: 1px solid var(--sp-border); border-top: 4px solid var(--sp-accent);
  border-radius: var(--sp-radius); box-shadow: var(--sp-shadow); padding: 22px 26px; margin-bottom: 22px;
}
.std-painel .sp-header-top { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 8px; }
.std-painel .sp-kicker { font-size: var(--sp-fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--sp-text-muted); margin: 0; }
.std-painel .sp-title { font-size: var(--sp-fs-xl); font-weight: 800; color: var(--sp-text); letter-spacing: -0.02em; line-height: 1.25; margin: 0; }
.std-painel .sp-meta-row { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 12px; font-size: var(--sp-fs-sm); color: var(--sp-text-sec); }
.std-painel .sp-meta-pill {
  display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;
  background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-radius: 8px;
  font-size: var(--sp-fs-xs); font-weight: 600; color: var(--sp-text-sec);
}
.std-painel .sp-meta-pill i { color: var(--sp-accent); }
.std-painel .sp-metrics-box {
  display: inline-flex; align-items: center; gap: 14px; padding: 7px 14px;
  background: var(--sp-surface-tint); border: 1px solid var(--sp-warn-border); border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm);
}
.std-painel .sp-surface-primary {
  background: var(--sp-surface); border: 1px solid var(--sp-border); border-radius: var(--sp-radius);
  box-shadow: var(--sp-shadow); padding: 22px 26px; margin-bottom: 22px;
}
.std-painel .sp-card-head {
  display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px;
  padding-bottom: 14px; margin-bottom: 16px; border-bottom: 1px solid var(--sp-border);
}
.std-painel .sp-card-head-title { display: flex; align-items: center; gap: 12px; }
.std-painel .sp-icon-badge {
  width: 40px; height: 40px; border-radius: 11px; background: var(--sp-accent-bg); color: var(--sp-accent);
  display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;
}
.std-painel .sp-surface-aside {
  background: var(--sp-surface); border: 1px solid var(--sp-border); border-radius: var(--sp-radius);
  box-shadow: var(--sp-shadow); padding: 8px 20px;
}
.std-painel .sp-aside-block { display: flex; align-items: flex-start; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--sp-border); }
.std-painel .sp-aside-block:last-child { border-bottom: none; }
.std-painel .sp-aside-icon {
  width: 36px; height: 36px; border-radius: 9px; background: var(--sp-blue-bg);
  color: var(--sp-blue); display: inline-flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; margin-top: 2px;
}
.std-painel .sp-aside-body { flex: 1; min-width: 0; }
.std-painel .sp-list {
  list-style: none; padding: 0; margin: 0 0 22px; background: var(--sp-surface);
  border: 1px solid var(--sp-border); border-radius: var(--sp-radius); box-shadow: var(--sp-shadow); overflow: hidden;
}
.std-painel .sp-list-row {
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
  padding: 15px 20px; border-bottom: 1px solid var(--sp-border); font-size: var(--sp-fs-sm);
}
.std-painel .sp-list-row:last-child { border-bottom: none; }
.std-painel .sp-chip {
  display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px; border-radius: 999px;
  font-size: var(--sp-fs-xs); font-weight: 700; line-height: 1.4; border: 1px solid transparent; white-space: nowrap;
}
.std-painel .sp-chip-ok { color: var(--sp-ok-text); background: var(--sp-ok-bg); border-color: var(--sp-ok-border); }
.std-painel .sp-chip-warn { color: var(--sp-warn-text); background: var(--sp-warn-bg); border-color: var(--sp-warn-border); }
.std-painel .sp-chip-err { color: var(--sp-err-text); background: var(--sp-err-bg); border-color: var(--sp-err-border); }
.std-painel .sp-chip-neutral { color: var(--sp-text-sec); background: var(--sp-surface-muted); border-color: var(--sp-border); }
.std-painel .sp-btn-primary {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px;
  padding: 8px 18px; background: var(--sp-btn-bg); color: var(--sp-btn-fg); border: none;
  border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm); font-weight: 600; text-decoration: none; cursor: pointer;
  transition: transform 0.15s ease, background 0.15s ease;
}
.std-painel .sp-btn-primary:hover { background: var(--sp-btn-hover); color: var(--sp-btn-fg); transform: translateY(-1px); }
.std-painel .sp-btn-secondary {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 38px;
  padding: 7px 15px; background: var(--sp-surface-muted); color: var(--sp-text); border: 1px solid var(--sp-border);
  border-radius: var(--sp-radius-sm); font-size: var(--sp-fs-sm); font-weight: 600; text-decoration: none; cursor: pointer;
}
.std-painel .sp-btn-secondary:hover { background: var(--sp-border); color: var(--sp-text); }
.std-painel .sp-link {
  display: inline-flex; align-items: center; gap: 6px; min-height: 30px; color: var(--sp-accent);
  font-size: var(--sp-fs-sm); font-weight: 600; text-decoration: none;
}
.std-painel .sp-link:hover { text-decoration: underline; color: var(--sp-accent-strong); }
.std-painel .sp-empty-line {
  display: flex; align-items: center; gap: 10px; min-height: 52px; padding: 14px 18px;
  font-size: var(--sp-fs-sm); color: var(--sp-text-muted); background: var(--sp-surface-muted);
  border: 1px dashed var(--sp-border-strong); border-radius: var(--sp-radius-sm);
}
.std-painel .sp-tabs {
  display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
  padding: 6px; margin-bottom: 22px; background: var(--sp-surface);
  border: 1px solid var(--sp-border); border-radius: 12px; box-shadow: var(--sp-shadow);
}
.std-painel .sp-tab-btn {
  display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px;
  border-radius: 9px; border: none; background: transparent; color: var(--sp-text-sec);
  font-size: var(--sp-fs-sm); font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.15s ease;
}
.std-painel .sp-tab-btn:hover { background: var(--sp-surface-muted); color: var(--sp-text); }
.std-painel .sp-tab-btn.active { background: var(--sp-btn-bg); color: var(--sp-btn-fg); box-shadow: 0 2px 8px rgba(217,119,6,0.25); }
.std-painel .sp-table { width: 100%; border-collapse: collapse; font-size: var(--sp-fs-sm); }
.std-painel .sp-table th {
  text-align: left; font-size: var(--sp-fs-xs); font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.05em; color: var(--sp-text-muted); padding: 13px 16px;
  background: var(--sp-surface-muted); border-bottom: 1px solid var(--sp-border);
}
.std-painel .sp-table td {
  padding: 14px 16px; border-bottom: 1px solid var(--sp-border); color: var(--sp-text); vertical-align: middle;
}
.std-painel .sp-table tr:last-child td { border-bottom: none; }
</style>
<?php endif; ?>
</head>
<body>
<script>
if (document.documentElement.classList.contains('dark-mode')) {
    document.body.classList.add('dark-mode');
}
</script>

<!-- ═══════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- Logo & Notificações -->
    <div class="sidebar-brand-wrapper" style="padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 10px;">
        <div class="d-flex align-items-center justify-content-between">
            <a href="/incubadora_ispsn/public/website/" style="text-decoration: none; flex: 1;">
                <div style="padding: 0; width: 100%; max-width: 180px; display: flex; align-items: center; justify-content: flex-start;">
                    <img src="/incubadora_ispsn/assets/img/logo_sn_transparent.png" alt="Incubadora ISPSN" style="width: 100%; height: auto; max-height: 60px; object-fit: contain; filter: brightness(0) invert(1); opacity: 0.95;">
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
                        <li class="p-4 text-center text-muted small">A carregar...</li>
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
        <?php foreach ($itemsMenu as $item):
            $iconClass = trim($item['icon']);
            if (!preg_match('/\b(fa-solid|fa-regular|fa-brands)\b/', $iconClass)) {
                $iconClass = 'fa-solid ' . $iconClass;
            }
        ?>
        <a href="<?= $item['href'] ?>"
           class="nav-item <?= ($paginaActiva === $item['id']) ? 'active' : '' ?>">
            <i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?> nav-icon"></i>
            <span><?= $item['label'] ?></span>
            <?php if ($item['id'] === 'projetos' && $perfil !== 'utilizador'): ?>
                <!-- badge dinâmico pode ir aqui -->
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Footer sidebar -->
    <div class="sidebar-footer">
        <?php if (!empty($isSimulando)): ?>
        <a href="/incubadora_ispsn/public/index.php" class="nav-item mb-1" style="color: #FBBF24;">
            <i class="fa-solid fa-arrow-left nav-icon"></i>
            <span>Voltar ao Painel Admin</span>
        </a>
        <?php endif; ?>
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
            <!-- Alternador Dark Mode -->
            <button class="topbar-btn" type="button" id="btnDarkMode" onclick="toggleDarkMode()" title="Alternar Tema" style="margin-right: 4px;">
                <i class="fa fa-moon" id="darkModeIcon"></i>
            </button>

            <!-- Notificações -->
            <div class="dropdown">
                <button class="topbar-btn" type="button" id="btnNotif" data-bs-toggle="dropdown" aria-expanded="false" title="Notificações">
                    <i class="fa fa-bell"></i>
                    <span id="notif-badge-topbar" class="notif-badge <?= ($naoLidas > 0) ? '' : 'd-none' ?>"><?= $naoLidas ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="btnNotif" style="width:300px; border:none; border-radius:var(--radius); padding:0; overflow:hidden; margin-top:10px;">
                    <li class="p-3 border-bottom d-flex justify-content-between align-items-center" style="background:var(--surface-2)">
                        <span class="fw-bold" style="font-size:0.85rem">Notificações</span>
                        <button class="btn-ghost btn-sm p-0" onclick="marcarLidas()" style="font-size:0.7rem; color:var(--primary); background:none; border:none;">Limpar todas</button>
                    </li>
                    <div id="notif-list-topbar" style="max-height:300px; overflow-y:auto;">
                        <li class="p-4 text-center text-muted small">A carregar...</li>
                    </div>
                </ul>
            </div>

            <!-- Avatar -->
            <div class="topbar-avatar" title="<?= htmlspecialchars($nomeUsuario) ?>">
                <?= $iniciais ?>
            </div>
        </div>
    </header>

    <!-- ZONA DE CONTEÚDO — cada página injeta o seu conteúdo aqui -->
    <div class="page-content">
    <?php if (!empty($isSimulando)): ?>
        <div class="std-preview-banner" role="region" aria-label="Modo de pré-visualização de administrador">
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="width:36px; height:36px; border-radius:9px; background:rgba(79,70,229,0.14); color:#4F46E5; display:inline-flex; align-items:center; justify-content:center; font-size:0.95rem; flex-shrink:0;" aria-hidden="true">
                    <i class="fa-solid fa-eye"></i>
                </span>
                <div>
                    <div style="font-weight:700; font-size:0.86rem;">Modo de Inspecção &amp; Interação: a operar no módulo de <strong><?= htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div style="font-size:0.75rem; opacity:0.85;">Controlo total ativo · Todas as ferramentas, modais, simulador e abas estão habilitados</div>
                </div>
            </div>
            <div style="display:flex; align-items:center; flex-wrap:wrap; gap:10px;">
                <form method="GET" class="m-0">
                    <?php foreach ($_GET as $gk => $gv):
                        if ($gk === 'id_usuario' || !is_scalar($gv)) continue;
                    ?>
                        <input type="hidden" name="<?= htmlspecialchars((string)$gk, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$gv, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>
                    <label for="selectEstudanteGlobal" class="visually-hidden">Selecionar estudante</label>
                    <select id="selectEstudanteGlobal" name="id_usuario" class="std-preview-select" onchange="this.form.submit()">
                        <?php
                        $estGlobalList = $mysqli->query("
                            SELECT u.id, u.nome,
                                   (SELECT p.titulo FROM projetos p WHERE p.criado_por = u.id ORDER BY p.criado_em DESC LIMIT 1) AS proj_titulo
                            FROM usuarios u
                            WHERE u.perfil = 'utilizador' AND u.activo = 1
                            ORDER BY (proj_titulo IS NULL) ASC, u.nome ASC
                        ");
                        if ($estGlobalList):
                            while ($estG = $estGlobalList->fetch_assoc()):
                                $selG = ((int)$estG['id'] === (int)$idUsuario) ? 'selected' : '';
                                $subLbl = !empty($estG['proj_titulo']) ? $estG['proj_titulo'] : 'Sem projecto';
                        ?>
                            <option value="<?= (int)$estG['id'] ?>" <?= $selG ?>>
                                <?= htmlspecialchars($estG['nome'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($subLbl, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php
                            endwhile;
                        endif;
                        ?>
                    </select>
                </form>
                <a href="/incubadora_ispsn/public/index.php" class="btn-ghost" style="padding:6px 14px; font-size:0.78rem; background:#fff; color:#312E81; border-color:#C7D2FE;">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>Voltar ao Admin</span>
                </a>
            </div>
        </div>
    <?php endif; ?>


