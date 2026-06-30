<?php
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

obrigarLogin();

$realPerfil = $_SESSION['usuario_perfil'] ?? 'utilizador';
$isAdminOrSuper = in_array($realPerfil, ['admin', 'superadmin']);

if ($isAdminOrSuper) {
    // Admin can choose which student to view
    $idUsuario = (int)($_GET['id_usuario'] ?? 0);
    if ($idUsuario <= 0) {
        // Fallback to first student project creator
        $fallback = $mysqli->query("
            SELECT u.id, u.nome 
            FROM usuarios u 
            JOIN projetos p ON p.criado_por = u.id 
            ORDER BY p.criado_em DESC LIMIT 1
        ")->fetch_assoc();
        $idUsuario = $fallback ? (int)$fallback['id'] : 0;
    }
    
    // Fetch student's name
    $stmtU = $mysqli->prepare("SELECT nome FROM usuarios WHERE id = ?");
    if ($stmtU) {
        $stmtU->bind_param('i', $idUsuario);
        $stmtU->execute();
        $uData = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();
        $nome = $uData['nome'] ?? 'Estudante';
    } else {
        $nome = 'Estudante';
    }
    
    // Override variables for layout
    $nomeUsuario = $nome;
    $perfil = 'utilizador'; // Force layout to render student menus
} else {
    $idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
    $nome = $_SESSION['usuario_nome'] ?? 'Utilizador';
}

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

    // --- METAS DO PROJETO ---
    $metasFase = [];
    $percentMetasConcluidas = 0;
    $metaActiva = null;
    if ($ultimoProjeto && $ultimoProjeto['estado'] === 'incubado') {
        $faseActual = $ultimoProjeto['fase'] ?? 'ideacao';
        
        $stmtM = $mysqli->prepare("
            SELECT mp.*, mpd.titulo as meta_titulo, mpd.descricao as meta_descricao,
                   mpd.evidencia_tipo, mpd.evidencia_desc, mpd.peso_percentual, mpd.prazo_dias
            FROM metas_projeto mp
            JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
            WHERE mp.id_projeto = ? AND mpd.fase = ?
            ORDER BY mpd.numero
        ");
        $idProj = $ultimoProjeto['id'];
        $stmtM->bind_param('is', $idProj, $faseActual);
        $stmtM->execute();
        $metasFase = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtM->close();
        
        $pesoTotal = 0;
        $pesoConcluido = 0;
        foreach ($metasFase as $m) {
            $pesoTotal += $m['peso_percentual'];
            if ($m['estado'] === 'concluida') {
                $pesoConcluido += $m['peso_percentual'];
            }
            if ($m['estado'] === 'activa' || $m['estado'] === 'reprovada') {
                $metaActiva = $m;
            }
        }
        $percentMetasConcluidas = $pesoTotal > 0 ? round(($pesoConcluido / $pesoTotal) * 100) : 0;
    }
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

<?php if ($isAdminOrSuper): ?>
    <div class="card-custom mb-4" style="background:#FFFDF5; border: 1px solid #FEF3C7; border-radius:12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <div class="card-body-custom d-flex align-items-center justify-content-between flex-wrap gap-3 py-3 px-4">
            <div>
                <h6 class="fw-bold mb-1" style="color:#B45309; font-size:0.95rem;"><i class="fa fa-eye me-2"></i>Visualização de Estudante (Painel Admin)</h6>
                <p class="small text-muted mb-0">Você está a ver o painel do estudante <strong><?= htmlspecialchars($nome) ?></strong> exatamente como ele o vê.</p>
            </div>
            <div>
                <form method="GET" class="d-flex align-items-center gap-2" style="margin:0;">
                    <select name="id_usuario" class="form-select" style="font-size:0.82rem; padding:6px 36px 6px 12px; border-radius:8px; border:1.5px solid #FCD34D;" onchange="this.form.submit()">
                        <?php
                        $estudantesList = $mysqli->query("
                            SELECT DISTINCT u.id, u.nome, p.titulo
                            FROM usuarios u
                            JOIN projetos p ON p.criado_por = u.id
                            ORDER BY u.nome
                        ");
                        if ($estudantesList) {
                            while ($est = $estudantesList->fetch_assoc()) {
                                $selected = $est['id'] == $idUsuario ? 'selected' : '';
                                echo "<option value='{$est['id']}' {$selected}>" . htmlspecialchars($est['nome']) . " (" . htmlspecialchars($est['titulo']) . ")</option>";
                            }
                        }
                        ?>
                    </select>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

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

<?php if ($ultimoProjeto && $ultimoProjeto['estado'] === 'incubado'): ?>
<!-- ══ METAS DA FASE ══ -->
<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-header py-3 bg-white border-bottom-0 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-gray-800"><i class="fa fa-bullseye text-warning me-2"></i>Jornada de Metas: <?= $faseLabel ?></h6>
        <span class="badge bg-warning text-dark font-weight-bold" style="padding: 5px 12px; border-radius: 8px; font-size: 0.78rem;"><?= $percentMetasConcluidas ?>% Concluído</span>
    </div>
    <div class="card-body">
        <div class="progress mb-4" style="height: 10px; border-radius: 5px; background: #F1F5F9;">
            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $percentMetasConcluidas ?>%" aria-valuenow="<?= $percentMetasConcluidas ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <div class="row">
            <!-- Coluna da Esquerda: Lista de Metas Detalhada -->
            <div class="col-lg-8">
                <?php if (empty($metasFase)): ?>
                    <div class="text-center p-5 text-muted border border-dashed rounded-4 bg-light">
                        <i class="fa fa-info-circle fa-2x mb-2 text-warning" style="opacity:0.6;"></i>
                        <h6 class="fw-bold mb-1">Nenhuma meta ativa</h6>
                        <p class="small mb-0">Nenhuma meta foi inicializada para esta fase ainda. Aguarde a activação pelo Administrador.</p>
                    </div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <?php foreach ($metasFase as $m): 
                            $est = $m['estado'];
                            $itemStyle = '';
                            $badge = '';
                            if ($est === 'concluida') {
                                $itemStyle = 'border-left: 4px solid #10B981; background: #F8FAFC;';
                                $badge = '<span class="badge bg-success-subtle text-success fw-bold"><i class="fa fa-check-circle me-1"></i>Concluída</span>';
                            } elseif ($est === 'activa') {
                                $itemStyle = 'border-left: 4px solid #D97706; background: #FFFBEB;';
                                $badge = '<span class="badge bg-warning-subtle text-warning fw-bold"><i class="fa fa-bolt me-1"></i>Activa</span>';
                            } elseif ($est === 'em_avaliacao') {
                                $itemStyle = 'border-left: 4px solid #3B82F6; background: #EFF6FF;';
                                $badge = '<span class="badge bg-info-subtle text-info fw-bold"><i class="fa fa-clock me-1"></i>A avaliar</span>';
                            } elseif ($est === 'reprovada') {
                                $itemStyle = 'border-left: 4px solid #EF4444; background: #FEF2F2;';
                                $badge = '<span class="badge bg-danger-subtle text-danger fw-bold"><i class="fa fa-triangle-exclamation me-1"></i>Devolvida</span>';
                            } else {
                                $itemStyle = 'border-left: 4px solid #CBD5E1; opacity: 0.65;';
                                $badge = '<span class="badge bg-light text-muted fw-bold"><i class="fa fa-lock me-1"></i>Bloqueada</span>';
                            }
                        ?>
                            <div class="p-4 rounded-4 shadow-sm" style="border: 1px solid #E2E8F0; <?= $itemStyle ?>">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                    <div>
                                        <span class="badge bg-secondary-subtle text-secondary fw-bold rounded-pill px-2.5 py-1 mb-2" style="font-size:0.68rem; font-weight:700;">#<?= $m['numero'] ?></span>
                                        <h6 class="fw-bold mb-1 text-slate-800" style="font-size:0.95rem; display:inline-block; margin-left:8px;"><?= htmlspecialchars($m['meta_titulo']) ?></h6>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="badge bg-slate-100 text-slate-600 fw-bold border" style="font-size:0.7rem; padding: 4px 8px; border-radius: 6px;"><?= $m['peso_percentual'] ?>%</span>
                                        <?= $badge ?>
                                    </div>
                                </div>
                                
                                <p class="text-muted mb-3" style="font-size:0.8rem; line-height:1.5; text-align:left;"><?= htmlspecialchars($m['meta_descricao']) ?></p>
                                
                                <div class="p-3 rounded-3 mb-3 text-start" style="background:#fff; border: 1px solid #E2E8F0; font-size:0.75rem; line-height:1.6;">
                                    <div class="mb-1 text-slate-600"><strong><i class="fa fa-paperclip me-1 text-warning"></i>Entregável Esperado:</strong> <?= htmlspecialchars($m['evidencia_desc']) ?> (Tipo: <?= ucfirst($m['evidencia_tipo']) ?>)</div>
                                    <?php if (!empty($m['data_limite'])): 
                                        $atrasado = (strtotime($m['data_limite']) < strtotime(date('Y-m-d')) && $m['estado'] !== 'concluida');
                                    ?>
                                        <div class="text-slate-600">
                                            <strong><i class="fa fa-calendar me-1"></i>Prazo Limite:</strong> 
                                            <span class="fw-bold <?= $atrasado ? 'text-danger' : 'text-success' ?>">
                                                <?= date('d/m/Y', strtotime($m['data_limite'])) ?> <?= $atrasado ? '(ATRASADO)' : '(No prazo)' ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($m['feedback_mentor']): ?>
                                    <div class="alert alert-danger py-2 px-3 mb-3 text-start border-0" style="border-radius:10px; font-size:0.78rem; background:rgba(239, 68, 68, 0.08); color:#b91c1c;">
                                        <strong><i class="fa fa-triangle-exclamation me-1"></i>Feedback de Correção:</strong> <?= htmlspecialchars($m['feedback_mentor']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 text-start">
                                    <?php if ($est === 'activa' || $est === 'reprovada'): ?>
                                        <button type="button" class="btn btn-warning btn-sm fw-bold px-4 py-2 text-dark rounded-3" style="font-size:0.75rem; border:none;" 
                                                onclick="abrirSubmeterEvidencia(<?= htmlspecialchars(json_encode([
                                                    'id' => $m['id'],
                                                    'titulo' => $m['meta_titulo'],
                                                    'tipo' => $m['evidencia_tipo'],
                                                    'desc' => $m['evidencia_desc'],
                                                    'texto' => $m['evidencia_texto'] ?? '',
                                                    'link' => $m['evidencia_link'] ?? '',
                                                    'path' => $m['evidencia_path'] ?? ''
                                                ])) ?>)">
                                            <i class="fa fa-paper-plane me-1"></i>Submeter Evidência
                                        </button>
                                    <?php elseif ($est === 'em_avaliacao'): ?>
                                        <div class="text-info small fw-semibold" style="font-size:0.78rem;"><i class="fa fa-clock me-1"></i>Evidência submetida em <?= date('d/m/Y H:i', strtotime($m['evidencia_em'])) ?>. Aguarda avaliação do mentor.</div>
                                    <?php elseif ($est === 'concluida'): ?>
                                        <div class="text-success small fw-bold" style="font-size:0.78rem;"><i class="fa fa-circle-check me-1"></i>Meta Concluída! Startup ganhou +<?= round($m['peso_percentual']) ?> SP.</div>
                                    <?php else: ?>
                                        <div class="text-muted small" style="font-size:0.78rem;"><i class="fa fa-lock me-1"></i>Esta meta está bloqueada. Aguarde a activação.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Coluna da Direita: Regras de Avaliação e Indicadores -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm p-4 text-start" style="border-radius: 12px; background: #F8FAFC; border:1px solid #E2E8F0 !important;">
                    <h6 class="fw-bold mb-3" style="color: #1E293B;"><i class="fa fa-info-circle text-warning me-2"></i>Como Funciona?</h6>
                    
                    <ul class="list-unstyled mb-0" style="font-size:0.8rem; line-height:1.6; color:#475569; display:flex; flex-direction:column; gap:12px;">
                        <li>
                            <i class="fa fa-circle-dot text-warning me-2" style="font-size:0.6rem;"></i>
                            <strong>Ativação:</strong> O administrador seleciona e ativa uma meta de cada vez ou todas as metas da fase para a sua startup.
                        </li>
                        <li>
                            <i class="fa fa-circle-dot text-warning me-2" style="font-size:0.6rem;"></i>
                            <strong>Submissão:</strong> Submeta relatórios, links ou ficheiros comprovativos antes do final do prazo limite.
                        </li>
                        <li>
                            <i class="fa fa-circle-dot text-warning me-2" style="font-size:0.6rem;"></i>
                            <strong>Avaliação:</strong> O mentor avalia a qualidade da evidência entregue, atribui pontuação (1 a 5) e valida-a.
                        </li>
                        <li>
                            <i class="fa fa-circle-dot text-warning me-2" style="font-size:0.6rem;"></i>
                            <strong>Pontuação e Evolução:</strong> Cada meta concluída gera <strong>Startup Points (SP)</strong>. Completar todas as metas da fase permite avançar para a fase seguinte da Jornada.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
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
                    <!-- O Pitch foi removido do ato de inscrição (será solicitado na pré-incubação) -->
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

