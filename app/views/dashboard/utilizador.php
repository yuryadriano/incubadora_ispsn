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
/* ── DESIGN TOKENS ESTUDANTE ── */
:root {
    --amber: #D97706;
    --amber-dark: #B45309;
    --emerald: #10B981;
    --violet: #8B5CF6;
    --sky: #3B82F6;
}

/* ── GLASS CARDS ── */
.g-card {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(253,230,138,0.3);
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: transform 0.22s ease, box-shadow 0.22s ease;
}
.g-card:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(0,0,0,0.09); }
.g-card-header {
    padding: 18px 20px 14px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
}
.g-card-title { font-weight: 700; font-size: 0.95rem; color: #1C1917; display: flex; align-items: center; gap: 8px; }
.g-card-body { padding: 18px 20px; }

/* ── BANNER ── */
.hero-banner {
    background: linear-gradient(135deg, #1C1917 0%, #292524 60%, #1C1917 100%);
    border-radius: 16px;
    padding: 28px 24px;
    color: #fff;
    position: relative;
    overflow: hidden;
    margin-bottom: 24px;
}
.hero-banner::before {
    content: '';
    position: absolute;
    width: 280px; height: 280px;
    background: radial-gradient(circle, rgba(217,119,6,0.18) 0%, transparent 70%);
    top: -100px; right: -60px; z-index: 1;
    pointer-events: none;
}
.hero-banner::after {
    content: '';
    position: absolute;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(16,185,129,0.08) 0%, transparent 70%);
    bottom: -80px; left: 20px; z-index: 1;
    pointer-events: none;
}
.hero-banner-content { position: relative; z-index: 2; }
.hero-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.hero-badge {
    padding: 4px 12px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 700; letter-spacing: 0.3px;
}
.hero-badge-sp { background: rgba(217,119,6,0.25); color: #FBBF24; border: 1px solid rgba(217,119,6,0.3); }
.hero-badge-fase { background: rgba(16,185,129,0.2); color: #6EE7B7; border: 1px solid rgba(16,185,129,0.25); }
.hero-name { font-size: 1.7rem; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.3px; line-height: 1.2; }
.hero-subtitle { font-size: 0.85rem; color: rgba(255,255,255,0.6); }
.hero-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
.hero-btn {
    padding: 9px 16px; border-radius: 10px; font-size: 0.82rem;
    font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    transition: all 0.2s; text-decoration: none;
}
.hero-btn-primary { background: #D97706; color: #fff; }
.hero-btn-primary:hover { background: #B45309; transform: translateY(-1px); color: #fff; }
.hero-btn-outline { background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); }
.hero-btn-outline:hover { background: rgba(255,255,255,0.18); color: #fff; }

/* ── PIPELINE ── */
.pipeline-wrap {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    position: relative;
    padding: 10px 0 6px;
    overflow-x: auto;
    gap: 4px;
    -webkit-overflow-scrolling: touch;
}
.pipeline-wrap::-webkit-scrollbar { height: 4px; }
.pipeline-track {
    position: absolute;
    top: 28px; left: 5%; right: 5%;
    height: 3px; background: #E5E7EB;
    border-radius: 10px; z-index: 1;
}
.pipeline-fill {
    height: 100%; border-radius: 10px;
    background: linear-gradient(90deg, #10B981, #D97706);
    transition: width 0.8s ease;
}
.pipe-step {
    position: relative; z-index: 2;
    display: flex; flex-direction: column; align-items: center;
    min-width: 60px; flex: 1;
}
.pipe-icon {
    width: 40px; height: 40px; border-radius: 50%;
    border: 3px solid #E5E7EB; background: #fff;
    display: flex; align-items: center; justify-content: center;
    color: #9CA3AF; font-size: 0.9rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    transition: all 0.3s;
    margin-bottom: 8px;
}
.pipe-step.done .pipe-icon { background: #10B981; border-color: #10B981; color: #fff; }
.pipe-step.active .pipe-icon {
    background: #D97706; border-color: #D97706; color: #fff;
    transform: scale(1.15);
    box-shadow: 0 0 0 5px rgba(217,119,6,0.18);
}
.pipe-label {
    font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.4px; color: #9CA3AF; text-align: center; white-space: nowrap;
}
.pipe-step.done .pipe-label { color: #10B981; }
.pipe-step.active .pipe-label { color: #B45309; }

/* ── METAS ── */
.meta-item {
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: 12px; padding: 14px 16px;
    border-radius: 12px; border: 1px solid #F3F4F6;
    background: #FAFAFA;
    margin-bottom: 8px; transition: border-color 0.2s;
}
.meta-item:hover { border-color: #D97706; background: #FFFBF2; }
.meta-item:last-child { margin-bottom: 0; }
.meta-dot {
    width: 22px; height: 22px; border-radius: 50%;
    border: 2px solid #D1D5DB; flex-shrink: 0; margin-top: 1px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.65rem; color: transparent; transition: all 0.2s;
}
.meta-item.is-valid .meta-dot { background: #10B981; border-color: #10B981; color: #fff; }
.meta-item.is-wait .meta-dot  { background: #F59E0B; border-color: #F59E0B; color: #fff; }
.meta-item.is-prog .meta-dot  { background: #3B82F6; border-color: #3B82F6; color: #fff; }
.meta-title { font-weight: 600; font-size: 0.88rem; color: #1C1917; }
.meta-desc  { font-size: 0.76rem; color: #6B7280; margin-top: 2px; }
.meta-limit { font-size: 0.69rem; color: #9CA3AF; margin-top: 4px; }
.meta-chip  { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; padding: 3px 9px; border-radius: 20px; white-space: nowrap; }
.chip-pending  { background: #F3F4F6; color: #6B7280; }
.chip-progress { background: #DBEAFE; color: #1D4ED8; }
.chip-wait     { background: #FEF3C7; color: #D97706; }
.chip-valid    { background: #D1FAE5; color: #065F46; }
.meta-actions  { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; }

/* ── DOCS ── */
.doc-item {
    display: flex; align-items: center; gap: 12px; padding: 10px 12px;
    border-radius: 10px; border: 1px solid #F3F4F6; background: #FAFAFA;
    margin-bottom: 6px; transition: border-color 0.2s;
}
.doc-item:hover { border-color: #D97706; }
.doc-icon {
    width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
    background: rgba(217,119,6,0.08); color: #D97706;
    display: flex; align-items: center; justify-content: center; font-size: 0.95rem;
}
.doc-name { font-weight: 600; font-size: 0.82rem; color: #1C1917; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }
.doc-meta { font-size: 0.7rem; color: #9CA3AF; }
.doc-section-label { font-size: 0.67rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #9CA3AF; margin-bottom: 8px; margin-top: 14px; }
.doc-section-label:first-child { margin-top: 0; }

/* ── RESERVAS ── */
.reserva-row {
    display: flex; align-items: center; gap: 12px; padding: 12px 14px;
    border-radius: 12px; border: 1px solid #F3F4F6; background: #FAFAFA;
    margin-bottom: 8px; font-size: 0.84rem;
}
.reserva-row:last-child { margin-bottom: 0; }
.reserva-icon {
    width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 1rem;
}
.r-icon-confirm { background: #D1FAE5; color: #10B981; }
.r-icon-pending { background: #FEF3C7; color: #D97706; }
.r-icon-cancel  { background: #FEE2E2; color: #EF4444; }

/* ── SALAS ── */
.sala-card {
    display: flex; align-items: center; gap: 12px; padding: 12px;
    border-radius: 12px; border: 1px solid #F3F4F6;
    background: #fff; margin-bottom: 8px;
}
.sala-icon {
    width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
    background: rgba(217,119,6,0.08); color: #D97706;
    display: flex; align-items: center; justify-content: center; font-size: 1rem;
}
.sala-name { font-weight: 600; font-size: 0.84rem; color: #1C1917; }
.sala-cap  { font-size: 0.71rem; color: #9CA3AF; }

/* ── FEEDBACK / SESSÃO ── */
.feedback-item {
    padding: 14px 16px; border-radius: 12px;
    background: #F9FAFB; border-left: 4px solid #D97706;
    margin-bottom: 10px;
}
.feedback-item:last-child { margin-bottom: 0; }
.sessao-item {
    padding: 14px 16px; border-radius: 12px;
    border: 1px solid #F3F4F6; background: #FAFAFA;
    margin-bottom: 10px;
}

/* ── MINI STATS ── */
.mini-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
.mini-stat {
    background: #fff; border-radius: 12px; padding: 14px;
    text-align: center; border: 1px solid #F3F4F6;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.mini-stat-val { font-size: 1.5rem; font-weight: 800; color: #D97706; line-height: 1; }
.mini-stat-lbl { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.4px; color: #9CA3AF; margin-top: 4px; font-weight: 600; }

/* ── FLASH ── */
.alert-custom { padding: 13px 18px; border-radius: 12px; font-size: 0.875rem; display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
.alert-success { background: #D1FAE5; color: #065F46; border-left: 4px solid #10B981; }
.alert-danger  { background: #FEE2E2; color: #991B1B; border-left: 4px solid #EF4444; }

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
    .hero-name { font-size: 1.3rem; }
    .hero-banner { padding: 20px 16px; }
    .mini-stats { gap: 8px; }
    .mini-stat { padding: 10px 8px; }
    .mini-stat-val { font-size: 1.2rem; }
    .g-card-body { padding: 14px; }
    .g-card-header { padding: 14px 14px 10px; }
    .meta-item { flex-direction: column; }
    .meta-actions { flex-direction: row; align-items: center; width: 100%; justify-content: flex-start; }
    .doc-name { max-width: 100%; }
}
@media (max-width: 480px) {
    .mini-stats { grid-template-columns: repeat(3, 1fr); }
    .mini-stat-val { font-size: 1rem; }
    .reserva-row { flex-wrap: wrap; }
    .hero-name { font-size: 1.15rem; }
}
</style>

<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-3"><i class="fa fa-check-circle me-2"></i><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-3"><i class="fa fa-triangle-exclamation me-2"></i><?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<?php
$horaAtual = (int)date('H');
if ($horaAtual >= 5 && $horaAtual < 12) $saudacao = 'Bom dia';
elseif ($horaAtual >= 12 && $horaAtual < 18) $saudacao = 'Boa tarde';
else $saudacao = 'Boa noite';
?>

<!-- ══ HERO BANNER ══ -->
<div class="hero-banner">
    <div class="hero-banner-content">
        <div class="hero-badges">
            <span class="hero-badge hero-badge-sp"><i class="fa fa-trophy me-1"></i><?= $ultimoProjeto['pontos'] ?? 0 ?> SP</span>
            <?php if($ultimoProjeto): ?>
            <span class="hero-badge hero-badge-fase"><i class="fa fa-circle-play me-1"></i><?= strtoupper(str_replace('_',' ', $ultimoProjeto['fase'] ?? 'IDEAÇÃO')) ?></span>
            <?php endif; ?>
        </div>
        <div class="hero-name"><?= $saudacao ?>, <?= htmlspecialchars(explode(' ', $nome)[0]) ?>! 👋</div>
        <div class="hero-subtitle">
            <?php if($ultimoProjeto): ?>
                Startup: <strong style="color:#FBBF24"><?= htmlspecialchars($ultimoProjeto['titulo']) ?></strong> · Progresso global: <strong><?= $progresso ?>%</strong>
            <?php else: ?>
                Bem-vindo à Incubadora Académica do ISPSN. Registe a sua ideia para começar.
            <?php endif; ?>
        </div>
        <div class="hero-actions">
            <?php if($ultimoProjeto): ?>
                <button class="hero-btn hero-btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaReservaDashboard">
                    <i class="fa fa-calendar-plus"></i> Reservar Sala
                </button>
                <button class="hero-btn hero-btn-outline" data-bs-toggle="modal" data-bs-target="#modalUploadDocumentoDashboard">
                    <i class="fa fa-upload"></i> Enviar Documento
                </button>
            <?php else: ?>
                <button class="hero-btn hero-btn-primary" data-bs-toggle="modal" data-bs-target="#modalProjeto">
                    <i class="fa fa-plus"></i> Submeter Ideia
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if($ultimoProjeto): ?>

<!-- ══ MINI STATS ══ -->
<div class="mini-stats">
    <div class="mini-stat">
        <div class="mini-stat-val"><?= $progresso ?>%</div>
        <div class="mini-stat-lbl">Progresso</div>
    </div>
    <div class="mini-stat">
        <div class="mini-stat-val"><?= count(array_filter($minhasTarefas, fn($t) => $t['validada_mentor'] == 1)) ?></div>
        <div class="mini-stat-lbl">Metas OK</div>
    </div>
    <div class="mini-stat">
        <div class="mini-stat-val"><?= count($minhasReservasPainel) ?></div>
        <div class="mini-stat-lbl">Reservas</div>
    </div>
</div>

<!-- ══ PIPELINE DE MATURIDADE ══ -->
<div class="g-card mb-4">
    <div class="g-card-header">
        <div class="g-card-title"><i class="fa fa-road text-warning"></i> Pipeline de Maturidade</div>
        <small class="text-muted">Fase atual: <strong><?= strtoupper(str_replace('_',' ', $ultimoProjeto['fase'] ?? 'ideacao')) ?></strong></small>
    </div>
    <div class="g-card-body" style="overflow-x: auto;">
        <?php
        $pipelineSteps = [
            ['id'=>'ideacao',          'label'=>'Ideação',     'icon'=>'lightbulb'],
            ['id'=>'validacao',        'label'=>'Validação',   'icon'=>'vial'],
            ['id'=>'mvp',              'label'=>'MVP',         'icon'=>'cube'],
            ['id'=>'tracao',           'label'=>'Tração',      'icon'=>'chart-line'],
            ['id'=>'mercado',          'label'=>'Mercado',     'icon'=>'shop'],
            ['id'=>'fundo_investimento','label'=>'Financiado', 'icon'=>'sack-dollar'],
        ];
        $currentFase = $ultimoProjeto['fase'] ?? 'ideacao';
        $currentIdx = 0;
        foreach($pipelineSteps as $idx => $step) { if($currentFase === $step['id']) $currentIdx = $idx; }
        $pct = ($currentIdx / (count($pipelineSteps)-1)) * 84 + 8;
        ?>
        <div style="min-width: 380px;">
            <div class="pipeline-wrap">
                <div class="pipeline-track">
                    <div class="pipeline-fill" style="width: <?= $pct ?>%"></div>
                </div>
                <?php foreach($pipelineSteps as $idx => $step):
                    $state = $idx < $currentIdx ? 'done' : ($idx === $currentIdx ? 'active' : '');
                ?>
                    <div class="pipe-step <?= $state ?>">
                        <div class="pipe-icon"><i class="fa fa-<?= $step['icon'] ?>"></i></div>
                        <div class="pipe-label"><?= $step['label'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══ GRID: METAS + DOCUMENTOS ══ -->
<div class="row g-3 mb-4">

    <!-- METAS -->
    <div class="col-lg-7 col-12">
        <div class="g-card h-100">
            <div class="g-card-header">
                <div class="g-card-title"><i class="fa fa-list-check text-warning"></i> Metas & Objetivos</div>
                <span class="meta-chip chip-progress">
                    <?= count(array_filter($minhasTarefas, fn($t)=> $t['validada_mentor']==1)) ?>/<?= count($minhasTarefas) ?> Validadas
                </span>
            </div>
            <div class="g-card-body" style="max-height: 460px; overflow-y: auto;">
                <?php if(empty($minhasTarefas)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa fa-clipboard-check fa-3x mb-3 d-block" style="opacity:0.2"></i>
                        <p class="small mb-0">Nenhuma meta atribuída pelo mentor ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($minhasTarefas as $t):
                        if($t['status']==='concluida' && $t['validada_mentor']==1)     { $cls='is-valid'; $chip='chip-valid';    $ct='Validada'; $ic='check'; }
                        elseif($t['status']==='concluida')                             { $cls='is-wait';  $chip='chip-wait';     $ct='Aguardando'; $ic='hourglass-half'; }
                        elseif($t['status']==='em_progresso')                          { $cls='is-prog';  $chip='chip-progress'; $ct='Em Curso'; $ic=''; }
                        else                                                           { $cls='';         $chip='chip-pending';  $ct='Pendente'; $ic=''; }
                    ?>
                    <div class="meta-item <?= $cls ?>">
                        <div style="display:flex; gap:12px; flex:1; min-width:0;">
                            <div class="meta-dot"><i class="fa fa-<?= $ic ?>"></i></div>
                            <div style="min-width:0;">
                                <div class="meta-title"><?= htmlspecialchars($t['titulo']) ?></div>
                                <?php if($t['descricao']): ?><div class="meta-desc"><?= htmlspecialchars($t['descricao']) ?></div><?php endif; ?>
                                <?php if($t['data_limite']): ?><div class="meta-limit"><i class="fa fa-calendar-alt me-1"></i>Limite: <?= date('d/m/Y', strtotime($t['data_limite'])) ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="meta-actions">
                            <span class="meta-chip <?= $chip ?>"><?= $ct ?></span>
                            <?php if($cls==='' || $cls==='is-prog'): ?>
                                <?php if($cls===''): ?>
                                    <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
                                        <input type="hidden" name="action" value="atualizar_estado_tarefa">
                                        <input type="hidden" name="id_tarefa" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="status" value="em_progresso">
                                        <input type="hidden" name="redirect" value="/incubadora_ispsn/public/index.php">
                                        <button type="submit" class="btn-ghost" style="padding:4px 10px; font-size:0.72rem;">Iniciar</button>
                                    </form>
                                <?php endif; ?>
                                <button class="btn-primary-custom" style="padding:5px 10px; font-size:0.72rem;" data-bs-toggle="modal" data-bs-target="#modalEvid_<?= $t['id'] ?>">
                                    <i class="fa fa-upload"></i> Evidência
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- MODAL EVIDÊNCIA -->
                    <div class="modal fade" id="modalEvid_<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content modal-content-custom">
                                <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="atualizar_estado_tarefa">
                                    <input type="hidden" name="id_tarefa" value="<?= $t['id'] ?>">
                                    <input type="hidden" name="status" value="concluida">
                                    <input type="hidden" name="redirect" value="/incubadora_ispsn/public/index.php">
                                    <div class="modal-header-custom">
                                        <h5 class="modal-title fw-bold"><i class="fa fa-envelope-open-text me-2"></i> Evidência de Meta</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body-custom">
                                        <div class="mb-3 p-3 rounded" style="background:#FEF3C7; border-left:4px solid #D97706;">
                                            <strong>Meta:</strong> <?= htmlspecialchars($t['titulo']) ?>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label-custom">Notas de Conclusão *</label>
                                            <textarea name="evidencia_nota" class="form-control-custom" rows="3" required placeholder="Descreva como cumpriu esta meta..."></textarea>
                                        </div>
                                        <div>
                                            <label class="form-label-custom">Ficheiro Comprovativo (PDF/Imagem) *</label>
                                            <input type="file" name="evidencia_ficheiro" class="form-control-custom" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer-custom">
                                        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn-primary-custom"><i class="fa fa-upload"></i> Entregar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- DOCUMENTOS -->
    <div class="col-lg-5 col-12">
        <div class="g-card h-100">
            <div class="g-card-header">
                <div class="g-card-title"><i class="fa fa-folder-open text-warning"></i> Documentos</div>
                <button class="btn-ghost" style="padding:5px 10px; font-size:0.75rem;" data-bs-toggle="modal" data-bs-target="#modalUploadDocumentoDashboard">
                    <i class="fa fa-plus"></i> Carregar
                </button>
            </div>
            <div class="g-card-body" style="max-height: 460px; overflow-y: auto;">
                <?php if(empty($meusDocumentos)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa fa-file-arrow-up fa-3x d-block mb-3" style="opacity:0.2"></i>
                        <p class="small mb-0">Nenhum documento partilhado ainda.</p>
                    </div>
                <?php else: ?>
                    <?php $recebidos = array_filter($meusDocumentos, fn($d)=>$d['perfil_submeteu']!=='utilizador'); ?>
                    <?php $enviados  = array_filter($meusDocumentos, fn($d)=>$d['perfil_submeteu']==='utilizador'); ?>
                    
                    <div class="doc-section-label">Recebidos da Incubadora / Mentor</div>
                    <?php if(empty($recebidos)): ?>
                        <div class="text-muted small text-center py-2">Nenhum documento recebido.</div>
                    <?php else: ?>
                        <?php foreach($recebidos as $d): ?>
                        <div class="doc-item">
                            <div class="doc-icon"><i class="fa fa-file-arrow-down"></i></div>
                            <div style="flex:1; min-width:0;">
                                <div class="doc-name" title="<?= htmlspecialchars($d['titulo']) ?>"><?= htmlspecialchars($d['titulo']) ?></div>
                                <div class="doc-meta">Por <?= htmlspecialchars($d['quem_submeteu']) ?> · <?= date('d/m/Y', strtotime($d['criado_em'])) ?></div>
                            </div>
                            <a href="/incubadora_ispsn/<?= $d['path'] ?>" target="_blank" class="btn-ghost p-2" style="padding:6px 10px !important;"><i class="fa fa-download"></i></a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div class="doc-section-label">Submetidos pela Equipa</div>
                    <?php if(empty($enviados)): ?>
                        <div class="text-muted small text-center py-2">Nenhum documento submetido.</div>
                    <?php else: ?>
                        <?php foreach($enviados as $d): ?>
                        <div class="doc-item">
                            <div class="doc-icon"><i class="fa fa-file-arrow-up"></i></div>
                            <div style="flex:1; min-width:0;">
                                <div class="doc-name" title="<?= htmlspecialchars($d['titulo']) ?>"><?= htmlspecialchars($d['titulo']) ?></div>
                                <div class="doc-meta"><?= $d['tipo'] ?> · <?= date('d/m/Y', strtotime($d['criado_em'])) ?></div>
                            </div>
                            <a href="/incubadora_ispsn/<?= $d['path'] ?>" target="_blank" class="btn-ghost p-2" style="padding:6px 10px !important;"><i class="fa fa-download"></i></a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══ GRID: RESERVAS + SALAS ══ -->
<div class="row g-3 mb-4">

    <!-- RESERVAS ATIVAS -->
    <div class="col-lg-8 col-12">
        <div class="g-card">
            <div class="g-card-header">
                <div class="g-card-title"><i class="fa fa-bookmark text-warning"></i> Minhas Reservas de Espaço</div>
                <button class="btn-primary-custom" style="padding:6px 12px; font-size:0.78rem;" data-bs-toggle="modal" data-bs-target="#modalNovaReservaDashboard">
                    <i class="fa fa-calendar-plus"></i> Nova
                </button>
            </div>
            <div class="g-card-body">
                <?php if(empty($minhasReservasPainel)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa fa-calendar-xmark fa-2x d-block mb-2" style="opacity:0.25"></i>
                        <p class="small mb-0">Sem reservas activas. Use o botão acima para solicitar.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($minhasReservasPainel as $r):
                        $ic = $r['status']==='confirmada' ? 'r-icon-confirm' : ($r['status']==='pendente' ? 'r-icon-pending' : 'r-icon-cancel');
                        $statusEmoji = $r['status']==='confirmada' ? '✅' : ($r['status']==='pendente' ? '⏳' : '❌');
                    ?>
                    <div class="reserva-row">
                        <div class="reserva-icon <?= $ic ?>"><i class="fa fa-building-user"></i></div>
                        <div style="flex:1; min-width:0;">
                            <div class="fw-bold" style="font-size:0.85rem;"><?= htmlspecialchars($r['espaco_nome']) ?></div>
                            <div style="font-size:0.72rem; color:#9CA3AF;">
                                <?= ucfirst($r['espaco_tipo']) ?> · <?= date('d/m/Y', strtotime($r['data_reserva'])) ?> · <?= substr($r['hora_inicio'],0,5) ?>–<?= substr($r['hora_fim'],0,5) ?>
                            </div>
                        </div>
                        <span style="font-size:0.75rem; font-weight:700; color:#6B7280;"><?= $statusEmoji ?> <?= ucfirst($r['status']) ?></span>
                        <?php if($r['status']==='pendente'): ?>
                        <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="gestao_reserva">
                            <input type="hidden" name="id_reserva" value="<?= $r['id'] ?>">
                            <input type="hidden" name="novo_status" value="cancelada">
                            <input type="hidden" name="redirect" value="/incubadora_ispsn/public/index.php">
                            <button type="submit" class="btn-ghost" style="padding:4px 8px; font-size:0.7rem; color:#EF4444;" onclick="return confirm('Cancelar reserva?')">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SALAS DISPONÍVEIS -->
    <div class="col-lg-4 col-12">
        <div class="g-card">
            <div class="g-card-header">
                <div class="g-card-title"><i class="fa fa-door-open text-warning"></i> Salas Livres</div>
            </div>
            <div class="g-card-body">
                <?php if(empty($espacosDisponiveis)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa fa-building-circle-xmark d-block mb-2 fa-2x" style="opacity:0.2"></i>
                        <p class="small mb-0">Nenhum espaço disponível de momento.</p>
                    </div>
                <?php else: ?>
                    <?php foreach(array_slice($espacosDisponiveis, 0, 4) as $e):
                        $icon = $e['tipo']==='mesa' ? 'desktop' : ($e['tipo']==='sala_reuniao' ? 'users' : 'lightbulb');
                    ?>
                    <div class="sala-card">
                        <div class="sala-icon"><i class="fa fa-<?= $icon ?>"></i></div>
                        <div style="flex:1; min-width:0;">
                            <div class="sala-name"><?= htmlspecialchars($e['nome']) ?></div>
                            <div class="sala-cap"><?= ucfirst($e['tipo']) ?> · <?= $e['capacidade'] ?> pessoa<?= $e['capacidade']>1?'s':'' ?></div>
                        </div>
                        <span style="background:#D1FAE5; color:#065F46; font-size:0.62rem; font-weight:700; padding:3px 8px; border-radius:20px; text-transform:uppercase;">Livre</span>
                    </div>
                    <?php endforeach; ?>
                    <div class="p-3 mt-2 rounded" style="background:#FEF3C7; border-left:3px solid #D97706; font-size:0.75rem; color:#92400E;">
                        <i class="fa fa-circle-info me-1"></i>Reserve com antecedência. Máx. 4h por reserva.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══ GRID: FEEDBACK + SESSÕES ══ -->
<div class="row g-3 mb-4">

    <!-- FEEDBACK ORIENTADOR -->
    <div class="col-lg-6 col-12">
        <div class="g-card">
            <div class="g-card-header">
                <div class="g-card-title"><i class="fa fa-comment-dots text-warning"></i> Feedback do Orientador</div>
            </div>
            <div class="g-card-body" style="max-height: 360px; overflow-y: auto;">
                <?php if(empty($comentarios)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa fa-comments-slash fa-3x d-block mb-3" style="opacity:0.2"></i>
                        <p class="small mb-0">Sem feedbacks do orientador ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($comentarios as $c): ?>
                    <div class="feedback-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong style="font-size:0.84rem;"><?= htmlspecialchars($c['nome']) ?></strong>
                            <small class="text-muted" style="font-size:0.7rem;"><?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?></small>
                        </div>
                        <span class="badge-estado badge-<?= str_replace(' ','_',$c['fase']) ?>" style="font-size:0.6rem; margin-bottom:6px; display:inline-block;"><?= strtoupper(str_replace('_',' ',$c['fase'])) ?></span>
                        <p class="small mb-0" style="color:#374151;"><?= nl2br(htmlspecialchars($c['comentario'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SESSÕES MENTORIA -->
    <div class="col-lg-6 col-12">
        <div class="g-card">
            <div class="g-card-header">
                <div class="g-card-title"><i class="fa fa-handshake text-warning"></i> Sessões de Mentoria</div>
            </div>
            <div class="g-card-body" style="max-height: 360px; overflow-y: auto;">
                <?php if(empty($sessoesMentoria)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa fa-calendar-xmark fa-3x d-block mb-3" style="opacity:0.2"></i>
                        <p class="small mb-0">Nenhuma sessão de mentoria registada.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($sessoesMentoria as $s): ?>
                    <div class="sessao-item">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong style="font-size:0.84rem;"><i class="fa fa-user-tie me-1 text-muted"></i><?= htmlspecialchars($s['mentor_nome']) ?></strong>
                            <small class="text-muted" style="font-size:0.7rem;"><?= date('d/m/Y', strtotime($s['data_sessao'])) ?></small>
                        </div>
                        <div class="mb-1">
                            <span class="badge bg-light text-dark" style="font-size:0.7rem;"><?= $s['duracao_min'] ?> min</span>
                            <?php if($s['avaliacao_equipa']): ?>
                            <span style="color:#F59E0B; font-size:0.75rem; margin-left:4px;">
                                <?php for($i=0; $i<$s['avaliacao_equipa']; $i++) echo '★'; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <p class="small mb-0 text-muted"><?= nl2br(htmlspecialchars($s['topicos'] ?? '')) ?></p>
                        <?php if($s['proximos_passos']): ?>
                        <div class="mt-2 pt-2" style="border-top:1px solid #F3F4F6; font-size:0.75rem; color:var(--amber);">
                            <i class="fa fa-arrow-right me-1"></i><?= htmlspecialchars($s['proximos_passos']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ══ SEM PROJETO AINDA ══ -->
<div class="g-card p-5 text-center" style="max-width: 600px; margin: 40px auto;">
    <div class="mb-4" style="font-size:4rem; color:var(--amber); opacity:0.8;"><i class="fa fa-diagram-project"></i></div>
    <h3 class="fw-800 mb-2" style="color:#1C1917;">Ainda não tem ideias submetidas</h3>
    <p class="text-muted small mb-4" style="max-width:400px; margin:0 auto;">O ecossistema da Incubadora do ISPSN apoia-o a validar e estruturar a sua ideia. Comece agora!</p>
    <button class="hero-btn hero-btn-primary" style="margin:0 auto;" data-bs-toggle="modal" data-bs-target="#modalProjeto">
        <i class="fa fa-plus"></i> Submeter Primeiro Projeto
    </button>
</div>
<?php endif; ?>

<!-- ══ MODAL RESERVA ══ -->
<div class="modal fade" id="modalNovaReservaDashboard" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/reserva_action.php" method="POST">
                <input type="hidden" name="action" value="solicitar_reserva">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/public/index.php">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-bookmark me-2"></i> Reservar Espaço</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Espaço *</label>
                        <select name="id_espaco" class="form-control-custom" required>
                            <option value="">Selecione...</option>
                            <?php foreach($espacosDisponiveis as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nome']) ?> (<?= ucfirst($e['tipo']) ?>) — <?= $e['capacidade'] ?>p</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Data *</label>
                        <input type="date" name="data_reserva" class="form-control-custom" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-custom">Hora Início *</label>
                            <input type="time" name="hora_inicio" class="form-control-custom" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-custom">Hora Fim *</label>
                            <input type="time" name="hora_fim" class="form-control-custom" required>
                        </div>
                    </div>
                    <div>
                        <label class="form-label-custom">Objetivo</label>
                        <textarea name="objetivo" class="form-control-custom" rows="2" placeholder="Ex: Reunião de equipa"></textarea>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Solicitar Reserva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ MODAL UPLOAD DOCUMENTO ══ -->
<div class="modal fade" id="modalUploadDocumentoDashboard" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_documento">
                <input type="hidden" name="id_projeto" value="<?= $ultimoProjeto['id'] ?? '' ?>">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/public/index.php">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-upload me-2"></i> Carregar Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Título *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Pitch Deck 2026">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Tipo</label>
                        <select name="tipo" class="form-control-custom">
                            <option value="Pitch">Pitch / Apresentação</option>
                            <option value="Plano de Negócio">Plano de Negócio</option>
                            <option value="Contrato">Contrato / Legal</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-custom">Ficheiro (PDF/PPTX/Imagem) *</label>
                        <input type="file" name="ficheiro" class="form-control-custom" required>
                    </div>
                </div>
    <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-upload"></i> Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ MODAL SUBMETER IDEIA ══ -->
<div class="modal fade" id="modalProjeto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
                <input type="hidden" name="action" value="criar_projeto">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/public/index.php">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-rocket me-2" style="color:var(--amber)"></i> Submeter Ideia de Startup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Nome da Startup *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: App de Transporte Local">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Tipo *</label>
                            <select name="tipo" class="form-control-custom" required>
                                <option value="startup_tecnologica">Startup Tecnológica (App, SaaS)</option>
                                <option value="negocio_tradicional">Negócio Tradicional</option>
                                <option value="impacto_social">Impacto Social / ONG</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Área Temática</label>
                            <select name="area" class="form-control-custom">
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
                        <label class="form-label-custom">Descrição *</label>
                        <textarea name="descricao" class="form-control-custom" rows="3" required placeholder="Descreva o projecto (mín. 20 caracteres)"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Problema Identificado</label>
                        <textarea name="problema" class="form-control-custom" rows="2" placeholder="Qual problema resolve?"></textarea>
                    </div>
                    <div>
                        <label class="form-label-custom">Solução Proposta</label>
                        <textarea name="solucao" class="form-control-custom" rows="2" placeholder="Como resolve o problema?"></textarea>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-paper-plane"></i> Submeter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = '<script>
const el=document.getElementById("graficoProgresso");
if(el){
  const primaryColor = getComputedStyle(document.documentElement).getPropertyValue("--primary").trim() || "#D97706";
  new Chart(el,{
    type:"doughnut",
    data:{datasets:[{data:[<?= $progresso ?>,<?= 100-$progresso ?>],backgroundColor:[primaryColor,"#F3F4F6"],borderWidth:0}]},
    options:{cutout:"75%",plugins:{legend:{display:false},tooltip:{enabled:false}}}
  });
}
<\/script>';
require_once __DIR__ . '/../partials/_layout_end.php';
?>
