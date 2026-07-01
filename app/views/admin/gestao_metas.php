<?php
// app/views/admin/gestao_metas.php
// Painel de Gestão de Metas — SuperAdmin
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['superadmin','admin']);

$tituloPagina = 'Gestão de Metas';
$paginaActiva = 'gestao_metas';

// Auto-seed para a fase tracao se não houver nenhuma meta padrão para tracao
$resCountTracao = $mysqli->query("SELECT COUNT(*) n FROM metas_padrao WHERE fase = 'tracao'");
$countTracao = $resCountTracao ? (int)$resCountTracao->fetch_assoc()['n'] : 0;
if ($countTracao === 0) {
    $metasTracaoDefault = [
        [1, 'Crescimento de Utilizadores', 'Alcançar um crescimento mensal consistente de utilizadores ativos ou downloads de pelo menos 15%.', 'ficheiro', 'Relatório ou gráfico de analytics comprovando o crescimento', 20.00, 15],
        [2, 'Aquisição Escalonável (Growth)', 'Identificar e provar um canal de aquisição escalonável com cálculo do CAC (Custo de Aquisição de Cliente) e LTV.', 'ficheiro', 'Relatório de métricas de aquisição, CAC e LTV', 20.00, 15],
        [3, 'Parcerias Comerciais', 'Assinar ou operacionalizar no mínimo duas parcerias estratégicas ou contratos comerciais.', 'ficheiro', 'Contratos ou acordos assinados digitalizados', 20.00, 20],
        [4, 'Otimização do Funil (AARRR)', 'Mapear e documentar a jornada e taxas de conversão dos utilizadores (Aquisição, Ativação, Retenção, Receita, Recomendação).', 'ficheiro', 'Documento de análise do funil com plano de otimização', 20.00, 15],
        [5, 'Pitch Deck para Investidores', 'Desenvolver e validar com mentores um Pitch Deck refinado para apresentação a fundos de Venture Capital ou Business Angels.', 'ficheiro', 'Apresentação em PDF do Pitch Deck atualizado', 20.00, 15]
    ];
    $stmtSeed = $mysqli->prepare("INSERT INTO metas_padrao (fase, numero, titulo, descricao, evidencia_tipo, evidencia_desc, peso_percentual, prazo_dias, activo) VALUES ('tracao', ?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($metasTracaoDefault as $mt) {
        $stmtSeed->bind_param('issssdi', $mt[0], $mt[1], $mt[2], $mt[3], $mt[4], $mt[5], $mt[6]);
        $stmtSeed->execute();
    }
    $stmtSeed->close();
}

// Flash messages
$flashOk   = $_SESSION['flash_ok'] ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

// Filtro: projecto seleccionado
$idProjetoSel = (int)($_GET['projeto'] ?? 0);
$faseSel      = $_GET['fase'] ?? '';
$modo         = $_GET['modo'] ?? 'projetos';