<!-- Modal Submeter Evidência -->
<div class="modal fade" id="modalSubmeterEvidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg" style="border-radius: 18px; overflow: hidden;">
            <form action="/incubadora_ispsn/app/controllers/metas_action.php" method="POST" enctype="multipart/form-data" class="m-0">
                <input type="hidden" name="action" value="submeter_evidencia">
                <input type="hidden" name="id_meta_projeto" id="submeterMetaId">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
                
                <div style="padding:22px 24px; border-bottom:1px solid #F3F4F6; background:#FFFBF2;">
                    <h5 class="modal-title fw-bold" id="submeterTituloMeta" style="color:#1C1917; margin:0;"><i class="fa fa-paper-plane text-warning me-2"></i>Submeter Evidência</h5>
                </div>
                
                <div style="padding:24px;">
                    <div class="alert alert-warning py-2 px-3 small border-0 mb-3 text-start" style="border-radius:10px; font-size:0.75rem; background:rgba(217,119,6,0.08); color:#b45309;">
                        <strong>Entregável esperado:</strong> <span id="submeterDescEvidencia"></span>
                    </div>
                    
                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold text-gray-700">Relatório/Descrição da Execução *</label>
                        <textarea name="evidencia_texto" id="submeterTextoInput" class="form-control rounded-3" rows="4" required placeholder="Especifique em detalhe as tarefas que realizou..."></textarea>
                    </div>
                    
                    <div class="mb-3 text-start" id="submeterLinkContainer">
                        <label class="form-label small fw-bold text-gray-700">Link Externo (URL) *</label>
                        <input type="url" name="evidencia_link" id="submeterLinkInput" class="form-control rounded-3" placeholder="https://exemplo.com/doc-ou-drive">
                    </div>
                    
                    <div class="mb-0 text-start" id="submeterFicheiroContainer">
                        <label class="form-label small fw-bold text-gray-700">Ficheiro Comprovativo (PDF, Zip, Imagem) *</label>
                        <input type="file" name="evidencia_ficheiro" id="submeterFicheiroInput" class="form-control" style="border-radius:10px;">
                        <div id="submeterFicheiroAtual" class="small text-muted mt-1"></div>
                    </div>
                </div>
                
                <div style="padding:16px 24px; border-top:1px solid #F3F4F6; display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4">Enviar Evidência</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirSubmeterEvidencia(meta) {
    document.getElementById('submeterMetaId').value = meta.id;
    document.getElementById('submeterTituloMeta').innerHTML = `<i class="fa fa-paper-plane text-warning me-2"></i>Submeter: ${meta.titulo}`;
    document.getElementById('submeterDescEvidencia').textContent = `${meta.desc} (Tipo: ${meta.tipo.toUpperCase()})`;
    document.getElementById('submeterTextoInput').value = meta.texto;
    
    const linkContainer = document.getElementById('submeterLinkContainer');
    const linkInput = document.getElementById('submeterLinkInput');
    const fileContainer = document.getElementById('submeterFicheiroContainer');
    const fileInput = document.getElementById('submeterFicheiroInput');
    
    // Reset validations and values
    linkInput.value = meta.link;
    fileInput.value = '';
    
    if (meta.tipo === 'link') {
        linkContainer.style.display = 'block';
        linkInput.required = true;
        fileContainer.style.display = 'none';
        fileInput.required = false;
    } else if (meta.tipo === 'ficheiro') {
        linkContainer.style.display = 'block'; 
        linkInput.required = false;
        fileContainer.style.display = 'block';
        
        // Se já tiver ficheiro enviado, não torna obrigatório enviar outro
        if (meta.path) {
            fileInput.required = false;
            document.getElementById('submeterFicheiroAtual').innerHTML = `<i class="fa fa-file me-1"></i>Ficheiro atual: <a href="/incubadora_ispsn/${meta.path}" target="_blank" class="fw-bold text-warning">Ver Ficheiro</a>`;
        } else {
            fileInput.required = true;
            document.getElementById('submeterFicheiroAtual').textContent = '';
        }
    } else {
        // Apenas texto
        linkContainer.style.display = 'none';
        linkInput.required = false;
        fileContainer.style.display = 'none';
        fileInput.required = false;
    }
    
    new bootstrap.Modal(document.getElementById('modalSubmeterEvidencia')).show();
}
</script>

<?php
require_once __DIR__ . '/../partials/_layout_end.php';
?>
