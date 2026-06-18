<?php
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

obrigarLogin();

$idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
$nome = $_SESSION['usuario_nome'] ?? 'Utilizador';

/* ============================
   COMENTÁRIOS DO ORIENTADOR
   ============================ */

// salvar comentário
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['novo_comentario'], $_POST['id_projeto'])
) {
    $comentario = trim($_POST['novo_comentario']);
    $idProjeto  = (int)$_POST['id_projeto'];
    $fase       = $_POST['fase'] ?? 'em_analise';

    if (strlen($comentario) >= 5) {
        $stmt = $mysqli->prepare("
            INSERT INTO comentarios_projetos
            (id_projeto, id_usuario, comentario, fase)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiss",
            $idProjeto,
            $_SESSION['usuario_id'],
            $comentario,
            $fase
        );
        $stmt->execute();
        $stmt->close();
    }
}


/* SUBMISSÃO DE PROJECTO — gerida por projeto_action.php via POST directo */
// (flash messages são lidas abaixo na view)

/* CONTADORES */
$stmt = $mysqli->prepare("SELECT COUNT(*) total FROM projetos WHERE criado_por=?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$meusProjetos = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $mysqli->prepare("SELECT COUNT(*) total FROM projetos WHERE criado_por=? AND tipo='pfc'");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$minhasPfcs = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $mysqli->prepare("
    SELECT *
    FROM projetos
    WHERE criado_por=?
    ORDER BY criado_em DESC LIMIT 1
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$ultimoProjeto = $stmt->get_result()->fetch_assoc();
$stmt->close();

$comentarios = [];

if ($ultimoProjeto) {
    $stmt = $mysqli->prepare("
        SELECT c.comentario, c.fase, c.criado_em, u.nome
        FROM comentarios_projetos c
        JOIN usuarios u ON u.id = c.id_usuario
        WHERE c.id_projeto = (
            SELECT id FROM projetos
            WHERE criado_por = ?
            ORDER BY criado_em DESC LIMIT 1
        )
        ORDER BY c.criado_em DESC
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $comentarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}


$mapaProgresso = [
    'submetido'          => 10,
    'em_avaliacao'       => 30,
    'aprovado'           => 50,
    'incubado'           => 75,
    'fundo_investimento' => 90,
    'concluido'          => 100,
    'rejeitado'          => 0
];
$progresso = $ultimoProjeto ? ($mapaProgresso[$ultimoProjeto['estado']] ?? 10) : 0;

$labels = [
    'submetido'          => 'Submetido (Em Triagem)',
    'em_avaliacao'       => 'Em Avaliação Técnica 🔍',
    'aprovado'           => 'Aprovado para Incubação ✓',
    'rejeitado'          => 'Rejeitado ✗',
    'incubado'           => 'Em Incubação 🚀',
    'fundo_investimento' => 'Pronto p/ Investimento 💰',
    'concluido'          => 'Graduado ✨'
];

$corBadge = [
    'submetido'    => 'secondary',
    'em_avaliacao' => 'warning',
    'aprovado'     => 'success',
    'rejeitado'    => 'danger',
    'incubado'     => 'primary',
    'fundo_investimento' => 'success',
    'concluido'    => 'success'
];

/* --- NOVOS DADOS: FINANCIAMENTOS E SESSÕES --- */
$totalFinanciamento = 0;
$sessoesMentoria = [];
$meusFinanciamentos = [];
$meusKpis = [];
$minhasTarefas = [];
$minhasAvaliacosMentor = [];
$mediaAvaliacaoAdmin = ['media' => 0, 'total' => 0];
$ultimasAvaliacoesOficiais = [];

if ($ultimoProjeto) {
    // Buscar total aprovado para o projeto mais recente
    $stmt = $mysqli->prepare("
        SELECT SUM(montante_aprovado) as total 
        FROM financiamentos 
        WHERE id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        AND estado IN ('activo', 'concluido')
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $resFin = $stmt->get_result()->fetch_assoc();
    $totalFinanciamento = $resFin['total'] ?? 0;
    $stmt->close();

    // Buscar sessões de mentoria
    $stmt = $mysqli->prepare("
        SELECT s.*, u.nome as mentor_nome
        FROM sessoes_mentoria s
        JOIN mentorias m ON m.id = s.id_mentoria
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u ON u.id = mt.id_usuario
        WHERE m.id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        ORDER BY s.data_sessao DESC
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $sessoesMentoria = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Buscar Financiamentos Activos (para o modal de despesas)
    $stmt = $mysqli->prepare("SELECT id, fonte FROM financiamentos WHERE id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1) AND estado='activo'");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $meusFinanciamentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Buscar KPIs Activos (para o modal de KPIs)
    $stmt = $mysqli->prepare("SELECT id, nome, unidade FROM kpis WHERE id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1) AND activo=1");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $meusKpis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // --- NOVOS DADOS: TAREFAS E REUNIÕES ---
    $stmt = $mysqli->prepare("SELECT * FROM tarefas WHERE id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1) ORDER BY status ASC, data_limite ASC");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $minhasTarefas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $minhasReunioes = [];
    $stmt = $mysqli->prepare("
        SELECT r.*, u.nome as mentor_nome
        FROM reunioes r
        JOIN usuarios u ON u.id = r.id_mentor
        WHERE r.id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        AND r.data_reuniao >= NOW()
        AND r.status = 'agendada'
        ORDER BY r.data_reuniao ASC
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $minhasReunioes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $minhasAvaliacosMentor = [];
    $stmt = $mysqli->prepare("
        SELECT av.*, u.nome as mentor_nome
        FROM avaliacoes_mentor av
        JOIN mentores m ON m.id = av.id_mentor
        JOIN usuarios u ON u.id = m.id_usuario
        WHERE av.id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        ORDER BY av.criado_em DESC
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $minhasAvaliacosMentor = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Buscar média das avaliações oficiais (Admin) e observações recentes
    $mediaAvaliacaoAdmin = null;
    $ultimasAvaliacoesOficiais = [];
    $stmt = $mysqli->prepare("
        SELECT pontuacao_total, observacoes, avaliado_em 
        FROM avaliacoes 
        WHERE id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        ORDER BY avaliado_em DESC
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $resultAval = $stmt->get_result();
    $ultimasAvaliacoesOficiais = $resultAval->fetch_all(MYSQLI_ASSOC);
    
    $totalAval = count($ultimasAvaliacoesOficiais);
    $somaAval = array_sum(array_column($ultimasAvaliacoesOficiais, 'pontuacao_total'));
    $mediaAvaliacaoAdmin = [
        'media' => $totalAval > 0 ? $somaAval / $totalAval : 0,
        'total' => $totalAval
    ];
    $stmt->close();

    // ── BUSCAR DOCUMENTOS DO PROJETO ──
    $meusDocumentos = [];
    $stmtDoc = $mysqli->prepare("
        SELECT f.*, u.nome as quem_submeteu, u.perfil as perfil_submeteu
        FROM ficheiros_projeto f
        JOIN usuarios u ON u.id = f.id_usuario_up
        WHERE f.id_projeto = (SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1)
        ORDER BY f.criado_em DESC
    ");
    $stmtDoc->bind_param('i', $idUsuario);
    $stmtDoc->execute();
    $meusDocumentos = $stmtDoc->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtDoc->close();

    // ── BUSCAR ESPAÇOS DISPONÍVEIS E RESERVAS ──
    $espacosDisponiveis = $mysqli->query("SELECT * FROM espacos WHERE status = 'disponivel' ORDER BY tipo, nome")->fetch_all(MYSQLI_ASSOC);

    $minhasReservasPainel = [];
    $stmtRes = $mysqli->prepare("
        SELECT r.*, e.nome as espaco_nome, e.tipo as espaco_tipo 
        FROM reservas_espaco r 
        JOIN espacos e ON e.id = r.id_espaco 
        WHERE r.id_usuario = ? 
        ORDER BY r.data_reserva DESC, r.hora_inicio DESC
        LIMIT 4
    ");
    $stmtRes->bind_param('i', $idUsuario);
    $stmtRes->execute();
    $minhasReservasPainel = $stmtRes->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtRes->close();
}
?>
<?php
$tituloPagina = 'Meu Painel';
$paginaActiva = 'dashboard';
// Flash messages
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);
require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
/* ── PAINEL GERAL ESTUDANTE ── */
:root { --amber: #D97706; --amber-dark: #B45309; --emerald: #10B981; }

/* Hero Banner */
.hero-banner {
    background: linear-gradient(135deg, #1C1917 0%, #292524 70%, #1C1917 100%);
    border-radius: 18px; padding: 30px 28px; color: #fff;
    position: relative; overflow: hidden; margin-bottom: 24px;
}
.hero-banner::before {
    content:''; position:absolute; width:300px; height:300px;
    background: radial-gradient(circle, rgba(217,119,6,0.2) 0%, transparent 70%);
    top:-120px; right:-60px; z-index:1; pointer-events:none;
}
.hero-banner-inner { position:relative; z-index:2; }
.hero-badges { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
.hero-badge {
    padding:3px 12px; border-radius:20px; font-size:0.72rem;
    font-weight:700; letter-spacing:0.3px;
}
.hb-sp   { background:rgba(217,119,6,0.25); color:#FBBF24; border:1px solid rgba(217,119,6,0.3); }
.hb-fase { background:rgba(16,185,129,0.2); color:#6EE7B7; border:1px solid rgba(16,185,129,0.3); }
.hero-name  { font-size:1.75rem; font-weight:800; letter-spacing:-0.4px; line-height:1.2; }
.hero-sub   { font-size:0.85rem; color:rgba(255,255,255,0.55); margin-top:4px; }
.hero-btns  { display:flex; flex-wrap:wrap; gap:10px; margin-top:18px; }
.hbtn {
    padding:9px 18px; border-radius:10px; font-size:0.83rem; font-weight:600;
    border:none; cursor:pointer; display:inline-flex; align-items:center; gap:7px;
    transition:all 0.2s; text-decoration:none;
}
.hbtn-primary { background:#D97706; color:#fff; }
.hbtn-primary:hover { background:#B45309; color:#fff; transform:translateY(-1px); }
.hbtn-outline { background:rgba(255,255,255,0.1); color:#fff; border:1px solid rgba(255,255,255,0.2); }
.hbtn-outline:hover { background:rgba(255,255,255,0.18); color:#fff; }

/* Mini Stats */
.mini-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
.mini-card {
    background:#fff; border-radius:14px; padding:16px 14px;
    text-align:center; border:1px solid #F3F4F6;
    box-shadow:0 2px 8px rgba(0,0,0,0.04); transition:transform 0.2s;
}
.mini-card:hover { transform:translateY(-2px); }
.mini-val { font-size:1.8rem; font-weight:800; color:#D97706; line-height:1; }
.mini-lbl { font-size:0.68rem; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; color:#9CA3AF; margin-top:5px; }
.mini-icon { font-size:1.1rem; color:#D97706; opacity:0.6; margin-bottom:8px; }

/* Pipeline */
.pipeline-card {
    background:#fff; border-radius:16px; border:1px solid #F3F4F6;
    box-shadow:0 2px 8px rgba(0,0,0,0.04); overflow:hidden; margin-bottom:24px;
}
.pipeline-card-header {
    padding:16px 20px; border-bottom:1px solid #F9FAFB;
    display:flex; align-items:center; justify-content:space-between;
}
.pipeline-card-title { font-weight:700; font-size:0.9rem; color:#1C1917; display:flex; align-items:center; gap:8px; }
.pipeline-card-body { padding:20px; overflow-x:auto; }
.pipe-row {
    display:flex; align-items:flex-start; justify-content:space-between;
    position:relative; min-width:360px; gap:4px;
}
.pipe-track {
    position:absolute; top:18px; left:6%; right:6%;
    height:3px; background:#F3F4F6; border-radius:6px; z-index:1;
}
.pipe-fill {
    height:100%; border-radius:6px;
    background:linear-gradient(90deg, #10B981, #D97706);
    transition:width 0.8s ease;
}
.pipe-step { position:relative; z-index:2; display:flex; flex-direction:column; align-items:center; flex:1; }
.pipe-dot {
    width:36px; height:36px; border-radius:50%; border:3px solid #E5E7EB; background:#fff;
    display:flex; align-items:center; justify-content:center; color:#9CA3AF; font-size:0.85rem;
    box-shadow:0 2px 6px rgba(0,0,0,0.06); transition:all 0.3s; margin-bottom:7px;
}
.pipe-step.done   .pipe-dot { background:#10B981; border-color:#10B981; color:#fff; }
.pipe-step.active .pipe-dot {
    background:#D97706; border-color:#D97706; color:#fff;
    transform:scale(1.18); box-shadow:0 0 0 5px rgba(217,119,6,0.18);
}
.pipe-lbl {
    font-size:0.6rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.4px; color:#C4C9D4; text-align:center; white-space:nowrap;
}
.pipe-step.done   .pipe-lbl { color:#10B981; }
.pipe-step.active .pipe-lbl { color:#B45309; }

/* Quick Actions */
.qa-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
.qa-card {
    background:#fff; border-radius:14px; border:1px solid #F3F4F6;
    padding:20px 18px; display:flex; align-items:center; gap:14px;
    text-decoration:none; box-shadow:0 2px 8px rgba(0,0,0,0.04);
    transition:all 0.22s;
}
.qa-card:hover {
    border-color:#D97706; transform:translateY(-2px);
    box-shadow:0 8px 24px rgba(217,119,6,0.1); color:inherit;
}
.qa-icon {
    width:44px; height:44px; border-radius:12px; flex-shrink:0;
    background:rgba(217,119,6,0.08); color:#D97706;
    display:flex; align-items:center; justify-content:center; font-size:1.1rem;
}
.qa-label { font-weight:700; font-size:0.88rem; color:#1C1917; }
.qa-desc  { font-size:0.73rem; color:#9CA3AF; margin-top:2px; }
.qa-arrow { margin-left:auto; color:#D1D5DB; font-size:0.8rem; transition:transform 0.2s; }
.qa-card:hover .qa-arrow { transform:translateX(4px); color:#D97706; }

/* Progresso bar do projeto */
.proj-progress { margin-bottom:24px; }
.progress-track { height:8px; background:#F3F4F6; border-radius:8px; overflow:hidden; }
.progress-fill  { height:100%; border-radius:8px; background:linear-gradient(90deg, #10B981, #D97706); transition:width 0.8s ease; }

/* Sem projeto */
.empty-hero {
    background:#fff; border-radius:18px; padding:60px 40px;
    text-align:center; border:1px solid #F3F4F6;
    box-shadow:0 2px 8px rgba(0,0,0,0.04); max-width:560px; margin:40px auto;
}

/* Alerts flash */
.flash-ok  { background:#D1FAE5; color:#065F46; border-left:4px solid #10B981; padding:12px 16px; border-radius:10px; display:flex; align-items:center; gap:9px; font-size:0.87rem; margin-bottom:16px; }
.flash-err { background:#FEE2E2; color:#991B1B; border-left:4px solid #EF4444; padding:12px 16px; border-radius:10px; display:flex; align-items:center; gap:9px; font-size:0.87rem; margin-bottom:16px; }

/* Responsive */
@media (max-width: 992px) { .qa-grid { grid-template-columns:repeat(2,1fr); } .mini-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width: 576px)  { .qa-grid { grid-template-columns:1fr; } .mini-grid { grid-template-columns:repeat(2,1fr); } .hero-name { font-size:1.3rem; } .hero-banner { padding:22px 18px; } }
</style>

<?php if ($flashOk):   ?><div class="flash-ok"><i class="fa fa-check-circle"></i><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="flash-err"><i class="fa fa-triangle-exclamation"></i><?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<?php
$horaAtual = (int)date('H');
if ($horaAtual >= 5 && $horaAtual < 12)       $saudacao = 'Bom dia';
elseif ($horaAtual >= 12 && $horaAtual < 18)  $saudacao = 'Boa tarde';
else                                           $saudacao = 'Boa noite';
$primeiroNome = htmlspecialchars(explode(' ', $nome)[0]);
$pontos       = $ultimoProjeto['pontos'] ?? 0;
$fase         = $ultimoProjeto['fase']   ?? 'ideacao';
$faseLabel    = strtoupper(str_replace('_', ' ', $fase));
?>

<!-- ══ HERO ══ -->
<div class="hero-banner">
    <div class="hero-banner-inner">
        <div class="hero-badges">
            <span class="hero-badge hb-sp"><i class="fa fa-trophy me-1"></i><?= $pontos ?> SP</span>
            <?php if($ultimoProjeto): ?>
            <span class="hero-badge hb-fase"><i class="fa fa-circle-play me-1"></i><?= $faseLabel ?></span>
            <?php endif; ?>
        </div>
        <div class="hero-name"><?= $saudacao ?>, <?= $primeiroNome ?>! 👋</div>
        <div class="hero-sub">
            <?php if($ultimoProjeto): ?>
                Startup: <strong style="color:#FBBF24"><?= htmlspecialchars($ultimoProjeto['titulo']) ?></strong>
                &nbsp;·&nbsp; Progresso: <strong><?= $progresso ?>%</strong>
                &nbsp;·&nbsp; Fase: <strong><?= $faseLabel ?></strong>
            <?php else: ?>
                Bem-vindo à Incubadora Académica do ISPSN. Submeta a sua primeira ideia para começar.
            <?php endif; ?>
        </div>
        <?php if($ultimoProjeto): ?>
        <div class="mt-3" style="max-width:340px;">
            <div class="progress-track">
                <div class="progress-fill" style="width:<?= $progresso ?>%"></div>
            </div>
        </div>
        <?php endif; ?>
        <div class="hero-btns">
            <?php if($ultimoProjeto): ?>
                <a href="/incubadora_ispsn/app/views/utilizador/reservas.php" class="hbtn hbtn-primary"><i class="fa fa-calendar-plus"></i>Reservar Sala</a>
                <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php" class="hbtn hbtn-outline"><i class="fa fa-rocket"></i>Ver Startup</a>
            <?php else: ?>
                <button class="hbtn hbtn-primary" data-bs-toggle="modal" data-bs-target="#modalProjeto"><i class="fa fa-plus"></i>Submeter Ideia</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if($ultimoProjeto): ?>

<!-- ══ MINI STATS ══ -->
<div class="mini-grid">
    <div class="mini-card">
        <div class="mini-icon"><i class="fa fa-gauge-high"></i></div>
        <div class="mini-val"><?= $progresso ?>%</div>
        <div class="mini-lbl">Progresso</div>
    </div>
    <div class="mini-card">
        <div class="mini-icon"><i class="fa fa-list-check"></i></div>
        <div class="mini-val"><?= count(array_filter($minhasTarefas, fn($t)=> $t['validada_mentor']==1)) ?>/<?= count($minhasTarefas) ?></div>
        <div class="mini-lbl">Metas Validadas</div>
    </div>
    <div class="mini-card">
        <div class="mini-icon"><i class="fa fa-bookmark"></i></div>
        <div class="mini-val"><?= count($minhasReservasPainel) ?></div>
        <div class="mini-lbl">Reservas Activas</div>
    </div>
    <div class="mini-card">
        <div class="mini-icon"><i class="fa fa-trophy"></i></div>
        <div class="mini-val"><?= $pontos ?></div>
        <div class="mini-lbl">Startup Points</div>
    </div>
</div>

<!-- ══ PIPELINE ══ -->
<div class="pipeline-card">
    <div class="pipeline-card-header">
        <div class="pipeline-card-title"><i class="fa fa-road" style="color:#D97706"></i> Jornada de Maturidade</div>
        <small style="color:#9CA3AF; font-size:0.75rem;">Fase actual: <strong style="color:#D97706"><?= $faseLabel ?></strong></small>
    </div>
    <div class="pipeline-card-body">
        <?php
        $steps = [
            ['id'=>'ideacao',           'label'=>'Ideação',     'icon'=>'lightbulb'],
            ['id'=>'validacao',         'label'=>'Validação',   'icon'=>'vial'],
            ['id'=>'mvp',               'label'=>'MVP',         'icon'=>'cube'],
            ['id'=>'tracao',            'label'=>'Tração',      'icon'=>'chart-line'],
            ['id'=>'mercado',           'label'=>'Mercado',     'icon'=>'store'],
            ['id'=>'fundo_investimento','label'=>'Financiado',  'icon'=>'sack-dollar'],
        ];
        $currIdx = 0;
        foreach($steps as $i => $s) { if($fase === $s['id']) $currIdx = $i; }
        $pct = $currIdx > 0 ? round(($currIdx / (count($steps)-1)) * 84 + 8) : 8;
        ?>
        <div class="pipe-row">
            <div class="pipe-track"><div class="pipe-fill" style="width:<?= $pct ?>%"></div></div>
            <?php foreach($steps as $i => $s):
                $state = $i < $currIdx ? 'done' : ($i === $currIdx ? 'active' : '');
            ?>
            <div class="pipe-step <?= $state ?>">
                <div class="pipe-dot"><i class="fa fa-<?= $s['icon'] ?>"></i></div>
                <div class="pipe-lbl"><?= $s['label'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══ AÇÕES RÁPIDAS ══ -->
<div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#9CA3AF; margin-bottom:10px;">Acções Rápidas</div>
<div class="qa-grid">

    <a href="/incubadora_ispsn/app/views/utilizador/meu_projeto.php" class="qa-card">
        <div class="qa-icon"><i class="fa fa-rocket"></i></div>
        <div>
            <div class="qa-label">Minha Startup</div>
            <div class="qa-desc">Ver projecto, metas e progresso</div>
        </div>
        <i class="fa fa-chevron-right qa-arrow"></i>
    </a>

    <a href="/incubadora_ispsn/app/views/utilizador/reservas.php" class="qa-card">
        <div class="qa-icon"><i class="fa fa-calendar-check"></i></div>
        <div>
            <div class="qa-label">Reservas de Espaço</div>
            <div class="qa-desc">
                <?php
                $pendentes = count(array_filter($minhasReservasPainel, fn($r)=>$r['status']==='pendente'));
                $confirmadas = count(array_filter($minhasReservasPainel, fn($r)=>$r['status']==='confirmada'));
                echo $confirmadas ? "$confirmadas confirmada" . ($confirmadas>1?'s':'') : ($pendentes ? "$pendentes pendente" . ($pendentes>1?'s':'') : 'Nenhuma reserva activa');
                ?>
            </div>
        </div>
        <i class="fa fa-chevron-right qa-arrow"></i>
    </a>

    <a href="/incubadora_ispsn/app/views/admin/ranking.php" class="qa-card">
        <div class="qa-icon"><i class="fa fa-trophy"></i></div>
        <div>
            <div class="qa-label">Ranking de Startups</div>
            <div class="qa-desc">Ver posição entre os outros empreendedores</div>
        </div>
        <i class="fa fa-chevron-right qa-arrow"></i>
    </a>

</div>

<?php else: ?>
<!-- ══ SEM PROJETO ══ -->
<div class="empty-hero">
    <div style="font-size:3.5rem; color:#D97706; opacity:0.7; margin-bottom:20px;"><i class="fa fa-diagram-project"></i></div>
    <h4 style="font-weight:800; color:#1C1917; margin-bottom:8px;">Ainda sem projeto submetido</h4>
    <p style="color:#9CA3AF; font-size:0.88rem; max-width:380px; margin:0 auto 24px;">O ecossistema da Incubadora do ISPSN vai acompanhar a sua startup da ideia até ao financiamento. Comece agora.</p>
    <button class="hbtn hbtn-primary" style="margin:0 auto;" data-bs-toggle="modal" data-bs-target="#modalProjeto">
        <i class="fa fa-plus"></i> Submeter Primeira Ideia
    </button>
</div>
<?php endif; ?>

<!-- ══ MODAL SUBMETER PROJETO ══ -->
<div class="modal fade" id="modalProjeto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border:none; border-radius:18px; overflow:hidden;">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
                <input type="hidden" name="action" value="criar_projeto">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/public/index.php">
                <div style="padding:22px 24px; border-bottom:1px solid #F3F4F6; background:#FFFBF2;">
                    <h5 style="font-weight:800; margin:0; color:#1C1917;"><i class="fa fa-rocket me-2" style="color:#D97706"></i>Submeter Ideia de Startup</h5>
                </div>
                <div style="padding:24px;">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.78rem; font-weight:700; text-transform:uppercase; color:#6B7280; letter-spacing:0.4px;">Nome da Startup *</label>
                        <input type="text" name="titulo" class="form-control" required placeholder="Ex: AppAngola — Transporte Local">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:0.78rem; font-weight:700; text-transform:uppercase; color:#6B7280; letter-spacing:0.4px;">Tipo *</label>
                            <select name="tipo" class="form-control" required>
                                <option value="startup_tecnologica">Startup Tecnológica</option>
                                <option value="negocio_tradicional">Negócio Tradicional</option>
                                <option value="impacto_social">Impacto Social</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:0.78rem; font-weight:700; text-transform:uppercase; color:#6B7280; letter-spacing:0.4px;">Área</label>
                            <select name="area" class="form-control">
                                <option value="tecnologia">Tecnologia</option>
                                <option value="saude">Saúde</option>
                                <option value="educacao">Educação</option>
                                <option value="agro">Agronegócio</option>
                                <option value="financas">Finanças</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.78rem; font-weight:700; text-transform:uppercase; color:#6B7280; letter-spacing:0.4px;">Descrição *</label>
                        <textarea name="descricao" class="form-control" rows="3" required placeholder="Descreva o projecto..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.78rem; font-weight:700; text-transform:uppercase; color:#6B7280; letter-spacing:0.4px;">Problema que resolve</label>
                        <textarea name="problema" class="form-control" rows="2" placeholder="Qual o problema identificado?"></textarea>
                    </div>
                    <div>
                        <label class="form-label" style="font-size:0.78rem; font-weight:700; text-transform:uppercase; color:#6B7280; letter-spacing:0.4px;">Solução Proposta</label>
                        <textarea name="solucao" class="form-control" rows="2" placeholder="Como a sua startup resolve o problema?"></textarea>
                    </div>
                </div>
                <div style="padding:16px 24px; border-top:1px solid #F3F4F6; display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4"><i class="fa fa-paper-plane me-2"></i>Submeter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../partials/_layout_end.php';
?>