// Buscar projectos (todos exceto rejeitados)
$projectosIncubados = [];
$res = $mysqli->query("
    SELECT p.id, p.titulo, p.fase, p.estado, p.pontos, u.nome as autor
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    WHERE p.estado != 'rejeitado'
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

$todasMetasPadrao = [];
if ($_SESSION['usuario_perfil'] === 'superadmin') {
    $resMP = $mysqli->query("SELECT * FROM metas_padrao WHERE activo = 1 ORDER BY fase, numero");
    if ($resMP) {
        while ($r = $resMP->fetch_assoc()) {
            $todasMetasPadrao[] = $r;
        }
    }
}

$metasPendentes = [];
if ($modo === 'pendentes') {
    $resPend = $mysqli->query("
        SELECT mp.*, p.titulo as projeto_titulo, p.fase as projeto_fase, p.id as projeto_id,
               mpd.titulo as meta_titulo, mpd.evidencia_desc, mpd.evidencia_tipo, mpd.fase as meta_fase,
               u.nome as estudante_nome
        FROM metas_projeto mp
        JOIN projetos p ON p.id = mp.id_projeto
        JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
        JOIN usuarios u ON u.id = p.criado_por
        WHERE mp.estado = 'em_avaliacao'
        ORDER BY mp.evidencia_em ASC
    ");
    if ($resPend) {
        $metasPendentes = $resPend->fetch_all(MYSQLI_ASSOC);
    }
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
<div class="page-header mt-0" style="padding-top:0; margin-bottom:15px;">
    <div>
        <div class="page-header-title" style="font-size:1.4rem;"><i class="fa fa-bullseye me-2" style="color:#D97706"></i>Gestão de Metas</div>
        <div class="page-header-sub">Definição e activação progressiva de metas para startups</div>
    </div>
</div>

<!-- MODO TOGGLE -->
<div class="d-flex gap-2 mb-4">
    <a href="?modo=projetos" class="btn btn-sm <?= $modo === 'projetos' ? 'btn-warning text-white' : 'btn-outline-secondary' ?> fw-bold px-3 py-2 rounded-3" style="font-size:0.8rem;">
        <i class="fa fa-rocket me-1"></i> Acompanhamento de Startups
    </a>
    <a href="?modo=pendentes" class="btn btn-sm <?= $modo === 'pendentes' ? 'btn-warning text-white' : 'btn-outline-secondary' ?> fw-bold px-3 py-2 rounded-3" style="font-size:0.8rem;">
        <i class="fa fa-clipboard-check me-1"></i> Caixa de Entrada de Evidências
        <?php
        $resCountP = $mysqli->query("SELECT COUNT(*) n FROM metas_projeto WHERE estado = 'em_avaliacao'");
        $countP = $resCountP ? (int)$resCountP->fetch_assoc()['n'] : 0;
        if ($countP > 0) {
            echo "<span class='badge bg-danger ms-1' style='font-size:0.65rem; padding: 2px 5px;'>$countP</span>";
        }
        ?>
    </a>
    <?php if ($_SESSION['usuario_perfil'] === 'superadmin'): ?>
    <a href="?modo=dicionario" class="btn btn-sm <?= $modo === 'dicionario' ? 'btn-warning text-white' : 'btn-outline-secondary' ?> fw-bold px-3 py-2 rounded-3" style="font-size:0.8rem;">
        <i class="fa fa-book me-1"></i> Dicionário de Metas Padrão
    </a>
    <?php endif; ?>
</div>

<?php if ($modo === 'dicionario' && $_SESSION['usuario_perfil'] === 'superadmin'): ?>

<!-- ALERT INFORMATIVO -->
<div class="alert alert-info border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3" style="background:#EFF6FF; border-left: 4px solid #3B82F6 !important;">
    <div class="d-flex align-items-center text-start">
        <div style="font-size:1.5rem; color:#3B82F6; margin-right:15px;"><i class="fa fa-info-circle"></i></div>
        <div>
            <strong style="color:#1E293B; font-size:0.88rem;">Dicionário Global do Sistema</strong>
            <p class="text-muted mb-0 small" style="font-size:0.78rem;">Estas são as metas padrão (modelos) da incubadora. Para ativar metas para uma startup específica, aceda à aba <strong>Acompanhamento de Startups</strong>.</p>
        </div>
    </div>
    <a href="?modo=projetos" class="btn btn-primary btn-sm fw-bold px-3 py-2 rounded-3" style="font-size:0.75rem; text-decoration:none;">
        <i class="fa fa-rocket me-1"></i> Ir para Acompanhamento
    </a>
</div>

<!-- PAINEL DE ATIVAÇÃO RÁPIDA -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:16px; background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); color:#fff;">
    <div class="card-body p-4 text-start">
        <h6 class="fw-bold mb-2 text-warning"><i class="fa fa-bolt me-2"></i>Ativação Rápida de Metas por Startup</h6>
        <p class="small text-white-50 mb-3" style="font-size:0.8rem;">Selecione uma startup e a fase para inicializar e ativar todas as metas correspondentes em simultâneo.</p>
        
        <form method="post" action="/incubadora_ispsn/app/controllers/metas_action.php" class="row g-3 align-items-end m-0">
            <input type="hidden" name="action" value="inicializar_e_activar_todas">
            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
            
            <div class="col-md-5">
                <label class="form-label small fw-bold text-white-50" style="font-size:0.75rem;">Startup</label>
                <select name="id_projeto" class="form-select border-0 rounded-3 text-slate-800" style="background:#fff; height:38px; font-size:0.83rem;" required>
                    <option value="" disabled selected>Escolha a Startup...</option>
                    <?php foreach ($projectosIncubados as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['titulo']) ?> (Fase: <?= strtoupper($p['fase']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label class="form-label small fw-bold text-white-50" style="font-size:0.75rem;">Fase das Metas</label>
                <select name="fase" class="form-select border-0 rounded-3 text-slate-800" style="background:#fff; height:38px; font-size:0.83rem;" required>
                    <option value="ideacao">Ideação 💡</option>
                    <option value="validacao">Validação 🔬</option>
                    <option value="mvp">MVP 📦</option>
                    <option value="tracao">Tracção 📈</option>
                    <option value="mercado">Mercado 📊</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <button type="submit" class="btn btn-warning w-100 fw-bold rounded-3 text-dark border-0" style="background:#FBBF24; height:38px; font-size:0.83rem;">
                    <i class="fa fa-play me-1"></i>Inicializar & Activar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <div class="card-title-custom"><i class="fa fa-book text-warning"></i> Dicionário de Metas Padrão do Sistema</div>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalCriarMetaPadrao" style="background:#D97706; border:none;">
            <i class="fa fa-plus me-1"></i> Nova Meta Padrão
        </button>
    </div>
    <div class="card-body-custom">
        <?php if (empty($todasMetasPadrao)): ?>
            <p class="text-muted text-center p-4">Nenhuma meta padrão definida no sistema.</p>
        <?php else: 
            $metasPorFase = [];
            foreach ($todasMetasPadrao as $mp) {
                $metasPorFase[$mp['fase']][] = $mp;
            }
            
            $fasesLabels = [
                'ideacao' => 'Ideação 💡',
                'validacao' => 'Validação 🔬',
                'mvp' => 'MVP 📦',
                'tracao' => 'Tracção 📈',
                'mercado' => 'Mercado 📊'
            ];
            
            foreach ($fasesLabels as $fKey => $fLabel):
                $metasF = $metasPorFase[$fKey] ?? [];
        ?>
            <h5 class="fw-bold mt-4 mb-3" style="color: #1E293B; border-bottom: 2px solid #F1F5F9; padding-bottom: 8px;">
                <?= $fLabel ?> <span class="badge bg-light text-secondary rounded-pill small ms-2" style="font-size:0.75rem;"><?= count($metasF) ?> metas</span>
            </h5>
            
            <?php if (empty($metasF)): ?>
                <p class="text-muted small ps-3">Nenhuma meta padrão definida para esta fase.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th style="width: 8%;">Nº</th>
                                <th style="width: 25%;">Meta</th>
                                <th style="width: 35%;">Descrição / Evidência</th>
                                <th style="width: 10%;">Prazo (Dias)</th>
                                <th style="width: 10%;">Peso</th>
                                <th style="width: 12%;">Acções</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($metasF as $mp): ?>
                            <tr>
                                <td><span class="badge bg-secondary-subtle text-secondary fw-bold rounded-pill px-2.5 py-1">#<?= $mp['numero'] ?></span></td>
                                <td>
                                    <div class="fw-bold text-slate-800"><?= htmlspecialchars($mp['titulo']) ?></div>
                                </td>
                                <td>
                                    <div class="text-muted small" style="line-height:1.4;"><?= htmlspecialchars($mp['descricao']) ?></div>
                                    <div class="mt-1 small" style="font-size: 0.72rem; color: #D97706;">
                                        <i class="fa fa-paperclip me-1"></i><strong><?= strtoupper($mp['evidencia_tipo']) ?></strong>: <?= htmlspecialchars($mp['evidencia_desc']) ?>
                                    </div>
                                </td>
                                <td><strong><?= $mp['prazo_dias'] ?></strong> dias</td>
                                <td><span class="badge bg-warning-subtle text-warning fw-bold"><?= $mp['peso_percentual'] ?>%</span></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-warning btn-sm rounded-3 fw-bold text-dark px-2.5" onclick="abrirModalEditarMetaPadrao(<?= htmlspecialchars(json_encode($mp)) ?>)" style="font-size:0.75rem;">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <form method="post" action="/incubadora_ispsn/app/controllers/metas_action.php" onsubmit="return confirm('Deseja eliminar esta meta padrão? Ela deixará de aparecer em futuras inicializações.')" class="m-0">
                                            <input type="hidden" name="action" value="eliminar_meta_padrao">
                                            <input type="hidden" name="id_meta_padrao" value="<?= $mp['id'] ?>">
                                            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-3 px-2.5" style="font-size:0.75rem;">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($modo === 'pendentes'): ?>
<div class="card-custom mb-4" style="background:#fff; border: 1px solid #E2E8F0; border-radius: 16px; overflow:hidden;">
    <div class="card-header-custom p-4" style="border-bottom:1px solid #F1F5F9; background:#FFFBF2;">
        <h5 class="fw-bold mb-0" style="color:#1C1917;"><i class="fa fa-clipboard-check text-warning me-2"></i>Evidências Pendentes de Avaliação</h5>
    </div>
    <div class="card-body-custom p-4">
        <?php if (empty($metasPendentes)): ?>
            <div class="text-center p-5">
                <div style="font-size:3.5rem; color:#10B981; opacity:0.75; margin-bottom:15px;"><i class="fa fa-circle-check"></i></div>
                <h5 class="fw-bold" style="color:#1E293B; margin-bottom:4px;">Tudo em dia!</h5>
                <p class="text-muted small mb-0">Não existem evidências de metas pendentes de avaliação no momento.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; font-size:0.72rem; font-weight:700; text-transform:uppercase; color:#64748B;">
                            <th style="padding:12px 16px; width:22%;">Startup / Empreendedor</th>
                            <th style="padding:12px 16px; width:25%;">Meta / Fase</th>
                            <th style="padding:12px 16px; width:30%;">Evidência Submetida</th>
                            <th style="padding:12px 16px; width:13%;">Submetido em</th>
                            <th style="padding:12px 16px; width:10%; text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metasPendentes as $mp): 
                            $faseLabels = [
                                'ideacao' => 'Ideação 💡',
                                'validacao' => 'Validação 🔬',
                                'mvp' => 'MVP 📦',
                                'tracao' => 'Tracção 📈',
                                'mercado' => 'Mercado 📊'
                            ];
                            $faseLbl = $faseLabels[$mp['meta_fase']] ?? $mp['meta_fase'];
                            $atrasado = (!empty($mp['data_limite']) && strtotime($mp['evidencia_em']) > strtotime($mp['data_limite']));
                        ?>
                        <tr style="border-bottom: 1px solid #F1F5F9; font-size:0.83rem;">
                            <td style="padding:16px;">
                                <div class="fw-bold text-slate-800" style="font-size:0.88rem;"><?= htmlspecialchars($mp['projeto_titulo']) ?></div>
                                <div class="text-muted small"><i class="fa fa-user me-1"></i><?= htmlspecialchars($mp['estudante_nome']) ?></div>
                            </td>
                            <td style="padding:16px;">
                                <div class="fw-bold text-slate-800"><?= htmlspecialchars($mp['meta_titulo']) ?></div>
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill small mt-1" style="font-size:0.68rem; font-weight:600; padding:2px 8px;"><?= $faseLbl ?></span>
                            </td>
                            <td style="padding:16px;">
                                <?php if ($mp['evidencia_texto']): ?>
                                    <div class="text-slate-700 small mb-2 p-2 rounded bg-light" style="line-height:1.4; border-left: 3px solid #CBD5E1; max-height:90px; overflow-y:auto; font-size:0.78rem;">
                                        <?= nl2br(htmlspecialchars($mp['evidencia_texto'])) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex gap-2">
                                    <?php if ($mp['evidencia_link']): ?>
                                        <a href="<?= htmlspecialchars($mp['evidencia_link']) ?>" target="_blank" class="btn btn-xs btn-outline-warning fw-bold px-2 py-1 rounded-2" style="font-size:0.7rem; border-color:var(--primary); color:var(--primary); text-decoration:none;">
                                            <i class="fa fa-link me-1"></i>Ver Link
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($mp['evidencia_path']): ?>
                                        <a href="/incubadora_ispsn/<?= htmlspecialchars($mp['evidencia_path']) ?>" target="_blank" class="btn btn-xs btn-outline-dark fw-bold px-2 py-1 rounded-2" style="font-size:0.7rem; text-decoration:none;">
                                            <i class="fa fa-download me-1"></i>Ficheiro
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding:16px;">
                                <div class="small fw-semibold"><?= date('d/m/Y H:i', strtotime($mp['evidencia_em'])) ?></div>
                                <?php if ($atrasado): ?>
                                    <span class="badge bg-danger-subtle text-danger fw-bold mt-1" style="font-size:0.65rem; padding: 2px 6px; border-radius:4px;"><i class="fa fa-triangle-exclamation me-1"></i>Com atraso</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success fw-bold mt-1" style="font-size:0.65rem; padding: 2px 6px; border-radius:4px;"><i class="fa fa-check me-1"></i>No prazo</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:16px; text-align:right;">
                                <div class="d-flex flex-column gap-1" style="width:110px; margin-left:auto;">
                                    <button class="btn btn-success btn-sm fw-bold py-1.5 px-2.5 rounded-3" style="font-size:0.72rem;" onclick="validarMeta(<?= $mp['id'] ?>, 'aprovar')">
                                        <i class="fa fa-check me-1"></i>Validar
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm fw-bold py-1.5 px-2.5 rounded-3" style="font-size:0.72rem;" onclick="validarMeta(<?= $mp['id'] ?>, 'reprovar')">
                                        <i class="fa fa-rotate-left me-1"></i>Devolver
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>

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
        
        <?php
        $proximaFaseMap = [
            'ideacao' => ['id' => 'validacao', 'nome' => 'Validação 🔬'],
            'validacao' => ['id' => 'mvp', 'nome' => 'MVP 📦'],
            'mvp' => ['id' => 'tracao', 'nome' => 'Tracção 📈'],
            'tracao' => ['id' => 'mercado', 'nome' => 'Mercado 📊'],
            'mercado' => null
        ];
        $proxInfo = $proximaFaseMap[$faseActual] ?? null;
        if ($percentConcluido >= 100 && $proxInfo):
        ?>
        <div class="mt-4 p-3 rounded-4 border d-flex justify-content-between align-items-center flex-wrap gap-3" style="background: linear-gradient(135deg, #DCFCE7 0%, #F0FDF4 100%); border-color: #10B981 !important;">
            <div style="text-align: left;">
                <h6 class="fw-bold mb-1" style="color:#14532d;"><i class="fa fa-rocket me-2 text-success"></i>Fase Concluída com Sucesso!</h6>
                <p class="text-muted mb-0 small" style="font-size: 0.78rem;">Esta startup completou 100% das metas de <strong><?= strtoupper($faseActual) ?></strong>. Está apta a evoluir.</p>
            </div>
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" class="m-0">
                <input type="hidden" name="action" value="mudar_fase">
                <input type="hidden" name="id_projeto" value="<?= $idProjetoSel ?>">
                <input type="hidden" name="fase" value="<?= $proxInfo['id'] ?>">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                <button type="submit" class="btn btn-success fw-bold px-4 py-2" style="border-radius:10px; font-size:0.83rem; box-shadow: 0 4px 12px rgba(16,185,129,0.2); border: none;">
                    Avançar para <?= $proxInfo['nome'] ?>
                </button>
            </form>
        </div>
        <?php endif; ?>
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
    <?php if ($contEstados['inactiva'] > 0 && in_array($_SESSION['usuario_perfil'], ['superadmin', 'admin'])): ?>
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
            <?php if ($estado === 'inactiva' && in_array($_SESSION['usuario_perfil'], ['superadmin', 'admin'])): ?>
            <button type="button" class="btn btn-warning btn-sm fw-bold w-100" style="border-radius:8px;" onclick="abrirModalActivar(<?= $m['id'] ?>, '<?= htmlspecialchars($m['meta_titulo']) ?>', <?= $m['prazo_dias'] ?>)">
                <i class="fa fa-bolt me-1"></i>Activar
            </button>
            <?php elseif ($estado === 'em_avaliacao' && in_array($_SESSION['usuario_perfil'], ['mentor','admin','superadmin'])): ?>
            <button class="btn btn-success btn-sm fw-bold w-100" style="border-radius:8px;" onclick="validarMeta(<?= $m['id'] ?>, 'aprovar')"><i class="fa fa-check me-1"></i>Validar</button>
            <button class="btn btn-outline-danger btn-sm fw-bold w-100" style="border-radius:8px;" onclick="validarMeta(<?= $m['id'] ?>, 'reprovar')"><i class="fa fa-rotate-left me-1"></i>Devolver</button>
            <?php elseif ($estado === 'activa' && in_array($_SESSION['usuario_perfil'], ['superadmin', 'admin'])): ?>
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
<?php endif; // modo dicionario ?>

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

<!-- Modal de Activação de Meta -->
<div class="modal fade" id="modalActivarMeta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form method="post" action="/incubadora_ispsn/app/controllers/metas_action.php">
                <input type="hidden" name="action" value="activar_meta">
                <input type="hidden" name="id_meta_projeto" id="activarMetaId">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="activarTituloMeta">🎯 Activar Meta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <p class="text-muted small" id="activarDescricaoMeta"></p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="font-size:0.75rem;">Definir Prazo de Conclusão</label>
                        <select name="opcao_prazo" id="opcaoPrazoSelect" class="form-select form-control" onchange="togglePersonalizadoInput()" required style="border-radius:10px;">
                            <option value="padrao" id="opcaoPadraoLabel">Prazo Padrão ([X] dias)</option>
                            <option value="semana">1 Semana (7 dias)</option>
                            <option value="mes">1 Mês (30 dias)</option>
                            <option value="personalizado">Data Personalizada</option>
                        </select>
                    </div>
                    
                    <div class="mb-0" id="prazoPersonalizadoContainer" style="display:none;">
                        <label class="form-label fw-bold small text-uppercase" style="font-size:0.75rem;">Data Limite</label>
                        <input type="date" name="prazo_manual" id="prazoManualInput" class="form-control" min="<?= date('Y-m-d') ?>" style="border-radius:10px;">
                    </div>
                </div>
                
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold rounded-3 px-4" style="background:#D97706; border:none; color:white;">Activar Meta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Criação de Meta Padrão -->
<div class="modal fade" id="modalCriarMetaPadrao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form method="post" action="/incubadora_ispsn/app/controllers/metas_action.php">
                <input type="hidden" name="action" value="criar_meta_padrao">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">⚙️ Criar Nova Meta Padrão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Fase</label>
                            <select name="fase" class="form-select form-control" required style="border-radius:10px;">
                                <option value="ideacao">Ideação 💡</option>
                                <option value="validacao">Validação 🔬</option>
                                <option value="mvp">MVP 📦</option>
                                <option value="tracao">Tracção 📈</option>
                                <option value="mercado">Mercado 📊</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Número Sequencial</label>
                            <input type="number" name="numero" class="form-control" required min="1" value="1" style="border-radius:10px;">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Título da Meta *</label>
                        <input type="text" name="titulo" class="form-control" required placeholder="Ex: Modelo de Negócios (Canvas)" style="border-radius:10px;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Descrição detalhada *</label>
                        <textarea name="descricao" class="form-control" rows="3" required placeholder="Explique em detalhe o que deve ser alcançado..." style="border-radius:10px;"></textarea>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Tipo de Evidência</label>
                            <select name="evidencia_tipo" class="form-select form-control" required style="border-radius:10px;">
                                <option value="ficheiro">Upload de Ficheiro PDF/Imagem</option>
                                <option value="texto">Texto Descritivo</option>
                                <option value="link">Link URL</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Descrição do entregável *</label>
                            <input type="text" name="evidencia_desc" class="form-control" required placeholder="Ex: PDF do canvas preenchido" style="border-radius:10px;">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-0">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Prazo Padrão (Dias)</label>
                            <input type="number" name="prazo_dias" class="form-control" required min="1" value="15" style="border-radius:10px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Peso Percentual (%)</label>
                            <input type="number" name="peso_percentual" class="form-control" required min="1" max="100" value="20" style="border-radius:10px;">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold rounded-3 px-4" style="background:#D97706; border:none; color:white;">Definir Meta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Edição de Meta Padrão -->
<div class="modal fade" id="modalEditarMetaPadrao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form method="post" action="/incubadora_ispsn/app/controllers/metas_action.php">
                <input type="hidden" name="action" value="editar_meta_padrao">
                <input type="hidden" name="id_meta_padrao" id="editarMetaId">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">⚙️ Editar Meta Padrão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body text-start">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Fase</label>
                            <select name="fase" id="editarFase" class="form-select form-control" required style="border-radius:10px;">
                                <option value="ideacao">Ideação 💡</option>
                                <option value="validacao">Validação 🔬</option>
                                <option value="mvp">MVP 📦</option>
                                <option value="tracao">Tracção 📈</option>
                                <option value="mercado">Mercado 📊</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Número Sequencial</label>
                            <input type="number" name="numero" id="editarNumero" class="form-control" required min="1" style="border-radius:10px;">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Título da Meta *</label>
                        <input type="text" name="titulo" id="editarTitulo" class="form-control" required style="border-radius:10px;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Descrição detalhada *</label>
                        <textarea name="descricao" id="editarDescricao" class="form-control" rows="3" required style="border-radius:10px;"></textarea>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Tipo de Evidência</label>
                            <select name="evidencia_tipo" id="editarEvidenciaTipo" class="form-select form-control" required style="border-radius:10px;">
                                <option value="ficheiro">Upload de Ficheiro PDF/Imagem</option>
                                <option value="texto">Texto Descritivo</option>
                                <option value="link">Link URL</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Descrição do entregável *</label>
                            <input type="text" name="evidencia_desc" id="editarEvidenciaDesc" class="form-control" required style="border-radius:10px;">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-0">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Prazo Padrão (Dias)</label>
                            <input type="number" name="prazo_dias" id="editarPrazoDias" class="form-control" required min="1" style="border-radius:10px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="font-size:0.7rem;">Peso Percentual (%)</label>
                            <input type="number" name="peso_percentual" id="editarPesoPercentual" class="form-control" required min="1" max="100" style="border-radius:10px;">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold rounded-3 px-4" style="background:#D97706; border:none; color:white;">Salvar Alterações</button>
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

function abrirModalActivar(id, titulo, prazoDias) {
    document.getElementById('activarMetaId').value = id;
    document.getElementById('activarTituloMeta').textContent = `🎯 Activar Meta`;
    document.getElementById('activarDescricaoMeta').innerHTML = `Está prestes a activar a meta <strong>"${titulo}"</strong>.`;
    document.getElementById('opcaoPadraoLabel').textContent = `Prazo Padrão (${prazoDias} dias)`;
    document.getElementById('opcaoPrazoSelect').value = 'padrao';
    document.getElementById('prazoPersonalizadoContainer').style.display = 'none';
    document.getElementById('prazoManualInput').required = false;
    
    new bootstrap.Modal(document.getElementById('modalActivarMeta')).show();
}

function abrirModalEditarMetaPadrao(meta) {
    document.getElementById('editarMetaId').value = meta.id;
    document.getElementById('editarFase').value = meta.fase;
    document.getElementById('editarNumero').value = meta.numero;
    document.getElementById('editarTitulo').value = meta.titulo;
    document.getElementById('editarDescricao').value = meta.descricao;
    document.getElementById('editarEvidenciaTipo').value = meta.evidencia_tipo;
    document.getElementById('editarEvidenciaDesc').value = meta.evidencia_desc;
    document.getElementById('editarPrazoDias').value = meta.prazo_dias;
    document.getElementById('editarPesoPercentual').value = Math.round(meta.peso_percentual);
    
    new bootstrap.Modal(document.getElementById('modalEditarMetaPadrao')).show();
}

function togglePersonalizadoInput() {
    const sel = document.getElementById('opcaoPrazoSelect').value;
    const container = document.getElementById('prazoPersonalizadoContainer');
    const input = document.getElementById('prazoManualInput');
    
    if (sel === 'personalizado') {
        container.style.display = 'block';
        input.required = true;
    } else {
        container.style.display = 'none';
        input.required = false;
    }
}
</script>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
