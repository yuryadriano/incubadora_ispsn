<?php
// app/views/dashboard/utilizador.php
// Painel Geral do Empreendedor — Minimalista (Máx. 2 Blocos: Estado da Startup + Próxima Ação)

require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$realPerfil     = $_SESSION['usuario_perfil'] ?? 'utilizador';
$isAdminOrSuper = in_array($realPerfil, ['admin', 'superadmin'], true);

if ($isAdminOrSuper && !empty($_GET['id_usuario'])) {
    $idUsuario = (int)$_GET['id_usuario'];
} elseif (!$isAdminOrSuper) {
    $idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
} else {
    $resFirst = $mysqli->query("
        SELECT u.id
        FROM usuarios u
        LEFT JOIN projetos p ON p.criado_por = u.id
        WHERE u.perfil = 'utilizador' AND u.activo = 1
        GROUP BY u.id
        ORDER BY COUNT(p.id) DESC, u.id ASC
        LIMIT 1
    ");
    $rowFirst  = $resFirst ? $resFirst->fetch_assoc() : null;
    $idUsuario = $rowFirst ? (int)$rowFirst['id'] : (int)($_SESSION['usuario_id'] ?? 0);
}

$stmtUser = $mysqli->prepare("SELECT id, nome, email, tipo_utilizador FROM usuarios WHERE id = ? LIMIT 1");
$stmtUser->bind_param('i', $idUsuario);
$stmtUser->execute();
$userInfo = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$nomeEstudante = $userInfo['nome'] ?? ($_SESSION['usuario_nome'] ?? 'Empreendedor');
if ($isAdminOrSuper) {
    $nomeUsuario = $nomeEstudante;
    $perfil      = 'utilizador';
}

$tituloPagina = 'Painel Geral';
$paginaActiva = 'dashboard';

$userParam = $isAdminOrSuper ? ('?id_usuario=' . (int)$idUsuario) : '';
$userAmp   = $isAdminOrSuper ? ('&id_usuario=' . (int)$idUsuario) : '';

// 1. Listar projetos do estudante
$stmtLista = $mysqli->prepare("
    SELECT DISTINCT p.id, p.titulo, p.estado, p.fase, p.pontos, p.criado_em
    FROM projetos p
    LEFT JOIN membros_projeto mp ON mp.id_projeto = p.id
    WHERE p.criado_por = ? OR mp.id_usuario = ?
    ORDER BY p.criado_em DESC
");
$stmtLista->bind_param('ii', $idUsuario, $idUsuario);
$stmtLista->execute();
$listaProjetos = $stmtLista->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLista->close();

$idProjSel = isset($_GET['id_projeto']) ? (int)$_GET['id_projeto'] : 0;

// 2. Carregar projeto ativo
$meuProjeto = null;
if ($idProjSel > 0) {
    $stmtP = $mysqli->prepare("
        SELECT p.*
        FROM projetos p
        LEFT JOIN membros_projeto mp ON mp.id_projeto = p.id
        WHERE p.id = ? AND (p.criado_por = ? OR mp.id_usuario = ?)
        LIMIT 1
    ");
    $stmtP->bind_param('iii', $idProjSel, $idUsuario, $idUsuario);
} else {
    $stmtP = $mysqli->prepare("
        SELECT p.*
        FROM projetos p
        LEFT JOIN membros_projeto mp ON mp.id_projeto = p.id
        WHERE p.criado_por = ? OR mp.id_usuario = ?
        ORDER BY p.criado_em DESC
        LIMIT 1
    ");
    $stmtP->bind_param('ii', $idUsuario, $idUsuario);
}
$stmtP->execute();
$meuProjeto = $stmtP->get_result()->fetch_assoc();
$stmtP->close();

$mentorAtivo      = null;
$metasFase        = [];
$tarefasPendentes = [];
$numAvalConcl     = 0;

if ($meuProjeto) {
    $idP       = (int)$meuProjeto['id'];
    $faseAtual = (string)($meuProjeto['fase'] ?? 'ideacao');

    // Mentor atribuído
    $stmtMent = $mysqli->prepare("
        SELECT u.nome AS mentor_nome, mt.especialidade
        FROM mentorias m
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u ON u.id = mt.id_usuario
        WHERE m.id_projeto = ? AND m.estado = 'activa'
        ORDER BY m.id DESC
        LIMIT 1
    ");
    if ($stmtMent) {
        $stmtMent->bind_param('i', $idP);
        $stmtMent->execute();
        $mentorAtivo = $stmtMent->get_result()->fetch_assoc();
        $stmtMent->close();
    }

    // Progresso da avaliação (3 avaliadores)
    $stmtAv = $mysqli->prepare("
        SELECT COALESCE(SUM(CASE WHEN estado = 'concluido' THEN 1 ELSE 0 END), 0) AS total_concluidos
        FROM avaliacoes_atribuicao
        WHERE projeto_id = ?
    ");
    if ($stmtAv) {
        $stmtAv->bind_param('i', $idP);
        $stmtAv->execute();
        $avRow        = $stmtAv->get_result()->fetch_assoc();
        $numAvalConcl = (int)($avRow['total_concluidos'] ?? 0);
        $stmtAv->close();
    }

    // Metas da fase atual
    $stmtM = $mysqli->prepare("
        SELECT mp.*, mpd.numero, mpd.titulo AS meta_titulo, mpd.descricao AS meta_descricao
        FROM metas_projeto mp
        JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
        WHERE mp.id_projeto = ? AND mpd.fase = ?
        ORDER BY mpd.numero ASC
    ");
    if ($stmtM) {
        $stmtM->bind_param('is', $idP, $faseAtual);
        $stmtM->execute();
        $metasFase = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtM->close();
    }

    // Tarefas em aberto atribuídas pelo mentor
    $stmtT = $mysqli->prepare("
        SELECT id, titulo, prioridade, data_limite
        FROM tarefas
        WHERE id_projeto = ? AND COALESCE(validada_mentor, 0) = 0
        ORDER BY data_limite ASC
        LIMIT 3
    ");
    if ($stmtT) {
        $stmtT->bind_param('i', $idP);
        $stmtT->execute();
        $tarefasPendentes = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtT->close();
    }
}

$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
.pipeline-inline {
    display: flex; align-items: center; justify-content: space-between;
    padding-top: 20px; margin-top: 20px; border-top: 1px solid var(--border);
}
.pipeline-step {
    display: flex; flex-direction: column; align-items: center; z-index: 2; flex: 1; text-decoration: none;
}
.step-icon {
    width: 38px; height: 38px; border-radius: 50%;
    background: var(--surface-2); color: var(--text-muted);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; border: 2px solid var(--surface); box-shadow: 0 0 0 2px var(--border);
}
.step-label { font-size: 0.74rem; font-weight: 600; margin-top: 6px; color: var(--text-muted); }
.pipeline-line { flex: 1; height: 3px; background: var(--border); margin-top: -20px; z-index: 1; }
.pipeline-step.active .step-icon { background: var(--primary); color: #fff; box-shadow: 0 0 0 3px rgba(217,119,6,0.22); }
.pipeline-step.active .step-label { color: var(--primary); font-weight: 700; }
.pipeline-step.completed .step-icon { background: #10B981; color: #fff; box-shadow: 0 0 0 2px #10B981; }
.pipeline-step.completed .step-label { color: #10B981; }
.pipeline-line.completed { background: #10B981; }
</style>

<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<!-- CABEÇALHO SIMPLES -->
<div class="page-header">
    <div>
        <div class="page-header-title">Olá, <?= htmlspecialchars(explode(' ', $nomeEstudante)[0]) ?></div>
        <div class="page-header-sub">Acompanhe o estado atual e a próxima ação da sua startup.</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (count($listaProjetos) > 1 && $meuProjeto): ?>
            <form method="GET" class="m-0">
                <?php if ($isAdminOrSuper): ?>
                    <input type="hidden" name="id_usuario" value="<?= (int)$idUsuario ?>">
                <?php endif; ?>
                <select name="id_projeto" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($listaProjetos as $lp): ?>
                        <option value="<?= (int)$lp['id'] ?>" <?= ((int)$lp['id'] === (int)$meuProjeto['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lp['titulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
            <i class="fa fa-plus"></i> Submeter Nova Ideia
        </button>
    </div>
</div>

<?php if (!$meuProjeto): ?>
    <!-- BLOCO ÚNICO QUANDO NÃO HÁ PROJETO -->
    <div class="card-custom">
        <div class="empty-state py-5">
            <div class="empty-state-icon"><i class="fa fa-rocket" style="color:var(--primary)"></i></div>
            <div class="empty-state-title">Ainda não tem nenhuma startup submetida</div>
            <div class="empty-state-text mb-4">Submeta a sua ideia para iniciar o processo na Incubadora ISPSN.</div>
            <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
                <i class="fa fa-plus"></i> Criar Minha Startup
            </button>
        </div>
    </div>

<?php else:
    $idP        = (int)$meuProjeto['id'];
    $estadoProj = (string)($meuProjeto['estado'] ?? 'submetido');
    $faseAtual  = (string)($meuProjeto['fase'] ?? 'ideacao');
    $pontosSP   = (int)($meuProjeto['pontos'] ?? 0);
    $emTriagem  = in_array($estadoProj, ['submetido', 'em_avaliacao', 'em_revisao', 'rejeitado'], true);

    $fasesLabels = [
        'ideacao'   => ['label' => 'Ideação',   'icon' => 'lightbulb'],
        'validacao' => ['label' => 'Validação', 'icon' => 'vial'],
        'mvp'       => ['label' => 'MVP',       'icon' => 'cube'],
        'tracao'    => ['label' => 'Tração',    'icon' => 'chart-line'],
        'mercado'   => ['label' => 'Mercado',   'icon' => 'shop'],
    ];
    $faseInfo = $fasesLabels[$faseAtual] ?? $fasesLabels['ideacao'];

    $urlProposta = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=' . $idP . '&sec=proposta' . $userAmp;
    $urlMetas    = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=' . $idP . '&sec=metas'    . $userAmp;
    $urlMentoria = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=' . $idP . '&sec=mentoria' . $userAmp;
?>

    <!-- BLOCO 1: TOPO COM NOME, ESTADO E FASE DA STARTUP -->
    <div class="card-custom mb-4">
        <div class="card-body-custom p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge-estado badge-<?= htmlspecialchars($estadoProj) ?>">
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $estadoProj))) ?>
                        </span>
                        <span class="text-muted" style="font-size:0.8rem;">
                            Fase: <strong><?= htmlspecialchars($faseInfo['label']) ?></strong> · <?= $pontosSP ?> SP
                            <?php if ($mentorAtivo): ?>
                                · Mentor: <strong><?= htmlspecialchars($mentorAtivo['mentor_nome']) ?></strong>
                            <?php endif; ?>
                        </span>
                    </div>
                    <h2 class="fw-bold mb-1" style="font-size:1.35rem; color:var(--text-primary);">
                        <?= htmlspecialchars($meuProjeto['titulo']) ?>
                    </h2>
                    <p class="text-muted mb-0" style="font-size:0.86rem; max-width:740px;">
                        <?= htmlspecialchars(mb_strimwidth((string)($meuProjeto['descricao'] ?? ''), 0, 180, '...')) ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= htmlspecialchars($urlProposta) ?>" class="btn-ghost" style="font-size:0.82rem;">
                        <i class="fa fa-file-lines"></i> Ver Ficha
                    </a>
                    <a href="<?= htmlspecialchars($urlMentoria) ?>" class="btn-primary-custom" style="font-size:0.82rem;">
                        <i class="fa fa-comments"></i> Abrir Chat
                    </a>
                </div>
            </div>

            <!-- Linha limpa das 5 fases integrada no Bloco 1 -->
            <?php
            $fasesKeys = array_keys($fasesLabels);
            $currentFaseIdx = array_search($faseAtual, $fasesKeys, true);
            if ($currentFaseIdx === false) $currentFaseIdx = 0;
            ?>
            <div class="pipeline-inline">
                <?php foreach ($fasesKeys as $idx => $fK):
                    $fItem  = $fasesLabels[$fK];
                    $status = ($idx < $currentFaseIdx) ? 'completed' : (($idx === $currentFaseIdx) ? 'active' : 'pending');
                ?>
                    <a href="<?= htmlspecialchars($urlMetas) ?>" class="pipeline-step <?= $status ?>">
                        <div class="step-icon"><i class="fa fa-<?= $fItem['icon'] ?>"></i></div>
                        <div class="step-label"><?= $fItem['label'] ?></div>
                    </a>
                    <?php if ($idx < 4): ?>
                        <div class="pipeline-line <?= ($idx < $currentFaseIdx) ? 'completed' : '' ?>"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- BLOCO 2: ÚNICO BLOCO DE "PRÓXIMA AÇÃO" -->
    <div class="card-custom">
        <div class="card-header-custom">
            <div class="card-title-custom">
                <i class="fa fa-bullseye" style="color:var(--primary)"></i>
                Próxima Ação — <?= htmlspecialchars($faseInfo['label']) ?>
            </div>
            <a href="<?= htmlspecialchars($urlMetas) ?>" class="btn-ghost" style="padding:4px 12px; font-size:0.78rem;">
                Ver todas as metas &rarr;
            </a>
        </div>
        <div class="card-body-custom p-4">
            <?php if ($emTriagem && empty($metasFase)): ?>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="fw-bold mb-1" style="font-size:0.95rem;">
                            Proposta em Avaliação pela Comissão Técnica (<?= $numAvalConcl ?>/3 pareceres emitidos)
                        </div>
                        <div class="text-muted" style="font-size:0.84rem;">
                            Enquanto aguarda o resultado final, pode rever as metas previstas da fase de <strong><?= htmlspecialchars($faseInfo['label']) ?></strong> ou falar no Chat.
                        </div>
                    </div>
                    <a href="<?= htmlspecialchars($urlMetas) ?>" class="btn-primary-custom" style="font-size:0.82rem;">
                        <i class="fa fa-bullseye"></i> Consultar Metas da Fase
                    </a>
                </div>
            <?php elseif (!empty($metasFase) || !empty($tarefasPendentes)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach (array_slice($metasFase, 0, 3) as $mf):
                        $estM = (string)($mf['estado'] ?? 'inactiva');
                        $isConcl = ($estM === 'concluida');
                    ?>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pb-3 border-bottom" style="border-color:var(--border) !important;">
                            <div>
                                <div class="fw-bold" style="font-size:0.9rem;">
                                    #<?= (int)$mf['numero'] ?>. <?= htmlspecialchars($mf['meta_titulo']) ?>
                                </div>
                                <div class="text-muted" style="font-size:0.8rem;">
                                    <?= htmlspecialchars($mf['meta_descricao']) ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($isConcl): ?>
                                    <span class="badge-estado badge-aprovado">Validada ✓</span>
                                <?php elseif ($estM === 'em_avaliacao'): ?>
                                    <span class="badge-estado badge-avaliacao">Em análise pelo mentor</span>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($urlMetas) ?>" class="btn-primary-custom" style="padding:6px 14px; font-size:0.78rem;">
                                        <i class="fa fa-upload"></i> Enviar Evidência
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($tarefasPendentes)): ?>
                        <div class="pt-1">
                            <div class="text-muted fw-bold text-uppercase mb-2" style="font-size:0.72rem; letter-spacing:0.5px;">
                                <i class="fa fa-list-check me-1" style="color:var(--primary)"></i> Tarefas Atribuídas pelo Mentor
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($tarefasPendentes as $t): ?>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color:var(--border) !important;">
                                        <div>
                                            <div class="fw-bold" style="font-size:0.88rem;"><?= htmlspecialchars($t['titulo']) ?></div>
                                            <small class="text-muted">Prazo: <?= !empty($t['data_limite']) ? date('d/m/Y', strtotime($t['data_limite'])) : 'Sem prazo' ?></small>
                                        </div>
                                        <a href="<?= htmlspecialchars($urlMetas) ?>&aba=tarefas" class="btn-primary-custom" style="padding:5px 12px; font-size:0.76rem;">
                                            Entregar Tarefa
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="text-muted" style="font-size:0.86rem;">
                        Não existem pendências imediatas. Aceda a <strong>Metas &amp; Evidências</strong> no menu lateral para consultar o plano da fase.
                    </div>
                    <a href="<?= htmlspecialchars($urlMetas) ?>" class="btn-primary-custom" style="font-size:0.82rem;">
                        Abrir Metas &amp; Evidências
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- MODAL SUBMETER NOVA PROPOSTA -->
<div class="modal fade" id="modalNovoProjeto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="criar_projeto">
                <input type="hidden" name="id_usuario_alvo" value="<?= (int)$idUsuario ?>">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php<?= $userParam ?>">

                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold m-0"><i class="fa fa-rocket me-2" style="color:var(--primary);"></i>Submeter Nova Proposta de Startup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label-custom">Nome da Startup / Projeto *</label>
                            <input type="text" name="titulo" class="form-control-custom" placeholder="Ex: AgroSmart Huambo" required minlength="5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Tipologia *</label>
                            <select name="tipo" class="form-select form-control-custom" required>
                                <option value="startup_tecnologica">Startup Tecnológica</option>
                                <option value="negocio_tradicional">Negócio Tradicional / Inovação</option>
                                <option value="impacto_social">Impacto Social / Ambiental</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Área Temática *</label>
                            <select name="area" class="form-select form-control-custom" required>
                                <option value="tecnologia">Tecnologia &amp; Sistemas</option>
                                <option value="saude">Saúde &amp; Bem-Estar</option>
                                <option value="educacao">Educação</option>
                                <option value="agro">Agronegócio</option>
                                <option value="financas">Finanças / Fintech</option>
                                <option value="outro">Outra Área</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Resumo Executivo (mín. 20 caracteres) *</label>
                            <textarea name="descricao" class="form-control-custom" rows="3" required minlength="20" placeholder="Descreva a proposta de valor da startup..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Problema que Resolve</label>
                            <textarea name="problema" class="form-control-custom" rows="2" placeholder="Qual é a dor real identificada?"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Solução Proposta</label>
                            <textarea name="solucao" class="form-control-custom" rows="2" placeholder="Como a sua ideia resolve esse problema?"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Pitch Deck (PDF, PPT, PPTX ou ZIP — opcional)</label>
                            <input type="file" name="pitch_ficheiro" class="form-control form-control-custom" accept=".pdf,.ppt,.pptx,.zip">
                        </div>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-paper-plane me-1"></i> Submeter para Avaliação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
