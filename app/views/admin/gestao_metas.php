<?php
// app/views/admin/gestao_metas.php
// Painel de Gestão de Metas — SuperAdmin
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['superadmin','admin']);

$tituloPagina = 'Gestão de Metas';
$paginaActiva = 'gestao_metas';

// Flash messages
$flashOk   = $_SESSION['flash_ok'] ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

// Filtro: projecto seleccionado
$idProjetoSel = (int)($_GET['projeto'] ?? 0);
$faseSel      = $_GET['fase'] ?? '';

// Buscar projectos incubados
$projectosIncubados = [];
$res = $mysqli->query("
    SELECT p.id, p.titulo, p.fase, p.estado, p.pontos, u.nome as autor
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    WHERE p.estado IN ('incubado','aprovado','fundo_investimento')
    ORDER BY p.titulo
");
if ($res) while ($r = $res->fetch_assoc()) $projectosIncubados[] = $r;

// Se nenhum seleccionado, usar o primeiro
if (!$idProjetoSel && !empty($projectosIncubados)) {
    $idProjetoSel = (int)$projectosIncubados[0]['id'];
}

// Buscar fase actual do projecto seleccionado
$faseActual = 'ideacao';
$projetoInfo = null;
if ($idProjetoSel) {
    $stmt = $mysqli->prepare("SELECT p.*, u.nome as autor FROM projetos p JOIN usuarios u ON u.id = p.criado_por WHERE p.id = ?");
    $stmt->bind_param('i', $idProjetoSel);
    $stmt->execute();
    $projetoInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($projetoInfo) $faseActual = $projetoInfo['fase'] ?? 'ideacao';
}

if (!$faseSel) $faseSel = $faseActual;

// Buscar metas do projecto para a fase seleccionada
$metasProjeto = [];
if ($idProjetoSel) {
    $stmt = $mysqli->prepare("
        SELECT mp.*, mpd.titulo as meta_titulo, mpd.descricao as meta_descricao,
               mpd.evidencia_tipo, mpd.evidencia_desc, mpd.peso_percentual, mpd.prazo_dias,
               mpd.fase, mpd.numero,
               ua.nome as activador_nome, uv.nome as validador_nome
        FROM metas_padrao mpd
        LEFT JOIN metas_projeto mp ON mp.id_meta_padrao = mpd.id AND mp.id_projeto = ?
        LEFT JOIN usuarios ua ON ua.id = mp.activada_por
        LEFT JOIN usuarios uv ON uv.id = mp.validada_por
        WHERE mpd.fase = ? AND mpd.activo = 1
        ORDER BY mpd.numero
    ");
    $stmt->bind_param('is', $idProjetoSel, $faseSel);
    $stmt->execute();
    $metasProjeto = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Calcular progresso
$progressoTotal = 0;
$pesoConcluido = 0;
foreach ($metasProjeto as $m) {
    $progressoTotal += $m['peso_percentual'];
    if (($m['estado'] ?? '') === 'concluida') {
        $pesoConcluido += $m['peso_percentual'];
    }
}
$percentConcluido = $progressoTotal > 0 ? round(($pesoConcluido / $progressoTotal) * 100) : 0;

// Contadores por estado
$contEstados = ['inactiva'=>0, 'activa'=>0, 'em_avaliacao'=>0, 'concluida'=>0, 'reprovada'=>0, 'nao_inicializada'=>0];
foreach ($metasProjeto as $m) {
    $est = $m['estado'] ?? 'nao_inicializada';
    if (!$est) $est = 'nao_inicializada';
    $contEstados[$est] = ($contEstados[$est] ?? 0) + 1;
}

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- FLASH -->
<?php if ($flashOk): ?><div class="alert alert-success border-0 shadow-sm mb-4"><i class="fa fa-check-circle me-2"></i><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fa fa-triangle-exclamation me-2"></i><?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<style>
.meta-card {
    background: #fff; border: 1px solid #E2E8F0; border-radius: 16px; padding: 20px;
    margin-bottom: 12px; transition: all 0.3s; position: relative; overflow: hidden;
}
.meta-card:hover { border-color: #D97706; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
.meta-card.estado-concluida { border-left: 4px solid #10B981; }
.meta-card.estado-activa { border-left: 4px solid #D97706; background: #FFFBEB; }
.meta-card.estado-em_avaliacao { border-left: 4px solid #3B82F6; background: #EFF6FF; }
.meta-card.estado-reprovada { border-left: 4px solid #EF4444; background: #FEF2F2; }
.meta-card.estado-inactiva { border-left: 4px solid #CBD5E1; opacity: 0.75; }
.meta-card.estado-nao_inicializada { border-left: 4px solid #E2E8F0; opacity: 0.6; }
.meta-numero { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; flex-shrink: 0; }
.meta-peso { background: #F1F5F9; padding: 4px 10px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; color: #475569; }
.meta-estado-badge { padding: 4px 12px; border-radius: 8px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
.fase-tab { padding: 8px 16px; border-radius: 10px; font-size: 0.8rem; font-weight: 700; text-decoration: none; color: #64748B; transition: 0.2s; }
.fase-tab.active { background: #D97706; color: #fff; }
.fase-tab:hover:not(.active) { background: #F1F5F9; color: #1E293B; }
.proj-selector { background: #fff; border: 1px solid #E2E8F0; border-radius: 12px; padding: 12px 16px; cursor: pointer; transition: 0.2s; }
.proj-selector:hover { border-color: #D97706; }
.proj-selector.active { border-color: #D97706; background: #FFFBEB; box-shadow: inset 0 0 0 2px #D97706; }
.progress-ring { width: 100%; height: 12px; background: #F1F5F9; border-radius: 8px; overflow: hidden; }
.progress-ring-fill { height: 100%; border-radius: 8px; background: linear-gradient(90deg, #10B981, #D97706); transition: width 0.8s ease; }
.evidencia-box { background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 12px; padding: 16px; margin-top: 12px; }
</style>

<!-- HEADER -->
<div class="page-header mt-0" style="padding-top:0; margin-bottom:20px;">
    <div>
        <div class="page-header-title" style="font-size:1.4rem;"><i class="fa fa-bullseye me-2" style="color:#D97706"></i>Gestão de Metas</div>
        <div class="page-header-sub">Activação progressiva de metas para startups incubadas</div>
    </div>
</div>

<?php if (empty($projectosIncubados)): ?>
<div class="text-center p-5 bg-white rounded-4 border">
    <i class="fa fa-inbox fa-3x text-muted opacity-25 mb-3"></i>
    <h6 class="fw-bold">Sem projectos incubados</h6>
    <p class="text-muted small">Ainda não existem projectos em estado de incubação para gerir metas.</p>
</div>
<?php else: ?>

<!-- SELECTOR DE PROJECTO -->
<div class="mb-4">
    <div style="font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.1em; color:#94A3B8; margin-bottom:10px;"><i class="fa fa-rocket me-1"></i> Seleccionar Startup</div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php foreach ($projectosIncubados as $p): ?>
        <a href="?projeto=<?= $p['id'] ?>" class="proj-selector <?= $p['id']==$idProjetoSel?'active':'' ?>" style="text-decoration:none; min-width:200px;">
            <div style="font-weight:700; font-size:0.9rem; color:#1E293B;"><?= htmlspecialchars($p['titulo']) ?></div>
            <div style="font-size:0.72rem; color:#94A3B8;"><?= htmlspecialchars($p['autor']) ?> · Fase: <?= strtoupper($p['fase'] ?? 'ideacao') ?></div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($projetoInfo): ?>

<!-- PROGRESSO GERAL -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-1"><?= htmlspecialchars($projetoInfo['titulo']) ?></h5>
                <span class="text-muted" style="font-size:0.8rem;">Fase actual: <strong style="color:#D97706"><?= strtoupper($faseActual) ?></strong> · <?= $projetoInfo['pontos'] ?? 0 ?> SP</span>
            </div>
            <div style="text-align:right;">
                <div style="font-size:2rem; font-weight:900; color:<?= $percentConcluido >= 100 ? '#10B981' : '#D97706' ?>;"><?= $percentConcluido ?>%</div>
                <div style="font-size:0.7rem; color:#94A3B8; font-weight:600;">PROGRESSO DA FASE</div>
            </div>
        </div>
        <div class="progress-ring"><div class="progress-ring-fill" style="width:<?= $percentConcluido ?>%"></div></div>
        <div class="d-flex gap-3 mt-3 flex-wrap">
            <span class="meta-peso" style="background:#DCFCE7; color:#166534;"><i class="fa fa-check me-1"></i><?= $contEstados['concluida'] ?> Concluídas</span>
            <span class="meta-peso" style="background:#FEF3C7; color:#92400E;"><i class="fa fa-bolt me-1"></i><?= $contEstados['activa'] ?> Activas</span>
            <span class="meta-peso" style="background:#DBEAFE; color:#1E40AF;"><i class="fa fa-clock me-1"></i><?= $contEstados['em_avaliacao'] ?> Em Avaliação</span>
            <span class="meta-peso" style="background:#FEE2E2; color:#991B1B;"><i class="fa fa-rotate-left me-1"></i><?= $contEstados['reprovada'] ?> Devolvidas</span>
            <span class="meta-peso"><i class="fa fa-lock me-1"></i><?= $contEstados['inactiva'] + $contEstados['nao_inicializada'] ?> Bloqueadas</span>
        </div>
    </div>
</div>

<!-- TABS DE FASES -->
<div class="d-flex gap-6 mb-4" style="gap:6px; background:#F1F5F9; padding:5px; border-radius:14px; width:fit-content; border:1px solid #E2E8F0;">
    <?php foreach (['ideacao'=>'Ideação','validacao'=>'Validação','mvp'=>'MVP','tracao'=>'Tracção','mercado'=>'Mercado'] as $fKey=>$fLabel): ?>
    <a href="?projeto=<?= $idProjetoSel ?>&fase=<?= $fKey ?>" class="fase-tab <?= $faseSel===$fKey?'active':'' ?>"><?= $fLabel ?></a>
    <?php endforeach; ?>
</div>

<!-- BOTÃO ACTIVAR TODAS + INICIALIZAR -->
<div class="d-flex gap-2 mb-4">
    <?php if ($contEstados['nao_inicializada'] > 0): ?>
    <form method="post" action="/incubadora_ispsn/app/controllers/metas_action.php">
        <input type="hidden" name="action" value="inicializar_metas">
        <input type="hidden" name="id_projeto" value="<?= $idProjetoSel ?>">
        <input type="hidden" name="fase" value="<?= $faseSel ?>">
        <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
        <button type="submit" class="btn btn-dark fw-bold px-4 py-2" style="border-radius:10px; font-size:0.85rem;">
            <i class="fa fa-download me-2"></i>Inicializar Metas desta Fase
        </button>
    </form>
    <?php endif; ?>
    <?php if ($contEstados['inactiva'] > 0 && $_SESSION['usuario_perfil'] === 'superadmin'): ?>
    <form method="post" action="/incubadora_ispsn/app/controllers/metas_action.php" onsubmit="return confirm('Activar TODAS as metas inactivas desta fase?')">
        <input type="hidden" name="action" value="activar_todas_fase">
        <input type="hidden" name="id_projeto" value="<?= $idProjetoSel ?>">
        <input type="hidden" name="fase" value="<?= $faseSel ?>">
        <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
        <button type="submit" class="btn btn-warning fw-bold px-4 py-2" style="border-radius:10px; font-size:0.85rem;">
            <i class="fa fa-bolt me-2"></i>Activar Todas (<?= $contEstados['inactiva'] ?>)
        </button>
    </form>
    <?php endif; ?>
</div>

<!-- LISTA DE METAS -->
<?php foreach ($metasProjeto as $m):
    $estado = $m['estado'] ?? 'nao_inicializada';
    if (!$estado) $estado = 'nao_inicializada';
    $cores = [
        'concluida'=>['bg'=>'#DCFCE7','color'=>'#166534','icon'=>'fa-check-circle','label'=>'Concluída'],
        'activa'=>['bg'=>'#FEF3C7','color'=>'#92400E','icon'=>'fa-bolt','label'=>'Activa'],
        'em_avaliacao'=>['bg'=>'#DBEAFE','color'=>'#1E40AF','icon'=>'fa-clock','label'=>'Em Avaliação'],
        'reprovada'=>['bg'=>'#FEE2E2','color'=>'#991B1B','icon'=>'fa-rotate-left','label'=>'Devolvida'],
        'inactiva'=>['bg'=>'#F1F5F9','color'=>'#64748B','icon'=>'fa-lock','label'=>'Bloqueada'],
        'nao_inicializada'=>['bg'=>'#F8FAFC','color'=>'#94A3B8','icon'=>'fa-circle-question','label'=>'Não Inicializada'],
    ];
    $cor = $cores[$estado] ?? $cores['nao_inicializada'];
    $numCor = $estado === 'concluida' ? '#10B981' : ($estado === 'activa' ? '#D97706' : '#CBD5E1');
?>
<div class="meta-card estado-<?= $estado ?>">
    <div class="d-flex align-items-start gap-3">
        <div class="meta-numero" style="background:<?= $numCor ?>20; color:<?= $numCor ?>;"><?= $m['numero'] ?></div>
        <div style="flex:1;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h6 class="fw-bold mb-0" style="font-size:0.95rem;"><?= htmlspecialchars($m['meta_titulo']) ?></h6>
                <div class="d-flex gap-2 align-items-center">
                    <span class="meta-peso"><?= $m['peso_percentual'] ?>%</span>
                    <span class="meta-estado-badge" style="background:<?= $cor['bg'] ?>; color:<?= $cor['color'] ?>;"><i class="fa <?= $cor['icon'] ?> me-1"></i><?= $cor['label'] ?></span>
                </div>
            </div>
            <p class="text-muted mb-2" style="font-size:0.8rem; line-height:1.5;"><?= htmlspecialchars($m['meta_descricao']) ?></p>
            <div class="d-flex gap-3 flex-wrap" style="font-size:0.72rem; color:#94A3B8;">
                <span><i class="fa fa-paperclip me-1"></i>Evidência: <?= htmlspecialchars($m['evidencia_desc']) ?></span>
                <span><i class="fa fa-calendar me-1"></i>Prazo: <?= $m['prazo_dias'] ?> dias</span>
                <?php if ($m['data_limite']): ?>
                <span style="color:<?= strtotime($m['data_limite']) < time() ? '#EF4444' : '#10B981' ?>; font-weight:700;">
                    <i class="fa fa-clock me-1"></i>Limite: <?= date('d/m/Y', strtotime($m['data_limite'])) ?>
                </span>
                <?php endif; ?>
            </div>
            
            <?php // Mostrar evidência submetida
            if ($estado === 'em_avaliacao' && $m['evidencia_em']): ?>
            <div class="evidencia-box">
                <div style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#64748B; margin-bottom:8px;"><i class="fa fa-paperclip me-1"></i>Evidência Submetida em <?= date('d/m/Y H:i', strtotime($m['evidencia_em'])) ?></div>
                <?php if ($m['evidencia_texto']): ?><p class="mb-1" style="font-size:0.85rem;"><?= nl2br(htmlspecialchars($m['evidencia_texto'])) ?></p><?php endif; ?>
                <?php if ($m['evidencia_link']): ?><a href="<?= htmlspecialchars($m['evidencia_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1"><i class="fa fa-link me-1"></i>Ver Link</a><?php endif; ?>
                <?php if ($m['evidencia_path']): ?><a href="/incubadora_ispsn/<?= htmlspecialchars($m['evidencia_path']) ?>" target="_blank" class="btn btn-sm btn-outline-dark mt-1"><i class="fa fa-download me-1"></i>Download Ficheiro</a><?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($m['feedback_mentor']): ?>
            <div class="mt-2 p-2 rounded" style="background:#F0FDF4; font-size:0.8rem;">
                <strong>Feedback do Mentor:</strong> <?= htmlspecialchars($m['feedback_mentor']) ?>
                <?php if ($m['nota_mentor']): ?> · Nota: <?= $m['nota_mentor'] ?>/5<?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- ACÇÕES -->
        <div class="d-flex flex-column gap-2" style="min-width:140px;">
            <?php if ($estado === 'inactiva' && $_SESSION['usuario_perfil'] === 'superadmin'): ?>
            <form method="post" action="/incubadora_ispsn/app/controllers/metas_action.php">
                <input type="hidden" name="action" value="activar_meta">
                <input type="hidden" name="id_meta_projeto" value="<?= $m['id'] ?>">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                <button type="submit" class="btn btn-warning btn-sm fw-bold w-100" style="border-radius:8px;"><i class="fa fa-bolt me-1"></i>Activar</button>
            </form>
            <?php elseif ($estado === 'em_avaliacao' && in_array($_SESSION['usuario_perfil'], ['mentor','admin','superadmin'])): ?>
            <button class="btn btn-success btn-sm fw-bold w-100" style="border-radius:8px;" onclick="validarMeta(<?= $m['id'] ?>, 'aprovar')"><i class="fa fa-check me-1"></i>Validar</button>
            <button class="btn btn-outline-danger btn-sm fw-bold w-100" style="border-radius:8px;" onclick="validarMeta(<?= $m['id'] ?>, 'reprovar')"><i class="fa fa-rotate-left me-1"></i>Devolver</button>
            <?php elseif ($estado === 'activa' && $_SESSION['usuario_perfil'] === 'superadmin'): ?>
            <form method="post" action="/incubadora_ispsn/app/controllers/metas_action.php" onsubmit="return confirm('Desactivar esta meta?')">
                <input type="hidden" name="action" value="desactivar_meta">
                <input type="hidden" name="id_meta_projeto" value="<?= $m['id'] ?>">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                <button type="submit" class="btn btn-outline-secondary btn-sm fw-bold w-100" style="border-radius:8px;"><i class="fa fa-pause me-1"></i>Desactivar</button>
            </form>
            <?php elseif ($estado === 'concluida'): ?>
            <span class="text-success text-center" style="font-size:0.75rem; font-weight:700;"><i class="fa fa-trophy me-1"></i>+<?= $m['pontos_ganhos'] ?> SP</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; // projetoInfo ?>
<?php endif; // projectosIncubados ?>

<!-- Modal de Validação -->
<div class="modal fade" id="modalValidar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form method="post" action="/incubadora_ispsn/app/controllers/metas_action.php">
                <input type="hidden" name="action" value="validar_evidencia">
                <input type="hidden" name="id_meta_projeto" id="validarMetaId">
                <input type="hidden" name="decisao" id="validarDecisao">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                <div class="modal-header border-0"><h5 class="modal-title fw-bold" id="validarTitulo">Validar Evidência</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Nota (1-5)</label>
                        <select name="nota_mentor" class="form-control rounded-3">
                            <option value="1">1 — Insuficiente</option>
                            <option value="2">2 — Fraco</option>
                            <option value="3" selected>3 — Suficiente</option>
                            <option value="4">4 — Bom</option>
                            <option value="5">5 — Excelente</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Feedback</label>
                        <textarea name="feedback_mentor" class="form-control rounded-3" rows="3" required placeholder="Escreva o seu feedback sobre a evidência..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold rounded-3 px-4" id="validarBtn">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function validarMeta(id, decisao) {
    document.getElementById('validarMetaId').value = id;
    document.getElementById('validarDecisao').value = decisao;
    document.getElementById('validarTitulo').textContent = decisao === 'aprovar' ? '✅ Validar Evidência' : '🔄 Devolver Evidência';
    document.getElementById('validarBtn').textContent = decisao === 'aprovar' ? 'Aprovar' : 'Devolver com Feedback';
    document.getElementById('validarBtn').className = decisao === 'aprovar' ? 'btn btn-success fw-bold rounded-3 px-4' : 'btn btn-danger fw-bold rounded-3 px-4';
    new bootstrap.Modal(document.getElementById('modalValidar')).show();
}
</script>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
