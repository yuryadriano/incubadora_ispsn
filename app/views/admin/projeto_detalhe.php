<?php
// app/views/admin/projeto_detalhe.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$perfil    = $_SESSION['usuario_perfil'] ?? 'utilizador';
$idUsuario = (int)$_SESSION['usuario_id'];
$idProjeto = (int)($_GET['id'] ?? 0);

if (!$idProjeto) {
    header('Location: /incubadora_ispsn/app/views/admin/projetos.php');
    exit;
}

// ── Buscar projecto ────────────────────────
$stmt = $mysqli->prepare("
    SELECT p.*, u.nome autor, u.email email_autor, u.telefone tel_autor
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    WHERE p.id = ?
");
$stmt->bind_param('i', $idProjeto);
$stmt->execute();
$projeto = $stmt->get_result()->fetch_assoc();

if (!$projeto) {
    header('Location: /incubadora_ispsn/app/views/admin/projetos.php');
    exit;
}

// ── Segurança: estudante só vê os próprios ─
if ($perfil === 'utilizador' && $projeto['criado_por'] !== $idUsuario) {
    header('Location: /incubadora_ispsn/public/index.php');
    exit;
}

// ── Membros ────────────────────────────────
$membros = [];
$r = $mysqli->prepare("
    SELECT u.nome, u.email, mp.papel, mp.id_usuario
    FROM membros_projeto mp
    JOIN usuarios u ON u.id = mp.id_usuario
    WHERE mp.id_projeto = ?
");
$r->bind_param('i', $idProjeto);
$r->execute();
$membros = $r->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Comentários ────────────────────────────
$comentarios = [];
$r2 = $mysqli->prepare("
    SELECT c.comentario, c.fase, c.criado_em, u.nome, u.perfil
    FROM comentarios_projetos c
    JOIN usuarios u ON u.id = c.id_usuario
    WHERE c.id_projeto = ?
    ORDER BY c.criado_em DESC
");
$r2->bind_param('i', $idProjeto);
$r2->execute();
$comentarios = $r2->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Avaliação existente ────────────────────
$avaliacao = null;
if (in_array($perfil, ['admin','superadmin'])) {
    $r3 = $mysqli->prepare("SELECT * FROM avaliacoes WHERE id_projeto=? AND id_avaliador=? LIMIT 1");
    $r3->bind_param('ii', $idProjeto, $idUsuario);
    $r3->execute();
    $avaliacao = $r3->get_result()->fetch_assoc();

    // Média de todas as avaliações
    $r4 = $mysqli->prepare("SELECT AVG(pontuacao_total) avg, COUNT(*) n FROM avaliacoes WHERE id_projeto=?");
    $r4->bind_param('i', $idProjeto);
    $r4->execute();
    $mediaAval = $r4->get_result()->fetch_assoc();
    $r4->close();

    // Média de cada um dos 8 critérios
    $stmtAvg = $mysqli->prepare("
        SELECT 
            AVG(nota_inovacao) avg_inovacao,
            AVG(nota_sustentabilidade) avg_sustentabilidade,
            AVG(nota_escalabilidade) avg_escalabilidade,
            AVG(nota_impacto) avg_impacto,
            AVG(nota_viabilidade) avg_viabilidade,
            AVG(nota_equipa) avg_equipa,
            AVG(nota_mercado) avg_mercado,
            AVG(nota_proposta) avg_proposta
        FROM avaliacoes WHERE id_projeto = ?
    ");
    $stmtAvg->bind_param('i', $idProjeto);
    $stmtAvg->execute();
    $mediaCrit = $stmtAvg->get_result()->fetch_assoc();
    $stmtAvg->close();
}

// ── Dados Financeiros ──────────────────────
$stmtFin = $mysqli->prepare("SELECT * FROM financiamentos WHERE id_projeto = ?");
$stmtFin->bind_param('i', $idProjeto);
$stmtFin->execute();
$financiamentos = $stmtFin->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtFin->close();

$totalAprovado = 0; $totalExecutado = 0;
foreach($financiamentos as $f) {
    $totalAprovado += $f['montante_aprovado'];
    $totalExecutado += $f['montante_executado'];
}
$percentagemExec = $totalAprovado > 0 ? round(($totalExecutado / $totalAprovado) * 100) : 0;

// ── KPIs e Registos ────────────────────────
$stmtKpi = $mysqli->prepare("
    SELECT k.*, 
           (SELECT valor FROM registos_kpi WHERE id_kpi = k.id ORDER BY registado_em DESC LIMIT 1) as ultimo_valor
    FROM kpis k WHERE k.id_projeto = ? AND k.activo = 1
");
$stmtKpi->bind_param('i', $idProjeto);
$stmtKpi->execute();
$kpis = $stmtKpi->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtKpi->close();

// ── Documentos ─────────────────────────────
$stmtDoc = $mysqli->prepare("
    SELECT f.*, u.nome as quem_submeteu 
    FROM ficheiros_projeto f
    JOIN usuarios u ON u.id = f.id_usuario_up
    WHERE f.id_projeto = ?
    ORDER BY f.criado_em DESC
");
$stmtDoc->bind_param('i', $idProjeto);
$stmtDoc->execute();
$documentos = $stmtDoc->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtDoc->close();


// ── Metas / Tarefas ────────────────────────
$tarefasProjeto = [];
$stmtT = $mysqli->prepare("SELECT * FROM tarefas WHERE id_projeto = ? ORDER BY data_limite ASC");
if ($stmtT) {
    $stmtT->bind_param('i', $idProjeto);
    $stmtT->execute();
    $tarefasProjeto = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtT->close();
}

$metasProjeto = [];
$stmtM = $mysqli->prepare("
    SELECT mp.*, mpd.titulo as meta_titulo, mpd.descricao as meta_descricao,
           mpd.evidencia_tipo, mpd.evidencia_desc, mpd.peso_percentual, mpd.prazo_dias,
           mpd.fase, mpd.numero,
           ua.nome as activador_nome, uv.nome as validador_nome
    FROM metas_projeto mp
    JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
    LEFT JOIN usuarios ua ON ua.id = mp.activada_por
    LEFT JOIN usuarios uv ON uv.id = mp.validada_por
    WHERE mp.id_projeto = ?
    ORDER BY mpd.fase, mpd.numero
");
if ($stmtM) {
    $stmtM->bind_param('i', $idProjeto);
    $stmtM->execute();
    $metasProjeto = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtM->close();
}

// ── Mentor associado e mentores disponíveis ─
$mentorAssoc = null;
$stmtMent = $mysqli->prepare("
    SELECT m.id as id_mentoria, mt.id as id_mentor, u.nome, u.email, mt.especialidade
    FROM mentorias m
    JOIN mentores mt ON mt.id = m.id_mentor
    JOIN usuarios u ON u.id = mt.id_usuario
    WHERE m.id_projeto = ? AND m.estado = 'activa'
    LIMIT 1
");
if ($stmtMent) {
    $stmtMent->bind_param('i', $idProjeto);
    $stmtMent->execute();
    $mentorAssoc = $stmtMent->get_result()->fetch_assoc();
    $stmtMent->close();
}

$mentoresDisponiveis = [];
$resMent = $mysqli->query("
    SELECT mt.id, u.nome, mt.especialidade 
    FROM mentores mt 
    JOIN usuarios u ON u.id = mt.id_usuario 
    WHERE mt.disponivel = 1 
    ORDER BY u.nome
");
if ($resMent) {
    while ($row = $resMent->fetch_assoc()) {
        $mentoresDisponiveis[] = $row;
    }
}

$isDono = ($idUsuario === (int)$projeto['criado_por']);
$podeGerirMembros = $isDono || in_array($perfil, ['admin','superadmin']);

// ── Flash messages ─────────────────────────
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

$tituloPagina = 'Projecto: ' . mb_strimwidth($projeto['titulo'], 0, 40, '…');
$paginaActiva = 'projetos';

$mapaProgresso = [
    'submetido'          => 10,
    'em_avaliacao'       => 30,
    'aprovado'           => 50,
    'incubado'           => 75,
    'fundo_investimento' => 90,
    'concluido'          => 100,
    'rejeitado'          => 0
];
$progresso = $mapaProgresso[$projeto['estado']] ?? 10;

// Redirect padrão após acções
$redirectBack = "/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=$idProjeto";

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- FLASH -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<!-- MOSTRAR LINK GERADO -->
<?php if (isset($_SESSION['convite_link'])): ?>
    <div class="card-custom mb-4" style="background:#EEF2FF; border:1px solid #C7D2FE">
        <div class="card-body-custom">
            <h5 class="fw-bold text-primary"><i class="fa fa-link me-2"></i>Link de Convite Gerado!</h5>
            <p class="small text-muted mb-3">Copie a mensagem abaixo e envie para o professor/mentor.</p>
            <div class="p-3 bg-white border rounded mb-3" id="inviteMessageText"><?= nl2br(htmlspecialchars($_SESSION['convite_msg'])) ?></div>
            <button class="btn-primary-custom w-100" onclick="copyInvite()">
                <i class="fa fa-copy me-2"></i> Copiar Convite Completo
            </button>
        </div>
    </div>
    <script>
    function copyInvite() {
        const text = document.getElementById('inviteMessageText').innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('Convite copiado para a área de transferência!');
        });
    }
    </script>
    <?php unset($_SESSION['convite_link'], $_SESSION['convite_msg']); ?>
<?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <a href="/incubadora_ispsn/app/views/admin/projetos.php" class="btn-ghost" style="margin-bottom:10px;font-size:0.82rem">
            <i class="fa fa-arrow-left"></i> Voltar à lista
        </a>
        <div class="page-header-title"><?= htmlspecialchars($projeto['titulo']) ?></div>
        <div class="page-header-sub d-flex align-items-center gap-2 mt-1">
            <?php
            $labels = [
                'submetido'          => 'Submetido (Triagem)',
                'em_avaliacao'       => 'Em Avaliação Técnica 🔍',
                'aprovado'           => 'Aprovado para Incubação ✓',
                'rejeitado'          => 'Rejeitado ✗',
                'incubado'           => 'Em Incubação 🚀',
                'fundo_investimento' => 'Pronto p/ Financiamento 💰',
                'concluido'          => 'Graduado / Concluído ✨'
            ];
            $corBadge = [
                'submetido'    => 'secondary',
                'em_avaliacao' => 'warning',
                'aprovado'     => 'success',
                'rejeitado'    => 'danger',
                'incubado'     => 'primary',
                'fundo_investimento' => 'success',
                'concluido'    => 'success'
            ][$projeto['estado']] ?? 'info';
            ?>
            <span class="badge-estado badge-<?= $corBadge ?>">
                <?= $labels[$projeto['estado']] ?? $projeto['estado'] ?>
            </span>
            <span style="color:var(--text-muted)">·</span>
            <span style="font-size:0.82rem;color:var(--text-muted)"><?= strtoupper($projeto['tipo']) ?></span>
            <span style="color:var(--text-muted)">·</span>
            <span style="font-size:0.82rem;color:var(--text-muted)"><?= date('d/m/Y', strtotime($projeto['criado_em'])) ?></span>
        </div>
    </div>
    <?php if (in_array($perfil, ['admin','superadmin','mentor','funcionario'])): ?>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/incubadora_ispsn/app/views/admin/relatorio_projeto.php?id=<?= $idProjeto ?>" target="_blank" class="btn-ghost" title="Gerar Relatório PDF">
            <i class="fa fa-file-pdf"></i> Relatório
        </a>
        <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" style="display:inline" id="formIA">
            <input type="hidden" name="action" value="gerar_analise_ia">
            <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
            <button type="submit" class="btn-ghost" style="color:var(--primary)" onclick="this.innerHTML='<i class=\'fa fa-spinner fa-spin\'></i> Analisando...';">
                <i class="fa fa-robot"></i> Consultar IA
            </button>
        </form>
        
        <?php if ($projeto['estado'] === 'incubado' && in_array($perfil, ['admin','superadmin'])): ?>
        <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#modalConvidarMentorProjeto" style="color:var(--primary)">
            <i class="fa fa-user-tie"></i> Convidar Mentor
        </button>
        <?php endif; ?>

        <?php if (in_array($perfil, ['admin','superadmin'])): ?>
        <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#modalEstado">
            <i class="fa fa-sliders"></i> Mudar Estado
        </button>
        <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle_destaque">
            <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
            <button type="submit" class="btn-ghost" style="color: <?= $projeto['destaque_publico'] ? '#10b981' : '#64748b' ?>" title="<?= $projeto['destaque_publico'] ? 'Remover da Vitrine Pública' : 'Publicar na Vitrine Pública' ?>">
                <i class="fa fa-<?= $projeto['destaque_publico'] ? 'eye' : 'eye-slash' ?>"></i> <?= $projeto['destaque_publico'] ? 'Na Vitrine' : 'Publicar' ?>
            </button>
        </form>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalAvaliar">
            <i class="fa fa-star"></i> Avaliar
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ANÁLISE DE INTELIGÊNCIA ARTIFICIAL -->
<?php if ($perfil !== 'utilizador' && !empty($projeto['feedback_ia'])): ?>
    <div class="card-custom mb-4" style="background: linear-gradient(135deg, #f5f3ff 0%, #ffffff 100%); border-left: 5px solid #8b5cf6;">
        <div class="card-body-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-primary mb-0"><i class="fa fa-brain me-2"></i>Análise Estratégica IA</h5>
                <span class="badge bg-primary small">Exclusivo Mentor/Autor</span>
            </div>
            <div class="ai-feedback-container">
                <?= $projeto['feedback_ia'] ?>
            </div>
            <hr>
            <div class="text-end">
                <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" style="display:inline">
                    <input type="hidden" name="action" value="gerar_analise_ia">
                    <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                    <button type="submit" class="btn btn-sm btn-link text-decoration-none p-0" style="font-size:0.75rem; color:#8b5cf6">
                        <i class="fa fa-sync me-1"></i> Atualizar Análise
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php elseif ($perfil === 'utilizador'): ?>
    <!-- METAS E EVIDÊNCIAS -->
    <div class="card-custom mb-4" style="background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%); border-left: 5px solid #d97706;">
        <div class="card-body-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-warning-dark mb-0" style="color: #b45309;"><i class="fa fa-list-check me-2"></i>Metas & Progresso da Startup</h5>
                <span class="badge bg-warning text-dark small fw-bold">Próximos Passos</span>
            </div>
            
            <?php 
            $faseActual = $projeto['fase'] ?? 'ideacao';
            $metasFase = array_filter($metasProjeto, fn($m) => $m['fase'] === $faseActual);
            
            $metasConcluidas = count(array_filter($metasFase, fn($m) => $m['estado'] === 'concluida'));
            $totalMetas = count($metasFase);
            
            $tarefasConcluidas = count(array_filter($tarefasProjeto, fn($t) => $t['validada_mentor'] == 1));
            $totalTarefas = count($tarefasProjeto);
            
            $concluidasTotal = $metasConcluidas + $tarefasConcluidas;
            $totalItens = $totalMetas + $totalTarefas;
            $pctTotal = $totalItens > 0 ? round(($concluidasTotal / $totalItens) * 100) : 0;
            ?>

            <?php if (empty($metasFase) && empty($tarefasProjeto)): ?>
                <p class="text-muted small mb-0">Nenhuma meta ou tarefa atribuída a esta startup de momento. O seu orientador irá definir as metas para a fase atual em breve.</p>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <p class="small text-muted mb-2">Completou <strong><?= $concluidasTotal ?> de <?= $totalItens ?> metas e tarefas</strong> obrigatórias para a fase de <strong><?= strtoupper(str_replace('_', ' ', $faseActual)) ?></strong>.</p>
                        <div class="progress-custom mb-3" style="height: 8px;">
                            <div class="progress-bar-custom" style="width: <?= $pctTotal ?>%; background: #d97706;"></div>
                        </div>
                        
                        <div class="row">
                            <!-- METAS DO PROGRAMA -->
                            <?php if (!empty($metasFase)): ?>
                            <div class="col-sm-6 mb-3 mb-sm-0">
                                <div class="fw-bold mb-2 text-dark" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:#555;"><i class="fa fa-bullseye me-1 text-warning"></i> Metas do Programa</div>
                                <div style="display:flex; flex-direction:column; gap:8px; max-height: 220px; overflow-y: auto; padding-right: 5px;">
                                    <?php foreach($metasFase as $m): 
                                        $est = $m['estado'];
                                        $badgeBg = '#f3f4f6; color:#4b5563';
                                        $badgeLbl = 'Bloqueada';
                                        $icon = '<i class="fa fa-lock text-muted"></i>';
                                        
                                        if ($est === 'concluida') {
                                            $badgeBg = '#d1fae5; color:#065f46';
                                            $badgeLbl = 'Concluída';
                                            $icon = '<i class="fa fa-circle-check text-success"></i>';
                                        } elseif ($est === 'activa') {
                                            $badgeBg = '#fef3c7; color:#92400e';
                                            $badgeLbl = 'Ativa';
                                            $icon = '<i class="fa fa-bolt text-warning"></i>';
                                        } elseif ($est === 'em_avaliacao') {
                                            $badgeBg = '#e0f2fe; color:#0369a1';
                                            $badgeLbl = 'A avaliar';
                                            $icon = '<i class="fa fa-clock text-info"></i>';
                                        } elseif ($est === 'reprovada') {
                                            $badgeBg = '#fee2e2; color:#b91c1c';
                                            $badgeLbl = 'Devolvida';
                                            $icon = '<i class="fa fa-triangle-exclamation text-danger"></i>';
                                        }
                                    ?>
                                        <div class="p-2 rounded shadow-sm d-flex align-items-center justify-content-between" style="background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.04);">
                                            <div class="d-flex align-items-center gap-2 text-truncate" style="max-width:70%;">
                                                <?= $icon ?>
                                                <span class="small fw-semibold text-truncate <?= $est === 'concluida' ? 'text-decoration-line-through text-muted' : '' ?>" title="<?= htmlspecialchars($m['meta_titulo']) ?>">
                                                    <?= $m['numero'] ?>. <?= htmlspecialchars($m['meta_titulo']) ?>
                                                </span>
                                            </div>
                                            <span class="badge" style="font-size: 0.62rem; padding: 4px 8px; background: <?= $badgeBg ?>;">
                                                <?= $badgeLbl ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- TAREFAS AD-HOC -->
                            <div class="col-sm-6">
                                <div class="fw-bold mb-2 text-dark" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:#555;"><i class="fa fa-list-check me-1 text-primary"></i> Tarefas Ad-hoc</div>
                                <div style="display:flex; flex-direction:column; gap:8px; max-height: 220px; overflow-y: auto; padding-right: 5px;">
                                    <?php if (empty($tarefasProjeto)): ?>
                                        <small class="text-muted p-2">Sem tarefas adicionais criadas pelo mentor.</small>
                                    <?php else: ?>
                                        <?php foreach($tarefasProjeto as $t): 
                                            $statusIcon = $t['validada_mentor'] 
                                                ? '<i class="fa fa-circle-check text-success"></i>' 
                                                : ($t['status'] === 'concluida' 
                                                    ? '<i class="fa fa-clock text-warning" title="Aguardando validação"></i>' 
                                                    : ($t['status'] === 'em_progresso' 
                                                        ? '<i class="fa fa-spinner fa-spin text-primary"></i>' 
                                                        : '<i class="fa fa-circle text-muted"></i>'));
                                        ?>
                                            <div class="p-2 rounded shadow-sm d-flex align-items-center justify-content-between" style="background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.04);">
                                                <div class="d-flex align-items-center gap-2 text-truncate" style="max-width:70%;">
                                                    <?= $statusIcon ?>
                                                    <span class="small fw-semibold text-truncate <?= $t['validada_mentor'] ? 'text-decoration-line-through text-muted' : '' ?>" title="<?= htmlspecialchars($t['titulo']) ?>">
                                                        <?= htmlspecialchars($t['titulo']) ?>
                                                    </span>
                                                </div>
                                                <span class="badge" style="font-size: 0.62rem; padding: 4px 8px; background: <?= $t['validada_mentor'] ? '#d1fae5; color:#065f46' : ($t['status'] === 'concluida' ? '#fef3c7; color:#92400e' : '#f3f4f6; color:#4b5563') ?>;">
                                                    <?= $t['validada_mentor'] ? 'Validada' : ($t['status'] === 'concluida' ? 'A avaliar' : 'Pendente') ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end d-flex flex-column justify-content-center align-items-md-end gap-2">
                        <a href="/incubadora_ispsn/app/views/dashboard/utilizador.php" class="btn-primary-custom text-center" style="background:#d97706; border-color:#d97706; padding: 10px 20px; font-size:0.82rem; text-decoration:none; color:#fff; border-radius: 8px;">
                            <i class="fa fa-paper-plane me-1"></i> Submeter Evidências
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- MODAL: Convidar Mentor p/ Projecto -->
<div class="modal fade" id="modalConvidarMentorProjeto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/convite_action.php">
                <input type="hidden" name="action" value="gerar_link">
                <input type="hidden" name="perfil" value="mentor">
                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= $redirectBack ?>">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-envelope-open-text me-2"></i>Convidar Mentor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <p class="text-muted small mb-3">Gerar link de convite para mentorar o projecto <strong><?= htmlspecialchars($projeto['titulo']) ?></strong>.</p>
                    <div class="mb-3">
                        <label class="form-label-custom">E-mail do Especialista / Professor (opcional)</label>
                        <input type="email" name="email" class="form-control-custom" placeholder="exemplo@ispsn.org">
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Mensagem do Convite</label>
                        <textarea name="mensagem" class="form-control-custom" rows="4">Olá, gostaríamos do seu apoio como mentor para o projeto "<?= htmlspecialchars($projeto['titulo']) ?>" na nossa incubadora. O seu conhecimento seria fundamental.</textarea>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-link"></i> Gerar Convite Manual</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- TIMELINE DE MATURIDADE -->
<div class="card-custom mb-4" style="border:none; background: transparent; box-shadow:none">
    <div class="d-flex justify-content-between mb-3 align-items-center">
        <h5 class="fw-bold mb-0"><i class="fa fa-map-signs me-2" style="color:var(--primary)"></i>Jornada da Startup</h5>
        <?php if (in_array($perfil, ['admin','superadmin'])): ?>
        <div class="dropdown">
            <button class="btn-ghost dropdown-toggle" type="button" data-bs-toggle="dropdown" style="font-size:0.75rem">
                Mudar Fase
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="mudarFase('ideacao')">Ideação</a></li>
                <li><a class="dropdown-item" href="#" onclick="mudarFase('validacao')">Validação</a></li>
                <li><a class="dropdown-item" href="#" onclick="mudarFase('mvp')">MVP</a></li>
                <li><a class="dropdown-item" href="#" onclick="mudarFase('tracao')">Tração</a></li>
                <li><a class="dropdown-item" href="#" onclick="mudarFase('mercado')">Mercado</a></li>
            </ul>
        </div>
        <form id="formMudarFase" action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" style="display:none">
            <input type="hidden" name="action" value="mudar_fase">
            <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
            <input type="hidden" name="fase" id="inputFase">
        </form>
        <script>
            function mudarFase(fase) {
                if(confirm('Deseja mudar a fase da startup para ' + fase.toUpperCase() + '?')) {
                    document.getElementById('inputFase').value = fase;
                    document.getElementById('formMudarFase').submit();
                }
            }
        </script>
        <?php endif; ?>
    </div>
    
    <div class="maturity-pipeline">
        <?php 
        $fases = [
            ['id' => 'ideacao',   'label' => 'Ideação',   'icon' => 'lightbulb'],
            ['id' => 'validacao', 'label' => 'Validação', 'icon' => 'vial'],
            ['id' => 'mvp',       'label' => 'MVP',       'icon' => 'cube'],
            ['id' => 'tracao',    'label' => 'Tração',    'icon' => 'chart-line'],
            ['id' => 'mercado',   'label' => 'Mercado',   'icon' => 'shop']
        ];
        $currentFaseIdx = 0;
        foreach($fases as $idx => $f) if($f['id'] == ($projeto['fase'] ?? 'ideacao')) $currentFaseIdx = $idx;
        ?>
        <div class="pipeline-container">
            <?php foreach($fases as $idx => $f): 
                $status = ($idx < $currentFaseIdx) ? 'completed' : (($idx == $currentFaseIdx) ? 'active' : 'pending');
            ?>
                <div class="pipeline-step <?= $status ?>">
                    <div class="step-icon"><i class="fa fa-<?= $f['icon'] ?>"></i></div>
                    <div class="step-label"><?= $f['label'] ?></div>
                </div>
                <?php if($idx < 4): ?>
                    <div class="pipeline-line <?= ($idx < $currentFaseIdx) ? 'completed' : '' ?>"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.maturity-pipeline { padding: 10px 0; }
.pipeline-container { display: flex; align-items: center; justify-content: space-between; position: relative; }
.pipeline-step { display: flex; flex-direction: column; align-items: center; z-index: 2; flex: 1; }
.step-icon { width: 45px; height: 45px; border-radius: 50%; background: #f3f4f6; color: #9ca3af; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; transition: all 0.3s; border: 3px solid #fff; box-shadow: 0 0 0 2px #f3f4f6; }
.step-label { font-size: 0.75rem; font-weight: 600; margin-top: 8px; color: #9ca3af; }
.pipeline-line { flex: 1; height: 4px; background: #f3f4f6; margin-top: -25px; z-index: 1; transition: all 0.3s; }
.pipeline-step.active .step-icon { background: var(--primary); color: white; transform: scale(1.15); box-shadow: 0 0 0 2px var(--primary); }
.pipeline-step.active .step-label { color: var(--primary); }
.pipeline-step.completed .step-icon { background: #10b981; color: white; box-shadow: 0 0 0 2px #10b981; }
.pipeline-step.completed .step-label { color: #10b981; }
.pipeline-line.completed { background: #10b981; }

@media (max-width: 576px) {
    .pipeline-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 24px;
        padding-left: 15px;
    }
    .pipeline-container::before {
        content: '';
        position: absolute;
        left: 36px;
        top: 20px;
        bottom: 20px;
        width: 3px;
        background: #f3f4f6;
        z-index: 1;
    }
    .pipeline-step {
        flex-direction: row;
        align-items: center;
        gap: 16px;
        text-align: left;
        width: 100%;
        flex: none;
    }
    .step-label {
        margin-top: 0;
        font-size: 0.85rem;
    }
    .pipeline-line {
        display: none;
    }
}
</style>

<!-- PROGRESSO -->
<div class="card-custom mb-4">
    <div class="card-body-custom">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span style="font-weight:600;font-size:0.875rem">
                Progresso do Projecto
            </span>
            <span style="font-weight:800;color:var(--primary)"><?= $progresso ?>%</span>
        </div>
        <div class="progress-custom" style="height:10px">
            <div class="progress-bar-custom" style="width:<?= $progresso ?>%"></div>
        </div>
        <div class="d-flex justify-content-between mt-2" style="font-size:0.75rem;color:var(--text-muted)">
            <span>Submetido</span><span>Em Avaliação</span><span>Aprovado</span><span>Incubado</span><span>Financiado</span>
        </div>
    </div>
</div>

<!-- GRID PRINCIPAL -->
<div class="row g-4">

    <!-- COLUNA ESQUERDA: Info + Membros + Avaliação média -->
    <div class="col-lg-4">

        <!-- Info do projecto -->
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-circle-info"></i> Detalhes</div>
            </div>
            <div class="card-body-custom">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div>
                        <div class="form-label-custom">Autor</div>
                        <div style="font-weight:600"><?= htmlspecialchars($projeto['autor']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($projeto['email_autor']) ?></small>
                    </div>
                    <div>
                        <div class="form-label-custom">Área Temática</div>
                        <div><?= ucfirst($projeto['area_tematica'] ?? '—') ?></div>
                    </div>
                    <div>
                        <div class="form-label-custom">Submetido em</div>
                        <div><?= date('d/m/Y \à\s H:i', strtotime($projeto['criado_em'])) ?></div>
                    </div>
                    <?php if ($projeto['motivo_rejeicao']): ?>
                    <div>
                        <div class="form-label-custom" style="color:var(--danger)">Motivo de Rejeição</div>
                        <p style="font-size:0.875rem;color:var(--danger);margin:0">
                            <?= nl2br(htmlspecialchars($projeto['motivo_rejeicao'])) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Mentoria / Orientação -->
        <?php if ($perfil !== 'utilizador'): ?>
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-user-tie"></i> Mentoria / Orientação</div>
            </div>
            <div class="card-body-custom">
                <?php if ($mentorAssoc): ?>
                    <div class="d-flex align-items-center gap-3">
                        <div class="user-avatar" style="background:#8B5CF6; width:34px; height:34px; border-radius:50%; color:#fff; font-size:0.8rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0">
                            <?= strtoupper(substr($mentorAssoc['nome'], 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:0.875rem"><?= htmlspecialchars($mentorAssoc['nome']) ?></div>
                            <div style="font-size:0.75rem;color:var(--text-secondary)"><?= htmlspecialchars($mentorAssoc['especialidade']) ?></div>
                            <small class="text-muted" style="font-size:0.72rem"><?= htmlspecialchars($mentorAssoc['email']) ?></small>
                        </div>
                    </div>
                    <?php if (in_array($perfil, ['admin', 'superadmin'])): ?>
                        <form method="post" action="/incubadora_ispsn/app/controllers/mentoria_action.php" style="margin:0">
                            <input type="hidden" name="action" value="mudar_estado_mentoria">
                            <input type="hidden" name="id_mentoria" value="<?= $mentorAssoc['id_mentoria'] ?>">
                            <input type="hidden" name="estado_m" value="cancelada">
                            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                            <button type="submit" class="btn-ghost text-danger btn-sm w-100 mt-3" onclick="return confirm('Tem a certeza que deseja remover a orientação deste mentor?')">
                                <i class="fa fa-user-xmark"></i> Remover Mentor
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted small mb-3">Nenhum mentor associado a esta startup no momento.</p>
                    <?php if (in_array($perfil, ['admin', 'superadmin'])): ?>
                        <form method="post" action="/incubadora_ispsn/app/controllers/mentoria_action.php" style="margin:0">
                            <input type="hidden" name="action" value="criar_mentoria">
                            <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                            <input type="hidden" name="data_inicio" value="<?= date('Y-m-d') ?>">
                            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                            
                            <div class="mb-2">
                                <select name="id_mentor" class="form-control-custom" style="font-size: 0.8rem; padding: 8px 12px;" required>
                                    <option value="">— Seleccionar Orientador —</option>
                                    <?php foreach ($mentoresDisponiveis as $mD): ?>
                                        <option value="<?= $mD['id'] ?>"><?= htmlspecialchars($mD['nome']) ?> (<?= htmlspecialchars($mD['especialidade']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn-primary-custom w-100" style="padding: 7px 12px; font-size:0.8rem; justify-content:center;">
                                <i class="fa fa-user-plus"></i> Associar Orientador
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Membros da equipa -->
        <div class="card-custom mb-4">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <div class="card-title-custom"><i class="fa fa-users"></i> Equipa</div>
                <?php if (in_array($perfil, ['admin','superadmin'])): ?>
                <button class="btn-ghost btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdicionarMembro">
                    <i class="fa fa-plus"></i>
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body-custom">
                <?php if (empty($membros)): ?>
                <p class="text-muted" style="font-size:0.875rem;margin:0">Sem membros registados</p>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:12px">
                    <?php foreach ($membros as $m): ?>
                    <div class="d-flex align-items-center justify-content-between">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:34px;height:34px;border-radius:50%;background:var(--primary);color:#fff;
                                         font-size:0.8rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <?= strtoupper(substr($m['nome'] ?? 'U',0,1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:0.875rem"><?= htmlspecialchars($m['nome'] ?? 'Utilizador') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($m['papel'] ?? 'Membro') ?></small>
                            </div>
                        </div>
                        <?php if (in_array($perfil, ['admin','superadmin']) && $m['id_usuario'] != $projeto['criado_por']): ?>
                        <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" style="margin:0">
                            <input type="hidden" name="action" value="remover_membro">
                            <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                            <input type="hidden" name="id_usuario_remover" value="<?= $m['id_usuario'] ?>">
                            <button type="submit" class="btn-ghost text-danger p-1" onclick="return confirm('Remover este membro?')">
                                <i class="fa fa-trash-can"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Média avaliações -->
        <?php if (!empty($mediaAval['n'])): ?>
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-medal"></i> Avaliação Média</div>
            </div>
            <div class="card-body-custom text-center">
                <div style="font-size:2.5rem;font-weight:800;color:<?= $mediaAval['avg']>=7?'var(--success)':($mediaAval['avg']>=4?'var(--warning)':'var(--danger)') ?>">
                    <?= number_format($mediaAval['avg'],1) ?>
                </div>
                <div style="font-size:0.8rem;color:var(--text-muted)">
                    de 10 · <?= $mediaAval['n'] ?> avaliação(ões)
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Radar Chart da Avaliação -->
        <?php if (!empty($mediaAval['n']) && $mediaAval['n'] > 0): ?>
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-chart-pie text-warning"></i> Desempenho 8D</div>
            </div>
            <div class="card-body-custom text-center">
                <div style="max-width: 100%; height: 260px; margin: 0 auto; display: flex; justify-content: center; align-items: center;">
                    <canvas id="radarChartAvaliacao"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Execução Financeira -->
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-money-bill-wave"></i> Finanças</div>
            </div>
            <div class="card-body-custom">
                <div class="mb-2 d-flex justify-content-between">
                    <small class="text-muted">Executado / Aprovado</small>
                    <span class="fw-bold"><?= $percentagemExec ?>%</span>
                </div>
                <div class="progress-custom mb-2" style="height:8px">
                    <div class="progress-bar-custom" style="width:<?= $percentagemExec ?>%; background:var(--success)"></div>
                </div>
                <div style="font-size:0.8rem">
                    <div class="d-flex justify-content-between">
                        <span>Aprovado:</span>
                        <span class="fw-bold"><?= number_format($totalAprovado, 0, ',', '.') ?> Kz</span>
                    </div>
                    <div class="d-flex justify-content-between text-success">
                        <span>Executado:</span>
                        <span class="fw-bold"><?= number_format($totalExecutado, 0, ',', '.') ?> Kz</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHAT DE MENTORIA -->
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-comments"></i> Chat / Consultoria</div>
            </div>
            <div class="card-body-custom p-0">
                <div id="chat-admin-container" style="height: 300px; overflow-y: auto; padding: 15px; background: #f8fafc;">
                    <?php 
                    $msgAdm = $mysqli->prepare("SELECT m.*, u.nome, u.perfil FROM mensagens m JOIN usuarios u ON u.id = m.id_usuario WHERE m.id_projeto = ? ORDER BY m.criado_at ASC");
                    $msgAdm->bind_param('i', $idProjeto);
                    $msgAdm->execute();
                    $mChat = $msgAdm->get_result()->fetch_all(MYSQLI_ASSOC);
                    foreach($mChat as $mc): 
                        $isMe = ($mc['id_usuario'] == $idUsuario);
                    ?>
                        <div class="mb-3 <?= $isMe ? 'text-end' : '' ?>">
                            <div class="small fw-bold" style="font-size: 0.65rem; color: #94a3b8;"><?= htmlspecialchars($mc['nome']) ?>:</div>
                            <div class="p-2 shadow-sm d-inline-block <?= $isMe ? 'bg-primary text-white text-start' : 'bg-white' ?>" style="border-radius: 8px; font-size: 0.8rem; max-width:90%">
                                <?= nl2br(htmlspecialchars($mc['mensagem'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="p-2 border-top">
                    <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" class="d-flex gap-1">
                        <input type="hidden" name="action" value="enviar_mensagem">
                        <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                        <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                        <input type="text" name="mensagem" class="form-control-custom" placeholder="Chat rápido..." style="font-size:0.8rem" required>
                        <button type="submit" class="btn-primary-custom" style="padding: 5px 12px;"><i class="fa fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <script>
            setTimeout(() => {
                const ac = document.getElementById('chat-admin-container');
                if(ac) ac.scrollTop = ac.scrollHeight;
            }, 300);
        </script>

    </div>

    <!-- COLUNA DIREITA: Descrição + Comentários -->
    <div class="col-lg-8">

        <!-- Metas de Incubação (Apenas para Admin/SuperAdmin) -->
        <?php if ($perfil !== 'utilizador'): 
            $faseActual = $projeto['fase'] ?? 'ideacao';
            $metasFase = array_filter($metasProjeto, fn($m) => $m['fase'] === $faseActual);
        ?>
        <div class="card-custom mb-4">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <div class="card-title-custom"><i class="fa fa-bullseye" style="color:var(--primary)"></i> Metas de Incubação (Fase: <?= strtoupper(str_replace('_', ' ', $faseActual)) ?>)</div>
                <a href="/incubadora_ispsn/app/views/admin/gestao_metas.php?projeto=<?= $idProjeto ?>&fase=<?= $faseActual ?>" class="btn btn-sm btn-outline-warning fw-bold px-3 py-1.5 rounded-3" style="font-size:0.75rem;"><i class="fa fa-sliders me-1"></i> Gerir Metas</a>
            </div>
            <div class="card-body-custom" style="padding: 20px;">
                <?php if (empty($metasFase)): ?>
                    <p class="text-muted small mb-0">Nenhuma meta configurada para esta fase no dicionário de metas.</p>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($metasFase as $m): 
                            $est = $m['estado'] ?? 'inactiva';
                            $badgeBg = '#cbd5e1; color:#475569';
                            $badgeLbl = 'Inativa';
                            if ($est === 'concluida') {
                                $badgeBg = '#d1fae5; color:#065f46';
                                $badgeLbl = 'Concluída';
                            } elseif ($est === 'activa') {
                                $badgeBg = '#fef3c7; color:#92400e';
                                $badgeLbl = 'Ativa';
                            } elseif ($est === 'em_avaliacao') {
                                $badgeBg = '#e0f2fe; color:#0369a1';
                                $badgeLbl = 'A avaliar';
                            } elseif ($est === 'reprovada') {
                                $badgeBg = '#fee2e2; color:#b91c1c';
                                $badgeLbl = 'Devolvida';
                            }
                        ?>
                            <div class="p-3 rounded border" style="background:var(--surface-2); border-color:var(--border-color);">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="fw-bold text-gray-800" style="font-size:0.88rem;"><?= $m['numero'] ?>. <?= htmlspecialchars($m['meta_titulo']) ?></div>
                                    <span class="badge" style="font-size:0.65rem; background:<?= $badgeBg ?>;"><?= $badgeLbl ?></span>
                                </div>
                                <p class="text-muted small mb-2" style="margin:0;"><?= htmlspecialchars($m['meta_descricao']) ?></p>
                                
                                <?php if (!empty($m['evidencia_em'])): ?>
                                    <div class="bg-white p-3 rounded border small mt-2 shadow-2xs">
                                        <div class="fw-bold text-secondary mb-1" style="font-size: 0.72rem;">Evidência Submetida em <?= date('d/m/Y H:i', strtotime($m['evidencia_em'])) ?>:</div>
                                        <div class="mb-2 text-slate-700" style="font-style: italic;">"<?= htmlspecialchars($m['evidencia_texto']) ?>"</div>
                                        <div class="d-flex gap-2">
                                            <?php if ($m['evidencia_link']): ?>
                                                <a href="<?= htmlspecialchars($m['evidencia_link']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary" style="font-size:0.7rem; padding: 3px 8px;"><i class="fa fa-link me-1"></i>Ver Link</a>
                                            <?php endif; ?>
                                            <?php if ($m['evidencia_path']): ?>
                                                <a href="/incubadora_ispsn/<?= htmlspecialchars($m['evidencia_path']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary" style="font-size:0.7rem; padding: 3px 8px;"><i class="fa fa-file me-1"></i>Ver Ficheiro</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Descrição -->
        <div class="card-custom mb-4">

            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-file-lines"></i> Descrição do Projecto</div>
            </div>
            <div class="card-body-custom">
                <p style="line-height:1.7;color:var(--text-primary)"><?= nl2br(htmlspecialchars($projeto['descricao'] ?? '—')) ?></p>

                <?php if ($projeto['problema']): ?>
                <div style="margin-top:20px;padding:14px 16px;background:#FEF9C3;border-radius:var(--radius);border-left:4px solid #F59E0B">
                    <div style="font-weight:700;font-size:0.82rem;color:#A16207;margin-bottom:6px;text-transform:uppercase">Problema Identificado</div>
                    <p style="margin:0;font-size:0.875rem"><?= nl2br(htmlspecialchars($projeto['problema'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($projeto['solucao']): ?>
                <div style="margin-top:14px;padding:14px 16px;background:#D1FAE5;border-radius:var(--radius);border-left:4px solid #10B981">
                    <div style="font-weight:700;font-size:0.82rem;color:#065F46;margin-bottom:6px;text-transform:uppercase">Solução Proposta</div>
                    <p style="margin:0;font-size:0.875rem"><?= nl2br(htmlspecialchars($projeto['solucao'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comentários -->
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">
                    <i class="fa fa-comments"></i>
                    Comentários e Feedback
                    <?php if (!empty($comentarios)): ?>
                    <span style="font-size:0.72rem;background:var(--primary);color:#fff;padding:2px 7px;border-radius:10px;margin-left:4px">
                        <?= count($comentarios) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body-custom">

                <?php if ($perfil !== 'utilizador'): ?>
                <!-- Adicionar comentário -->
                <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" style="margin-bottom:24px">
                    <input type="hidden" name="action" value="adicionar_comentario">
                    <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                    <input type="hidden" name="redirect" value="<?= $redirectBack ?>">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label-custom">Novo Comentário / Feedback</label>
                            <textarea name="comentario" class="form-control-custom" rows="3"
                                      placeholder="Adicione feedback ao estudante… (mínimo 5 caracteres)" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Fase Relacionada</label>
                            <select name="fase" class="form-control-custom" style="margin-bottom:10px">
                                <option value="em_analise">Em Análise</option>
                                <option value="em_andamento">Em Andamento</option>
                                <option value="concluido">Concluído</option>
                            </select>
                            <button type="submit" class="btn-primary-custom w-100">
                                <i class="fa fa-paper-plane"></i> Enviar Feedback
                            </button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>

                <!-- Lista de comentários -->
                <?php if (empty($comentarios)): ?>
                <div class="empty-state" style="padding:32px">
                    <div class="empty-state-icon"><i class="fa fa-comment-slash"></i></div>
                    <div class="empty-state-title">Sem comentários</div>
                    <div class="empty-state-text">Seja o primeiro a adicionar feedback a este projecto</div>
                </div>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:14px">
                <?php foreach ($comentarios as $c):
                    $isAdmin = in_array($c['perfil'], ['admin','superadmin','funcionario']);
                ?>
                <div style="padding:14px 16px;background:var(--surface-2);border-radius:var(--radius);
                            border-left:4px solid <?= $isAdmin ? 'var(--primary)' : 'var(--success)' ?>">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div style="display:flex;align-items:center;gap:8px">
                            <div style="width:28px;height:28px;border-radius:50%;
                                        background:<?= $isAdmin ? 'var(--primary)' : 'var(--success)' ?>;
                                        color:#fff;font-size:0.72rem;font-weight:700;
                                        display:flex;align-items:center;justify-content:center">
                                <?= strtoupper(substr($c['nome'],0,1)) ?>
                            </div>
                            <div>
                                <strong style="font-size:0.875rem"><?= htmlspecialchars($c['nome']) ?></strong>
                                <span style="font-size:0.72rem;margin-left:6px;padding:2px 7px;border-radius:10px;
                                             background:<?= $isAdmin ? '#E0E7FF' : '#D1FAE5' ?>;
                                             color:<?= $isAdmin ? 'var(--primary)' : 'var(--success)' ?>;font-weight:600">
                                    <?= ucfirst($c['perfil']) ?>
                                </span>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span class="badge-estado badge-<?= $c['fase'] ?>"><?= ucfirst(str_replace('_',' ',$c['fase'])) ?></span>
                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?></small>
                        </div>
                    </div>
                    <p style="margin:0;font-size:0.875rem;line-height:1.6"><?= nl2br(htmlspecialchars($c['comentario'])) ?></p>
                </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Repositório de Documentos -->
        <div class="card-custom mt-4">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-folder-open"></i> Repositório de Documentos</div>
            </div>
            <div class="card-body-custom">
                <?php if (empty($documentos)): ?>
                    <div class="text-center py-4">
                        <i class="fa fa-cloud-arrow-up text-muted mb-2" style="font-size:2rem"></i>
                        <p class="text-muted small">Nenhum documento centralizado ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush mb-3">
                    <?php foreach ($documentos as $d): ?>
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center" style="background:transparent;border-color:var(--border-color)">
                            <div>
                                <div class="fw-bold" style="font-size:0.875rem"><?= htmlspecialchars($d['titulo']) ?></div>
                                <small class="text-muted"><?= $d['tipo'] ?> · Por <?= htmlspecialchars($d['quem_submeteu']) ?></small>
                            </div>
                            <a href="/incubadora_ispsn/<?= $d['path'] ?>" target="_blank" class="btn-ghost btn-sm text-primary">
                                <i class="fa fa-download"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($perfil !== 'mentor'): ?>
                <button class="btn-primary-custom w-100" style="font-size:0.82rem" data-bs-toggle="modal" data-bs-target="#modalDoc">
                    <i class="fa fa-upload me-1"></i> Carregar Documento
                </button>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ── MODAL: Adicionar Membro ──────────── -->
<?php if ($podeGerirMembros): ?>
<div class="modal fade" id="modalAddMembro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
                <input type="hidden" name="action" value="adicionar_membro">
                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= $redirectBack ?>">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-user-plus me-2"></i>Convidar Membro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">E-mail do Utilizador *</label>
                        <input type="email" name="email" class="form-control-custom" required placeholder="email@exemplo.com">
                    </div>
                    <div>
                        <label class="form-label-custom">Papel / Função</label>
                        <select name="papel" class="form-control-custom">
                            <option value="Cofundador">Cofundador</option>
                            <option value="Desenvolvedor">Desenvolvedor</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Membro">Membro</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── MODAL: Upload Documento ──────────── -->
<div class="modal fade" id="modalDoc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_documento">
                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= $redirectBack ?>">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-upload me-2"></i>Carregar Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Título do Documento *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Pitch Deck 2026, Business Canvas">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Tipo</label>
                        <select name="tipo" class="form-control-custom">
                            <option value="Pitch">Pitch / Apresentação</option>
                            <option value="Plano de Negócio">Plano de Negócio</option>
                            <option value="Contrato">Contrato / Documento Legal</option>
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
                    <button type="submit" class="btn-primary-custom">Fazer Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── MODAL: Convidar Mentor ──────────────── -->
<?php if ($projeto['estado'] === 'incubado' && in_array($perfil, ['admin','superadmin'])): ?>
<div class="modal fade" id="modalConvidarMentorProjeto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/mentor_action.php">
                <input type="hidden" name="action" value="atribuir_mentor">
                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-user-tie me-2"></i>Convidar Mentor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Seleccione o Mentor</label>
                        <select name="id_mentor" class="form-control-custom" required>
                            <option value="">Escolher mentor...</option>
                            <?php 
                            $mentores = $mysqli->query("SELECT m.id, u.nome, m.especialidade FROM mentores m JOIN usuarios u ON u.id = m.id_usuario WHERE u.activo = 1");
                            while($m = $mentores->fetch_assoc()):
                            ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?> (<?= htmlspecialchars($m['especialidade']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Atribuir Mentor</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── MODAL: Mudar Estado ───────────────── -->
<?php if (in_array($perfil, ['admin','superadmin'])): ?>
<div class="modal fade" id="modalEstado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
                <input type="hidden" name="action" value="mudar_estado">
                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= $redirectBack ?>">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-sliders me-2"></i>Mudar Estado do Projecto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Novo Estado</label>
                        <select name="estado" class="form-control-custom" required>
                            <option value="submetido"         <?= $projeto['estado']==='submetido'        ?'selected':'' ?>>Submetido (Triagem)</option>
                            <option value="em_avaliacao"      <?= $projeto['estado']==='em_avaliacao'     ?'selected':'' ?>>Em Avaliação 🔍</option>
                            <option value="aprovado"          <?= $projeto['estado']==='aprovado'         ?'selected':'' ?>>Aprovar para Incubação ✓</option>
                            <option value="rejeitado"         <?= $projeto['estado']==='rejeitado'        ?'selected':'' ?>>Rejeitar Projeto ✗</option>
                            <option value="incubado"          <?= $projeto['estado']==='incubado'         ?'selected':'' ?>>Incubado 🚀</option>
                            <option value="fundo_investimento" <?= $projeto['estado']==='fundo_investimento'?'selected':'' ?>>Pronto p/ Investimento 💰</option>
                            <option value="concluido"         <?= $projeto['estado']==='concluido'        ?'selected':'' ?>>Concluir / Graduar ✨</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-custom">Motivo (opcional, especialmente para rejeição)</label>
                        <textarea name="motivo_rejeicao" class="form-control-custom" rows="3"
                                  placeholder="Explique ao estudante o motivo da decisão…"><?= htmlspecialchars($projeto['motivo_rejeicao'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-check"></i> Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── MODAL: Avaliação Formal ──────────── -->
<?php if (in_array($perfil, ['admin','superadmin','mentor','funcionario'])): ?>
<div class="modal fade" id="modalAvaliar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
                <input type="hidden" name="action" value="avaliar_projeto">
                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= $redirectBack ?>">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-star me-2" style="color:#F59E0B"></i>Ficha de Avaliação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="row g-3 mb-3">
                        <?php
                        $criteria = [
                            'nota_inovacao'         => ['label'=>'🔬 Inovação (20%)', 'icon'=>'fa-lightbulb',   'val'=>$avaliacao['nota_inovacao'] ?? 5],
                            'nota_sustentabilidade' => ['label'=>'💰 Autossustentabilidade (15%)', 'icon'=>'fa-wallet', 'val'=>$avaliacao['nota_sustentabilidade'] ?? 5],
                            'nota_escalabilidade'   => ['label'=>'📈 Escalabilidade (10%)', 'icon'=>'fa-arrow-up-right-dots', 'val'=>$avaliacao['nota_escalabilidade'] ?? 5],
                            'nota_impacto'          => ['label'=>'🌍 Impacto Social (15%)', 'icon'=>'fa-globe', 'val'=>$avaliacao['nota_impacto'] ?? 5],
                            'nota_viabilidade'      => ['label'=>'⚙️ Viabilidade Técnica (10%)', 'icon'=>'fa-gears', 'val'=>$avaliacao['nota_viabilidade'] ?? 5],
                            'nota_equipa'           => ['label'=>'👥 Qualidade da Equipa (10%)', 'icon'=>'fa-users', 'val'=>$avaliacao['nota_equipa'] ?? 5],
                            'nota_mercado'          => ['label'=>'📊 Viabilidade de Mercado (10%)', 'icon'=>'fa-chart-simple', 'val'=>$avaliacao['nota_mercado'] ?? 5],
                            'nota_proposta'         => ['label'=>'📝 Qualidade da Proposta (10%)', 'icon'=>'fa-file-signature', 'val'=>$avaliacao['nota_proposta'] ?? 5],
                        ];
                        foreach ($criteria as $field => $meta):
                        ?>
                        <div class="col-md-6">
                            <label class="form-label-custom">
                                <?= $meta['label'] ?>
                            </label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="range" name="<?= $field ?>" min="0" max="10" step="1"
                                       value="<?= $meta['val'] ?>"
                                       class="flex-fill" id="range_<?= $field ?>"
                                       oninput="document.getElementById('val_<?= $field ?>').textContent=this.value"
                                       style="accent-color:var(--primary)">
                                <span id="val_<?= $field ?>" style="min-width:24px;font-weight:800;color:var(--primary);font-size:1.1rem">
                                    <?= $meta['val'] ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Observações</label>
                        <textarea name="observacoes" class="form-control-custom" rows="3"
                                  placeholder="Comentários adicionais sobre a avaliação…"><?= htmlspecialchars($avaliacao['observacoes'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="form-label-custom">Decisão</label>
                        <select name="decisao" class="form-control-custom">
                            <option value="pendente"   <?= ($avaliacao['decisao'] ?? '')==='pendente'   ?'selected':'' ?>>Pendente (aguarda mais análise)</option>
                            <option value="aprovado"   <?= ($avaliacao['decisao'] ?? '')==='aprovado'   ?'selected':'' ?>>Aprovado ✓</option>
                            <option value="em_revisao" <?= ($avaliacao['decisao'] ?? '')==='em_revisao' ?'selected':'' ?>>Em Revisão ↩</option>
                            <option value="rejeitado"  <?= ($avaliacao['decisao'] ?? '')==='rejeitado'  ?'selected':'' ?>>Rejeitado ✗</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-star"></i> Guardar Avaliação</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── MODAL: Adicionar Membro Equipa ──────────── -->
<?php if (in_array($perfil, ['admin','superadmin'])): ?>
<div class="modal fade" id="modalAdicionarMembro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
                <input type="hidden" name="action" value="adicionar_membro">
                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-user-plus me-2"></i>Nova Membro de Equipa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">E-mail do Usuário</label>
                        <input type="email" name="email" class="form-control-custom" required placeholder="pesquisar por email...">
                    </div>
                    <div>
                        <label class="form-label-custom">Cargo/Papel</label>
                        <input type="text" name="papel" class="form-control-custom" placeholder="Ex: CTO, Marketing, etc">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Adicionar Membro</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if (!empty($mediaAval['n']) && $mediaAval['n'] > 0): ?>
    const ctxRadar = document.getElementById('radarChartAvaliacao');
    if (ctxRadar) {
        new Chart(ctxRadar, {
            type: 'radar',
            data: {
                labels: [
                    'Inovação', 
                    'Autossustentabilidade', 
                    'Escalabilidade', 
                    'Impacto Social', 
                    'Viabilidade Técnica', 
                    'Qualidade Equipa', 
                    'Viabilidade Mercado', 
                    'Qualidade Proposta'
                ],
                datasets: [{
                    label: 'Média (0-10)',
                    data: [
                        <?= number_format($mediaCrit['avg_inovacao'] ?? 0, 1) ?>,
                        <?= number_format($mediaCrit['avg_sustentabilidade'] ?? 0, 1) ?>,
                        <?= number_format($mediaCrit['avg_escalabilidade'] ?? 0, 1) ?>,
                        <?= number_format($mediaCrit['avg_impacto'] ?? 0, 1) ?>,
                        <?= number_format($mediaCrit['avg_viabilidade'] ?? 0, 1) ?>,
                        <?= number_format($mediaCrit['avg_equipa'] ?? 0, 1) ?>,
                        <?= number_format($mediaCrit['avg_mercado'] ?? 0, 1) ?>,
                        <?= number_format($mediaCrit['avg_proposta'] ?? 0, 1) ?>
                    ],
                    backgroundColor: 'rgba(217, 119, 6, 0.2)',
                    borderColor: '#D97706',
                    borderWidth: 2,
                    pointBackgroundColor: '#D97706',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#D97706'
                }]
            },
            options: {
                scales: {
                    r: {
                        angleLines: { display: true },
                        suggestedMin: 0,
                        suggestedMax: 10,
                        ticks: { stepSize: 2, display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>

