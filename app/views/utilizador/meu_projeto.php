<?php
// app/views/utilizador/meu_projeto.php
// Páginas Dedicadas do Menu do Estudante:
// - ?sec=proposta   (Minha Startup & Equipa)
// - ?sec=metas      (Metas & Evidências)
// - ?sec=mentoria   (Mentoria & Chat)
// - ?sec=documentos (Doc Hub / Ficheiros & Termo)

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

obrigarLogin();

$realPerfil     = $_SESSION['usuario_perfil'] ?? 'utilizador';
$isAdminOrSuper = in_array($realPerfil, ['admin', 'superadmin'], true);

if ($isAdminOrSuper) {
    $idUsuario = (int)($_GET['id_usuario'] ?? 0);
    if ($idUsuario <= 0) {
        $resFallback = $mysqli->query("
            SELECT u.id, u.nome
            FROM usuarios u
            JOIN projetos p ON p.criado_por = u.id
            ORDER BY p.criado_em DESC LIMIT 1
        ");
        $fallback  = $resFallback ? $resFallback->fetch_assoc() : null;
        $idUsuario = $fallback ? (int)$fallback['id'] : 0;
    }

    $stmtU = $mysqli->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1");
    if ($stmtU) {
        $stmtU->bind_param('i', $idUsuario);
        $stmtU->execute();
        $uRow = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();
        $nome = $uRow['nome'] ?? 'Estudante';
    } else {
        $nome = 'Estudante';
    }
    $nomeUsuario = $nome;
    $perfil      = 'utilizador';
} else {
    $idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
    $nome      = $_SESSION['usuario_nome'] ?? 'Utilizador';
}

$userQuerySuffix = $isAdminOrSuper ? ('&id_usuario=' . (int)$idUsuario) : '';
$userParamOnly   = $isAdminOrSuper ? ('?id_usuario=' . (int)$idUsuario) : '';

// Determinar secção ativa do menu lateral
$sec = $_GET['sec'] ?? ($_GET['tab'] ?? 'proposta');
if ($sec === 'comunicacao') $sec = 'mentoria';
if ($sec === 'execucao')    $sec = 'metas';
if (!in_array($sec, ['proposta', 'metas', 'mentoria', 'documentos'], true)) {
    $sec = 'proposta';
}

$mapaPaginas = [
    'proposta'   => ['id' => 'projetos',   'titulo' => 'Minha Startup'],
    'metas'      => ['id' => 'metas',      'titulo' => 'Metas & Evidências'],
    'mentoria'   => ['id' => 'mentoria',   'titulo' => 'Mentoria & Chat'],
    'documentos' => ['id' => 'documentos', 'titulo' => 'Doc Hub / Ficheiros'],
];
$paginaActiva = $mapaPaginas[$sec]['id'];
$tituloPagina = $mapaPaginas[$sec]['titulo'];

// ── Buscar projeto do estudante ───────────────────────────────
$idProjGet = (int)($_GET['id'] ?? 0);
if ($idProjGet > 0) {
    $stmtP = $mysqli->prepare("
        SELECT p.*, u.nome AS autor_nome, u.email AS autor_email
        FROM projetos p
        JOIN usuarios u ON u.id = p.criado_por
        LEFT JOIN membros_projeto mp ON mp.id_projeto = p.id
        WHERE p.id = ? AND (p.criado_por = ? OR mp.id_usuario = ?)
        LIMIT 1
    ");
    $stmtP->bind_param('iii', $idProjGet, $idUsuario, $idUsuario);
} else {
    $stmtP = $mysqli->prepare("
        SELECT p.*, u.nome AS autor_nome, u.email AS autor_email
        FROM projetos p
        JOIN usuarios u ON u.id = p.criado_por
        LEFT JOIN membros_projeto mp ON mp.id_projeto = p.id
        WHERE p.criado_por = ? OR mp.id_usuario = ?
        ORDER BY p.criado_em DESC LIMIT 1
    ");
    $stmtP->bind_param('ii', $idUsuario, $idUsuario);
}
$stmtP->execute();
$projeto = $stmtP->get_result()->fetch_assoc();
$stmtP->close();

$labelsEstado = [
    'submetido'          => 'Submetido (Em Triagem)',
    'em_avaliacao'       => 'Em Avaliação Técnica',
    'em_revisao'         => 'Em Revisão',
    'aprovado'           => 'Aprovado para Incubação',
    'rejeitado'          => 'Rejeitado',
    'incubado'           => 'Em Incubação',
    'fundo_investimento' => 'Pronto p/ Investimento',
    'concluido'          => 'Graduado',
];

$fasesDisponiveis = [
    'ideacao'   => ['emoji' => '💡', 'label' => 'Ideação',   'icon' => 'lightbulb'],
    'validacao' => ['emoji' => '🔬', 'label' => 'Validação', 'icon' => 'vial'],
    'mvp'       => ['emoji' => '📦', 'label' => 'MVP',       'icon' => 'cube'],
    'tracao'    => ['emoji' => '📈', 'label' => 'Tração',    'icon' => 'chart-line'],
    'mercado'   => ['emoji' => '🛒', 'label' => 'Mercado',   'icon' => 'shop'],
];

$labelsTipo = [
    'startup_tecnologica' => 'Startup Tecnológica',
    'negocio_tradicional' => 'Negócio Tradicional',
    'impacto_social'      => 'Impacto Social',
    'outro'               => 'Outro',
];

$membros          = [];
$mentorAtivo      = null;
$termoProjeto     = null;
$metasProjeto     = [];
$metasPadraoDict  = [];
$tarefasProjeto   = [];
$documentos       = [];
$comentarios      = [];
$mensagensChat    = [];
$sessoesMentoria  = [];
$reunioesAgend    = [];
$avaliacoesMentor = [];
$kpis             = [];
$financiamentos   = [];

if ($projeto) {
    $idProjeto  = (int)$projeto['id'];
    $estadoProj = (string)($projeto['estado'] ?? '');
    $faseActual = (string)($projeto['fase'] ?? 'ideacao');

    // 1. Equipa
    $stmtEq = $mysqli->prepare("
        SELECT u.nome, u.email, mp.papel, mp.id_usuario
        FROM membros_projeto mp
        JOIN usuarios u ON u.id = mp.id_usuario
        WHERE mp.id_projeto = ?
    ");
    if ($stmtEq) {
        $stmtEq->bind_param('i', $idProjeto);
        $stmtEq->execute();
        $membros = $stmtEq->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtEq->close();
    }

    // 2. Mentor atribuído
    $stmtMent = $mysqli->prepare("
        SELECT m.id AS id_mentoria, u.nome AS mentor_nome, u.email AS mentor_email, mt.especialidade
        FROM mentorias m
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u ON u.id = mt.id_usuario
        WHERE m.id_projeto = ? AND m.estado = 'activa'
        ORDER BY m.id DESC LIMIT 1
    ");
    if ($stmtMent) {
        $stmtMent->bind_param('i', $idProjeto);
        $stmtMent->execute();
        $mentorAtivo = $stmtMent->get_result()->fetch_assoc();
        $stmtMent->close();
    }

    // 3. Termo de incubação
    $stmtTermo = $mysqli->prepare("
        SELECT *
        FROM termos_incubacao
        WHERE id_projeto = ? AND estado IN ('gerado', 'pendente_assinatura', 'assinado')
        ORDER BY criado_em DESC LIMIT 1
    ");
    if ($stmtTermo) {
        $stmtTermo->bind_param('i', $idProjeto);
        $stmtTermo->execute();
        $termoProjeto = $stmtTermo->get_result()->fetch_assoc();
        $stmtTermo->close();
    }

    // 4. Todas as Metas do Projeto (idêntico a mentor/projeto_detalhe.php)
    $stmtM = $mysqli->prepare("
        SELECT mp.*, mpd.titulo AS meta_titulo, mpd.descricao AS meta_descricao,
               mpd.evidencia_tipo, mpd.evidencia_desc, mpd.peso_percentual, mpd.prazo_dias,
               mpd.fase, mpd.numero,
               ua.nome AS activador_nome, uv.nome AS validador_nome
        FROM metas_projeto mp
        JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
        LEFT JOIN usuarios ua ON ua.id = mp.activada_por
        LEFT JOIN usuarios uv ON uv.id = mp.validada_por
        WHERE mp.id_projeto = ?
        ORDER BY mpd.fase, mpd.numero ASC
    ");
    if ($stmtM) {
        $stmtM->bind_param('i', $idProjeto);
        $stmtM->execute();
        $metasProjeto = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtM->close();
    }

    // Dicionário de metas padrão (para exibir o roteiro mesmo antes da ativação)
    $resMP = $mysqli->query("SELECT * FROM metas_padrao WHERE activo = 1 ORDER BY fase, numero ASC");
    if ($resMP) {
        $metasPadraoDict = $resMP->fetch_all(MYSQLI_ASSOC);
    }

    // 5. Tarefas do mentor
    $stmtT = $mysqli->prepare("
        SELECT *
        FROM tarefas
        WHERE id_projeto = ?
        ORDER BY COALESCE(validada_mentor, 0) ASC, data_limite ASC
    ");
    if ($stmtT) {
        $stmtT->bind_param('i', $idProjeto);
        $stmtT->execute();
        $tarefasProjeto = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtT->close();
    }

    // 6. Documentos do projeto
    $stmtDoc = $mysqli->prepare("
        SELECT f.*, u.nome AS enviado_por
        FROM ficheiros_projeto f
        LEFT JOIN usuarios u ON u.id = f.id_usuario_up
        WHERE f.id_projeto = ?
        ORDER BY f.id DESC
    ");
    if ($stmtDoc) {
        $stmtDoc->bind_param('i', $idProjeto);
        $stmtDoc->execute();
        $documentos = $stmtDoc->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtDoc->close();
    }

    // 7. Sessões de Mentoria e Reuniões (idêntico a mentor/projeto_detalhe.php)
    $stmtSes = $mysqli->prepare("
        SELECT s.*, u.nome AS mentor_nome
        FROM sessoes_mentoria s
        JOIN mentorias m ON m.id = s.id_mentoria
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u ON u.id = mt.id_usuario
        WHERE m.id_projeto = ?
        ORDER BY s.data_sessao DESC
    ");
    if ($stmtSes) {
        $stmtSes->bind_param('i', $idProjeto);
        $stmtSes->execute();
        $sessoesMentoria = $stmtSes->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtSes->close();
    }

    $stmtReu = $mysqli->prepare("
        SELECT r.*
        FROM reunioes r
        WHERE r.id_projeto = ? AND r.data_reuniao >= NOW() AND r.status = 'agendada'
        ORDER BY r.data_reuniao ASC
    ");
    if ($stmtReu) {
        $stmtReu->bind_param('i', $idProjeto);
        $stmtReu->execute();
        $reunioesAgend = $stmtReu->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtReu->close();
    }

    // 8. Avaliações periódicas do mentor e comentários
    $stmtAvM = $mysqli->prepare("
        SELECT av.*, u.nome AS mentor_nome
        FROM avaliacoes_mentor av
        JOIN mentores m ON m.id = av.id_mentor
        JOIN usuarios u ON u.id = m.id_usuario
        WHERE av.id_projeto = ?
        ORDER BY av.criado_em DESC
    ");
    if ($stmtAvM) {
        $stmtAvM->bind_param('i', $idProjeto);
        $stmtAvM->execute();
        $avaliacoesMentor = $stmtAvM->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtAvM->close();
    }

    $stmtCom = $mysqli->prepare("
        SELECT c.comentario, c.fase, c.criado_em, u.nome, u.perfil
        FROM comentarios_projetos c
        JOIN usuarios u ON u.id = c.id_usuario
        WHERE c.id_projeto = ?
        ORDER BY c.criado_em DESC
    ");
    if ($stmtCom) {
        $stmtCom->bind_param('i', $idProjeto);
        $stmtCom->execute();
        $comentarios = $stmtCom->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtCom->close();
    }

    // 9. Mensagens do chat do projeto
    $stmtMsg = $mysqli->prepare("
        SELECT m.*, u.nome, u.perfil
        FROM mensagens m
        JOIN usuarios u ON u.id = m.id_usuario
        WHERE m.id_projeto = ?
        ORDER BY m.id ASC
    ");
    if ($stmtMsg) {
        $stmtMsg->bind_param('i', $idProjeto);
        $stmtMsg->execute();
        $mensagensChat = $stmtMsg->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMsg->close();
    }

    // 10. KPIs & Financiamentos
    $stmtKpi = $mysqli->prepare("
        SELECT k.*,
               (SELECT valor FROM registos_kpi WHERE id_kpi = k.id ORDER BY registado_em DESC LIMIT 1) AS ultimo_valor
        FROM kpis k
        WHERE k.id_projeto = ? AND k.activo = 1
    ");
    if ($stmtKpi) {
        $stmtKpi->bind_param('i', $idProjeto);
        $stmtKpi->execute();
        $kpis = $stmtKpi->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtKpi->close();
    }

    $stmtFin = $mysqli->prepare("SELECT * FROM financiamentos WHERE id_projeto = ?");
    if ($stmtFin) {
        $stmtFin->bind_param('i', $idProjeto);
        $stmtFin->execute();
        $financiamentos = $stmtFin->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtFin->close();
    }
}

$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
.task-item-box {
    padding: 15px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    transition: transform 0.2s, border-color 0.2s;
}
.task-item-box:hover { transform: translateX(4px); border-color: var(--primary); }
.task-priority-baixa { border-left: 4px solid var(--success); }
.task-priority-media { border-left: 4px solid var(--warning); }
.task-priority-alta  { border-left: 4px solid var(--danger); }
</style>

<!-- FLASH MESSAGES -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<?php if (!$projeto): ?>
    <div class="page-header">
        <div>
            <div class="page-header-title"><i class="fa fa-rocket me-2" style="color:var(--primary)"></i><?= htmlspecialchars($tituloPagina) ?></div>
            <div class="page-header-sub">Submeta uma proposta no Painel Geral para desbloquear esta secção.</div>
        </div>
    </div>
    <div class="card-custom">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa fa-seedling"></i></div>
            <div class="empty-state-title">Sem startup registada</div>
            <div class="empty-state-text mb-4">Crie a sua primeira proposta de startup no Painel Geral.</div>
            <a href="/incubadora_ispsn/app/views/dashboard/utilizador.php<?= $userParamOnly ?>" class="btn-primary-custom">
                <i class="fa fa-arrow-left"></i> Ir para o Painel Geral
            </a>
        </div>
    </div>

<?php else:
    $idProjeto      = (int)$projeto['id'];
    $estadoRaw      = (string)($projeto['estado'] ?? 'submetido');
    $faseActualProj = (string)($projeto['fase'] ?? 'ideacao');
    $pontos         = (int)($projeto['pontos'] ?? 0);
    $selfUrlSec     = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=' . $idProjeto . '&sec=' . $sec . $userQuerySuffix;
?>

    <!-- CABEÇALHO DA PÁGINA (.page-header IDÊNTICO A mentor/projeto_detalhe.php) -->
    <div class="page-header">
        <div>
            <a href="/incubadora_ispsn/app/views/dashboard/utilizador.php<?= $userParamOnly ?>" class="btn-ghost mb-2" style="padding:5px 12px; font-size:0.8rem;">
                <i class="fa fa-arrow-left"></i> Voltar ao Painel Geral
            </a>
            <div class="page-header-title">
                <?= htmlspecialchars($tituloPagina) ?>: <span style="color:var(--primary)"><?= htmlspecialchars($projeto['titulo']) ?></span>
            </div>
            <div class="page-header-sub">
                <span class="badge-estado badge-<?= htmlspecialchars($estadoRaw) ?>"><?= htmlspecialchars($labelsEstado[$estadoRaw] ?? ucfirst($estadoRaw)) ?></span>
                <span class="ms-2 text-muted">| Fase: <strong><?= strtoupper($faseActualProj) ?></strong> · <strong><?= $pontos ?> SP</strong></span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($sec === 'proposta'): ?>
                <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#modalAddMembro">
                    <i class="fa fa-user-plus me-1"></i> Convidar Equipa
                </button>
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalEditarProposta">
                    <i class="fa fa-pen-to-square me-1"></i> Editar Proposta
                </button>
            <?php elseif ($sec === 'documentos'): ?>
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalDoc">
                    <i class="fa fa-upload me-1"></i> Carregar Documento
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($sec === 'proposta'): ?>
        <!-- ═══════════════════════════════════════════════════════════
             PÁGINA 1 DO MENU: MINHA STARTUP (PROPOSTA, EQUIPA & FINANÇAS)
        ═══════════════════════════════════════════════════════════ -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card-custom mb-4">
                    <div class="card-header-custom">
                        <div class="card-title-custom"><i class="fa fa-file-lines"></i> Resumo &amp; Canvas da Startup</div>
                        <button type="button" class="btn-ghost" style="padding:5px 12px; font-size:0.78rem;" data-bs-toggle="modal" data-bs-target="#modalEditarProposta">
                            <i class="fa fa-pen"></i> Editar
                        </button>
                    </div>
                    <div class="card-body-custom">
                        <p><strong>Descrição Geral:</strong><br><?= nl2br(htmlspecialchars((string)$projeto['descricao'])) ?></p>

                        <?php if (!empty($projeto['pitch_path'])): ?>
                            <div class="mt-3 mb-3 p-3 d-flex align-items-center justify-content-between rounded-3" style="background:var(--surface-2); border:1px solid var(--border);">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fa fa-file-pdf text-danger fa-2x"></i>
                                    <div>
                                        <span class="d-block fw-bold" style="font-size:0.88rem;">Pitch da Ideia</span>
                                        <small class="text-muted" style="font-size:0.75rem;">Apresentação principal da startup</small>
                                    </div>
                                </div>
                                <a href="/incubadora_ispsn/<?= htmlspecialchars($projeto['pitch_path']) ?>" target="_blank" class="btn-primary-custom" style="padding:6px 14px; font-size:0.78rem;">
                                    <i class="fa fa-download"></i> Descarregar Pitch
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <div class="alert-custom alert-info m-0 h-100" style="align-items:flex-start; flex-direction:column;">
                                    <strong><i class="fa fa-bullseye me-1"></i> Problema Identificado:</strong>
                                    <div><?= !empty($projeto['problema']) ? nl2br(htmlspecialchars((string)$projeto['problema'])) : 'Ainda não especificado.' ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert-custom alert-success m-0 h-100" style="align-items:flex-start; flex-direction:column;">
                                    <strong><i class="fa fa-lightbulb me-1"></i> Solução Proposta:</strong>
                                    <div><?= !empty($projeto['solucao']) ? nl2br(htmlspecialchars((string)$projeto['solucao'])) : 'Ainda não especificada.' ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CONSULTOR ESTRATÉGICO IA -->
                <div class="card-custom">
                    <div class="card-header-custom">
                        <div class="card-title-custom"><i class="fa fa-wand-magic-sparkles"></i> Consultor Estratégico IA (Gemini)</div>
                        <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" class="m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="gerar_analise_ia">
                            <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfUrlSec) ?>">
                            <button type="submit" class="btn-primary-custom" style="padding:6px 14px; font-size:0.78rem;">
                                <i class="fa fa-robot"></i> <?= !empty($projeto['feedback_ia']) ? 'Atualizar Parecer IA' : 'Gerar Parecer IA' ?>
                            </button>
                        </form>
                    </div>
                    <div class="card-body-custom">
                        <?php if (!empty($projeto['feedback_ia'])): ?>
                            <div style="font-size:0.88rem; line-height:1.65;"><?= nl2br(htmlspecialchars((string)$projeto['feedback_ia'])) ?></div>
                        <?php else: ?>
                            <p class="text-muted m-0" style="font-size:0.86rem;">
                                Clique em <strong>"Gerar Parecer IA"</strong> para obter uma análise automática sobre pontos fortes, riscos e sugestões para a sua proposta.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- EQUIPA -->
                <div class="card-custom mb-4">
                    <div class="card-header-custom">
                        <div class="card-title-custom"><i class="fa fa-users"></i> Equipa da Startup</div>
                        <button type="button" class="btn-ghost" style="padding:4px 10px; font-size:0.75rem;" data-bs-toggle="modal" data-bs-target="#modalAddMembro">
                            <i class="fa fa-plus"></i> Adicionar
                        </button>
                    </div>
                    <div class="card-body-custom">
                        <?php if (empty($membros)): ?>
                            <div class="fw-bold"><?= htmlspecialchars($projeto['autor_nome']) ?> <span class="badge-estado badge-aprovado ms-1">Líder</span></div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($membros as $mb): ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold" style="font-size:0.88rem;"><?= htmlspecialchars($mb['nome']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($mb['email']) ?></small>
                                        </div>
                                        <span class="badge-estado badge-em_analise"><?= htmlspecialchars($mb['papel'] ?? 'Membro') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- EXECUÇÃO FINANCEIRA (IGUAL A mentor/projeto_detalhe.php) -->
                <?php
                $totalAprovado  = array_sum(array_column($financiamentos, 'montante_aprovado'));
                $totalExecutado = array_sum(array_column($financiamentos, 'montante_executado'));
                $percentagemExec = $totalAprovado > 0 ? round(($totalExecutado / $totalAprovado) * 100) : 0;
                ?>
                <div class="card-custom">
                    <div class="card-header-custom">
                        <div class="card-title-custom"><i class="fa fa-money-bill-wave"></i> Execução Financeira</div>
                    </div>
                    <div class="card-body-custom text-center">
                        <div class="mb-3">
                            <span class="d-block text-muted small mb-1">Total Aprovado</span>
                            <h3 class="fw-bold m-0"><?= number_format($totalAprovado, 0, ',', '.') ?> <small>Kz</small></h3>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between mb-1" style="font-size:0.8rem">
                                <span>Executado: <?= number_format($totalExecutado, 0, ',', '.') ?> Kz</span>
                                <span class="fw-bold"><?= $percentagemExec ?>%</span>
                            </div>
                            <div class="progress-custom" style="height:10px">
                                <div class="progress-bar-custom" style="width:<?= $percentagemExec ?>%; background:var(--success)"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($sec === 'metas'): ?>
        <!-- ═══════════════════════════════════════════════════════════
             PÁGINA 2 DO MENU: METAS & EVIDÊNCIAS (ESPELHO DO MENTOR)
        ═══════════════════════════════════════════════════════════ -->
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- METAS DO PROGRAMA DE INCUBAÇÃO POR FASE -->
                <div class="card-custom mb-4">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <div class="card-title-custom"><i class="fa fa-bullseye"></i> Metas de Incubação por Fase</div>
                        <span class="badge bg-warning text-dark fw-bold" style="font-size:0.75rem;">Fase Atual: <?= strtoupper($faseActualProj) ?></span>
                    </div>
                    <div class="card-body-custom" style="padding: 20px;">
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <?php foreach ($fasesDisponiveis as $fKey => $fInfo):
                                $metasF = array_values(array_filter($metasProjeto, fn($m) => $m['fase'] === $fKey));
                                $usandoPadrao = false;
                                if (empty($metasF)) {
                                    $metasF = array_values(array_filter($metasPadraoDict, fn($mp) => $mp['fase'] === $fKey));
                                    $usandoPadrao = true;
                                }
                                if (empty($metasF)) continue;
                                $isFaseActual = ($fKey === $faseActualProj);
                            ?>
                                <div class="p-3 rounded border <?= $isFaseActual ? 'border-warning' : '' ?>" style="background: <?= $isFaseActual ? 'var(--surface-2)' : 'var(--surface)' ?>;">
                                    <div class="fw-bold mb-3 d-flex align-items-center justify-content-between" style="font-size:0.9rem; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
                                        <span><?= $fInfo['emoji'] ?> Fase de <?= $fInfo['label'] ?></span>
                                        <?php if ($isFaseActual): ?>
                                            <span class="badge bg-warning text-dark fw-bold" style="font-size:0.68rem;">Fase Atual</span>
                                        <?php endif; ?>
                                    </div>

                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <?php foreach ($metasF as $m):
                                            $est       = $usandoPadrao ? 'inactiva' : (string)$m['estado'];
                                            $mTitulo   = $usandoPadrao ? (string)$m['titulo'] : (string)$m['meta_titulo'];
                                            $mDesc     = $usandoPadrao ? (string)$m['descricao'] : (string)$m['meta_descricao'];
                                            $mPeso     = (int)($m['peso_percentual'] ?? 20);
                                            $mEvidTipo = (string)($m['evidencia_tipo'] ?? 'ficheiro');
                                            $mEvidDesc = (string)($m['evidencia_desc'] ?? 'Anexe o comprovativo da meta');

                                            $badgeBg  = '#cbd5e1; color:#475569';
                                            $badgeLbl = $usandoPadrao ? 'Aguarda Ativação' : 'Inativa';
                                            if ($est === 'concluida') {
                                                $badgeBg  = '#d1fae5; color:#065f46';
                                                $badgeLbl = 'Concluída ✓';
                                            } elseif ($est === 'activa') {
                                                $badgeBg  = '#fef3c7; color:#92400e';
                                                $badgeLbl = 'Ativa p/ Entrega';
                                            } elseif ($est === 'em_avaliacao') {
                                                $badgeBg  = '#e0f2fe; color:#0369a1';
                                                $badgeLbl = 'Em Validação pelo Mentor';
                                            } elseif ($est === 'reprovada') {
                                                $badgeBg  = '#fee2e2; color:#b91c1c';
                                                $badgeLbl = 'Devolvida';
                                            }

                                            $canSubmit = (!$usandoPadrao) && ($est === 'activa' || $est === 'reprovada');
                                            $payloadM  = json_encode([
                                                'id'     => (int)($m['id'] ?? 0),
                                                'titulo' => $mTitulo,
                                                'tipo'   => $mEvidTipo,
                                                'desc'   => $mEvidDesc,
                                                'texto'  => (string)($m['evidencia_texto'] ?? ''),
                                                'link'   => (string)($m['evidencia_link'] ?? ''),
                                                'path'   => (string)($m['evidencia_path'] ?? ''),
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                        ?>
                                            <div style="padding:14px; border-radius:10px; background:var(--surface); border:1px solid var(--border);">
                                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2 flex-wrap">
                                                    <div style="max-width:75%;">
                                                        <div class="fw-bold" style="font-size:0.86rem;"><?= (int)$m['numero'] ?>. <?= htmlspecialchars($mTitulo) ?></div>
                                                        <small class="text-muted d-block"><?= htmlspecialchars($mDesc) ?></small>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge" style="font-size:0.68rem; padding:5px 9px; background:<?= $badgeBg ?>;"><?= $badgeLbl ?></span>
                                                        <?php if ($canSubmit): ?>
                                                            <button type="button" class="btn-primary-custom" style="padding:5px 12px; font-size:0.75rem;" onclick="abrirSubmeterEvidencia(<?= htmlspecialchars((string)$payloadM, ENT_QUOTES, 'UTF-8') ?>)">
                                                                <i class="fa fa-paper-plane"></i> Submeter Evidência
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="mt-2" style="font-size:0.75rem; display:flex; gap:12px; flex-wrap:wrap; color:var(--text-muted);">
                                                    <span><i class="fa fa-calendar me-1"></i> Limite: <?= !empty($m['data_limite']) ? date('d/m/Y', strtotime($m['data_limite'])) : 'Após ativação' ?></span>
                                                    <span><i class="fa fa-bolt me-1" style="color:var(--primary)"></i> Recompensa: +<?= $mPeso ?> SP</span>
                                                    <?php if (!empty($m['validador_nome'])): ?>
                                                        <span><i class="fa fa-user-check me-1 text-success"></i> Validada por: <?= htmlspecialchars($m['validador_nome']) ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if (!$usandoPadrao && ($est === 'em_avaliacao' || !empty($m['evidencia_em']))): ?>
                                                    <div class="p-3 rounded border small mt-2" style="background:var(--surface-2);">
                                                        <div class="fw-bold mb-1"><i class="fa fa-paperclip me-1"></i> Evidência Submetida:</div>
                                                        <div class="mb-2 p-2 rounded bg-white" style="border-left: 3px solid var(--primary);"><?= nl2br(htmlspecialchars((string)$m['evidencia_texto'])) ?></div>
                                                        <div class="d-flex gap-2 align-items-center flex-wrap">
                                                            <?php if (!empty($m['evidencia_link'])): ?>
                                                                <a href="<?= htmlspecialchars($m['evidencia_link']) ?>" target="_blank" class="btn-ghost" style="font-size:0.72rem; padding:3px 10px;"><i class="fa fa-link me-1"></i>Ver Link</a>
                                                            <?php endif; ?>
                                                            <?php if (!empty($m['evidencia_path'])): ?>
                                                                <a href="/incubadora_ispsn/<?= htmlspecialchars($m['evidencia_path']) ?>" target="_blank" class="btn-ghost" style="font-size:0.72rem; padding:3px 10px;"><i class="fa fa-file me-1"></i>Ver Ficheiro</a>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($m['feedback_mentor'])): ?>
                                                            <div class="mt-2 text-danger" style="font-size:0.75rem;">
                                                                <strong>Feedback do Mentor:</strong> <?= htmlspecialchars($m['feedback_mentor']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- TAREFAS DE APOIO AD-HOC DO MENTOR -->
                <div class="card-custom mb-4">
                    <div class="card-header-custom">
                        <div class="card-title-custom"><i class="fa fa-list-check"></i> Plano de Ação / Tarefas do Mentor</div>
                    </div>
                    <div class="card-body-custom">
                        <?php if (empty($tarefasProjeto)): ?>
                            <div class="text-center p-4">
                                <i class="fa fa-clipboard-check fa-2x text-muted mb-2"></i>
                                <p class="text-muted small m-0">Nenhuma tarefa extra atribuída pelo mentor neste momento.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($tarefasProjeto as $t):
                                $idTar   = (int)$t['id'];
                                $valMent = (int)($t['validada_mentor'] ?? 0) === 1;
                                $prio    = $t['prioridade'] ?? 'media';
                            ?>
                                <div class="task-item-box task-priority-<?= htmlspecialchars($prio) ?>">
                                    <div class="w-100">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div class="fw-bold" style="font-size:0.86rem;"><?= htmlspecialchars($t['titulo']) ?></div>
                                            <?php if ($valMent): ?>
                                                <span class="badge bg-success-subtle text-success">Validada ✓</span>
                                            <?php else: ?>
                                                <button type="button" class="btn-primary-custom" style="padding:4px 10px; font-size:0.72rem;" data-bs-toggle="collapse" data-bs-target="#boxEvidTar<?= $idTar ?>">
                                                    <i class="fa fa-upload"></i> Entregar
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($t['descricao'])): ?>
                                            <div class="text-muted small mt-1"><?= htmlspecialchars($t['descricao']) ?></div>
                                        <?php endif; ?>
                                        <div class="mt-2 small text-muted" style="font-size:0.72rem;">
                                            <i class="fa fa-calendar me-1"></i> <?= !empty($t['data_limite']) ? date('d/m/Y', strtotime($t['data_limite'])) : 'Sem prazo' ?>
                                        </div>

                                        <?php if (!$valMent): ?>
                                            <div class="collapse mt-2" id="boxEvidTar<?= $idTar ?>">
                                                <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" enctype="multipart/form-data" class="p-2 rounded border" style="background:var(--surface-2);">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="atualizar_estado_tarefa">
                                                    <input type="hidden" name="id_tarefa" value="<?= $idTar ?>">
                                                    <input type="hidden" name="status" value="concluida">
                                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfUrlSec) ?>">
                                                    <div class="mb-2">
                                                        <input type="text" name="evidencia_nota" class="form-control form-control-sm" placeholder="Resumo do que realizou..." required value="<?= htmlspecialchars((string)($t['evidencia_nota'] ?? '')) ?>">
                                                    </div>
                                                    <div class="mb-2">
                                                        <input type="file" name="evidencia_ficheiro" class="form-control form-control-sm">
                                                    </div>
                                                    <button type="submit" class="btn-primary-custom w-100 justify-content-center" style="padding:5px 10px; font-size:0.75rem;">Enviar ao Mentor</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- KPIS DA STARTUP -->
                <div class="card-custom">
                    <div class="card-header-custom">
                        <div class="card-title-custom"><i class="fa fa-chart-line"></i> Indicadores (KPIs)</div>
                    </div>
                    <div class="card-body-custom">
                        <?php if (empty($kpis)): ?>
                            <p class="text-muted small text-center m-0">Nenhum KPI registado ainda.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($kpis as $k):
                                    $perc = $k['meta'] > 0 ? min(100, ($k['ultimo_valor'] / $k['meta']) * 100) : 0;
                                ?>
                                    <div class="p-3 rounded border" style="background:var(--surface-2);">
                                        <small class="text-muted d-block"><?= htmlspecialchars($k['nome']) ?></small>
                                        <div class="fw-bold" style="font-size:1.3rem; color:var(--primary);">
                                            <?= $k['ultimo_valor'] !== null ? number_format((float)$k['ultimo_valor'], 1) : '—' ?>
                                            <small class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($k['unidade']) ?></small>
                                        </div>
                                        <div class="progress-custom mt-2" style="height:5px;">
                                            <div class="progress-bar-custom" style="width:<?= $perc ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($sec === 'mentoria'): ?>
        <!-- ═══════════════════════════════════════════════════════════
             PÁGINA 3 DO MENU: MENTORIA & CHAT DIRETO (ESPELHO DO MENTOR)
        ═══════════════════════════════════════════════════════════ -->
        <div class="row g-4">
            <!-- CANAL DE CHAT DIRETO (IDÊNTICO A mentor/projeto_detalhe.php) -->
            <div class="col-lg-7">
                <div class="card-custom border-0 shadow-sm" style="overflow: hidden; border-radius: 18px;">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <div class="card-title-custom mb-0">
                            <i class="fa fa-comments me-2" style="color:var(--primary)"></i> Canal de Mentoria &amp; Consultoria Direta
                        </div>
                        <span class="badge-estado badge-activa">Espaço Colaborativo</span>
                    </div>
                    <div class="card-body-custom p-0" style="background: var(--bg);">
                        <div id="chat-container-std" style="height: 440px; overflow-y: auto; padding: 22px; display:flex; flex-direction:column; gap:14px;">
                            <?php if (empty($mensagensChat)): ?>
                                <div class="text-center p-5 my-auto">
                                    <div class="mb-3 text-muted"><i class="fa fa-comments fa-3x" style="opacity:0.3"></i></div>
                                    <h6 class="fw-bold">Inicie a conversa com o seu Mentor / Coordenação</h6>
                                    <p class="text-muted small m-0">Envie dúvidas, alinhamentos de metas ou pedidos de revisão abaixo.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($mensagensChat as $m):
                                    $isMe = ((int)$m['id_usuario'] === (int)$idUsuario);
                                    $roleColors = [
                                        'mentor'     => ['bg' => 'rgba(139, 92, 246, 0.12)', 'color' => '#8B5CF6'],
                                        'admin'      => ['bg' => 'rgba(217, 119, 6, 0.12)',  'color' => '#D97706'],
                                        'superadmin' => ['bg' => 'rgba(239, 68, 68, 0.12)',  'color' => '#EF4444'],
                                        'utilizador' => ['bg' => 'rgba(71, 85, 105, 0.12)',  'color' => '#475569'],
                                    ];
                                    $roleStyle = $roleColors[$m['perfil']] ?? ['bg' => '#eee', 'color' => '#333'];
                                    $dtMsg = $m['criado_at'] ?? ($m['enviado_em'] ?? '');
                                ?>
                                    <div class="d-flex <?= $isMe ? 'justify-content-end' : 'justify-content-start' ?>">
                                        <div style="max-width: 78%;">
                                            <div class="mb-1 d-flex align-items-center gap-2" style="font-size: 0.72rem; color: var(--text-secondary); <?= $isMe ? 'justify-content: flex-end;' : '' ?>">
                                                <span class="fw-bold"><?= htmlspecialchars($m['nome']) ?></span>
                                                <span class="badge rounded-pill px-2" style="background:<?= $roleStyle['bg'] ?>; color:<?= $roleStyle['color'] ?>; font-size:0.6rem; font-weight:800;"><?= strtoupper($m['perfil']) ?></span>
                                            </div>
                                            <div class="p-3 shadow-sm"
                                                 style="background: <?= $isMe ? 'var(--primary)' : 'var(--surface)' ?>; color: <?= $isMe ? '#fff' : 'var(--text-primary)' ?>; border-radius: <?= $isMe ? '16px 16px 2px 16px' : '16px 16px 16px 2px' ?>; font-size: 0.88rem; line-height:1.5; border: <?= $isMe ? 'none' : '1px solid var(--border)' ?>;">
                                                <div style="white-space: pre-wrap;"><?= htmlspecialchars($m['mensagem']) ?></div>
                                                <?php if ($dtMsg): ?>
                                                    <div class="mt-1" style="font-size: 0.65rem; opacity: 0.8; text-align: right;">
                                                        <i class="fa fa-clock me-1"></i><?= date('d/m H:i', strtotime($dtMsg)) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 border-top" style="background: var(--surface);">
                            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" class="d-flex gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="enviar_mensagem">
                                <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                                <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfUrlSec) ?>">
                                <textarea name="mensagem" class="form-control-custom" rows="2" placeholder="Escreva a sua mensagem para o mentor ou coordenação..." required style="resize:none;"></textarea>
                                <button type="submit" class="btn-primary-custom px-4">
                                    <i class="fa fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const c = document.getElementById('chat-container-std');
                    if (c) c.scrollTop = c.scrollHeight;
                });
                </script>
            </div>

            <!-- SESSÕES DE MENTORIA, REUNIÕES & AVALIAÇÕES DO MENTOR -->
            <div class="col-lg-5 d-flex flex-column gap-4">
                <!-- Mentor Card -->
                <div class="card-custom">
                    <div class="card-header-custom">
                        <div class="card-title-custom"><i class="fa fa-user-tie"></i> O Seu Mentor</div>
                    </div>
                    <div class="card-body-custom">
                        <?php if ($mentorAtivo): ?>
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:48px;height:48px;border-radius:50%;background:var(--primary);color:#fff;font-size:1.2rem;font-weight:800;display:flex;align-items:center;justify-content:center;">
                                    <?= strtoupper(substr($mentorAtivo['mentor_nome'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($mentorAtivo['mentor_nome']) ?></h6>
                                    <small class="text-muted d-block"><?= htmlspecialchars($mentorAtivo['especialidade'] ?? 'Mentor Especialista') ?></small>
                                    <small class="text-muted"><?= htmlspecialchars($mentorAtivo['mentor_email']) ?></small>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small m-0">O mentor especialista será vinculado assim que o projeto entrar na etapa de incubação.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Histórico de Sessões Realizadas -->
                <div class="card-custom">
                    <div class="card-header-custom">
                        <div class="card-title-custom"><i class="fa fa-calendar-check"></i> Sessões de Mentoria (<?= count($sessoesMentoria) ?>)</div>
                    </div>
                    <div class="card-body-custom">
                        <?php if (empty($sessoesMentoria)): ?>
                            <p class="text-muted small m-0">Ainda não existem sessões registadas pelo mentor.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($sessoesMentoria as $s): ?>
                                    <div class="p-3 rounded border" style="background:var(--surface-2);">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong style="font-size:0.84rem;"><?= htmlspecialchars($s['mentor_nome']) ?></strong>
                                            <span class="badge bg-light text-dark"><?= date('d/m/Y', strtotime($s['data_sessao'])) ?></span>
                                        </div>
                                        <div class="small"><?= nl2br(htmlspecialchars($s['topicos'])) ?></div>
                                        <?php if (!empty($s['proximos_passos'])): ?>
                                            <div class="mt-2 p-2 rounded bg-white small">
                                                <strong>Próximos passos:</strong> <?= htmlspecialchars($s['proximos_passos']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pareceres da Comissão e Avaliações do Mentor -->
                <div class="card-custom">
                    <div class="card-header-custom">
                        <div class="card-title-custom"><i class="fa fa-star"></i> Pareceres &amp; Feedback Oficial</div>
                    </div>
                    <div class="card-body-custom">
                        <?php if (empty($comentarios) && empty($avaliacoesMentor)): ?>
                            <p class="text-muted small m-0">Sem pareceres adicionais registados até ao momento.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($avaliacoesMentor as $av): ?>
                                    <div class="p-3 rounded border" style="background:var(--surface-2);">
                                        <div class="d-flex justify-content-between mb-1">
                                            <strong class="small"><?= htmlspecialchars($av['periodo']) ?></strong>
                                            <span class="fw-bold small" style="color:var(--primary)">Progresso: <?= (int)$av['progresso_geral'] ?>%</span>
                                        </div>
                                        <div class="small text-muted"><?= nl2br(htmlspecialchars($av['feedback'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($comentarios as $c): ?>
                                    <div class="p-3 rounded border">
                                        <div class="d-flex justify-content-between mb-1">
                                            <strong class="small"><?= htmlspecialchars($c['nome']) ?> (<?= htmlspecialchars(ucfirst($c['perfil'])) ?>)</strong>
                                            <small class="text-muted"><?= date('d/m/Y', strtotime($c['criado_em'])) ?></small>
                                        </div>
                                        <div class="small"><?= nl2br(htmlspecialchars($c['comentario'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($sec === 'documentos'): ?>
        <!-- ═══════════════════════════════════════════════════════════
             PÁGINA 4 DO MENU: DOC HUB / FICHEIROS & TERMO (IGUAL AO MENTOR)
        ═══════════════════════════════════════════════════════════ -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <div class="card-title-custom"><i class="fa fa-folder-open"></i> Doc Hub — Arquivo Documental da Startup</div>
                        <button type="button" class="btn-primary-custom" style="padding:6px 14px; font-size:0.78rem;" data-bs-toggle="modal" data-bs-target="#modalDoc">
                            <i class="fa fa-upload"></i> Anexar Novo Ficheiro
                        </button>
                    </div>
                    <div class="card-body-custom">
                        <?php if (!empty($projeto['pitch_path'])): ?>
                            <div class="p-3 border rounded d-flex align-items-center justify-content-between mb-3" style="background:var(--surface-2); border-color:var(--border) !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fa fa-file-powerpoint fa-2x" style="color:var(--primary)"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0">Pitch Deck Principal da Proposta</h6>
                                        <small class="text-muted">Submetido na criação da candidatura</small>
                                    </div>
                                </div>
                                <a href="/incubadora_ispsn/<?= htmlspecialchars($projeto['pitch_path']) ?>" target="_blank" class="btn-primary-custom" style="padding:6px 14px; font-size:0.78rem;">
                                    <i class="fa fa-download"></i> Abrir
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($documentos)): ?>
                            <div class="text-center p-5">
                                <i class="fa fa-folder-open fa-3x text-muted mb-3" style="opacity:0.3"></i>
                                <p class="text-muted mb-3">Ainda não carregou documentos técnicos adicionais (Canvas, Plano Financeiro, Protótipos).</p>
                                <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalDoc">
                                    <i class="fa fa-upload"></i> Carregar Primeiro Documento
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($documentos as $f):
                                    $ext = strtolower(pathinfo((string)$f['path'], PATHINFO_EXTENSION));
                                    $icon = 'fa-file-lines';
                                    $iconColor = '#64748b';
                                    if ($ext === 'pdf') { $icon = 'fa-file-pdf'; $iconColor = '#ef4444'; }
                                    elseif (in_array($ext, ['doc', 'docx'], true)) { $icon = 'fa-file-word'; $iconColor = '#3b82f6'; }
                                    elseif (in_array($ext, ['xls', 'xlsx'], true)) { $icon = 'fa-file-excel'; $iconColor = '#10b981'; }
                                    elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) { $icon = 'fa-file-image'; $iconColor = '#8b5cf6'; }
                                ?>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded d-flex align-items-start gap-3 h-100" style="background:var(--surface); border-color:var(--border) !important;">
                                            <div style="font-size: 2rem; color: <?= $iconColor ?>;">
                                                <i class="fa <?= $icon ?>"></i>
                                            </div>
                                            <div style="flex:1; min-width:0;">
                                                <h6 class="fw-bold text-truncate mb-1" style="font-size:0.9rem;"><?= htmlspecialchars($f['titulo']) ?></h6>
                                                <span class="badge bg-light text-dark mb-1"><?= strtoupper((string)$f['tipo']) ?></span>
                                                <div class="text-muted" style="font-size:0.74rem;">
                                                    Enviado por: <?= htmlspecialchars($f['enviado_por'] ?? 'Equipa') ?>
                                                </div>
                                            </div>
                                            <a href="/incubadora_ispsn/<?= htmlspecialchars($f['path']) ?>" target="_blank" class="btn-ghost" title="Descarregar" style="color:var(--primary);">
                                                <i class="fa fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <div class="card-title-custom"><i class="fa fa-file-contract"></i> Termo de Incubação</div>
                    </div>
                    <div class="card-body-custom">
                        <?php if ($termoProjeto): ?>
                            <div class="mb-3">
                                <small class="text-muted d-block">Código do Termo</small>
                                <strong style="font-size:1rem;"><?= htmlspecialchars($termoProjeto['codigo_termo']) ?></strong>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block">Estado</small>
                                <span class="badge-estado badge-<?= $termoProjeto['estado'] === 'assinado' ? 'aprovado' : 'pendente' ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $termoProjeto['estado']))) ?>
                                </span>
                            </div>
                            <a href="/incubadora_ispsn/app/views/admin/termo_incubacao.php?id=<?= (int)$termoProjeto['id'] ?>" class="btn-primary-custom w-100 justify-content-center">
                                <i class="fa fa-file-pdf"></i> Visualizar / Exportar PDF
                            </a>
                        <?php else: ?>
                            <p class="text-muted small m-0">
                                O Termo Oficial de Incubação é emitido pela Coordenação após a aprovação da proposta pelos 3 avaliadores da Comissão Técnica.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════
         MODAIS PARTILHADOS (EDIÇÃO, EQUIPA, DOCUMENTO, EVIDÊNCIA)
    ═══════════════════════════════════════════════════════════ -->

    <!-- MODAL: EDITAR PROPOSTA -->
    <div class="modal fade" id="modalEditarProposta" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content modal-content-custom">
                <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="editar_proposta">
                    <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfUrlSec) ?>">
                    <div class="modal-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="modal-title fw-bold m-0"><i class="fa fa-pen-to-square me-2" style="color:var(--primary)"></i>Actualizar Proposta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body-custom">
                        <div class="mb-3">
                            <label class="form-label-custom">Nome da Startup *</label>
                            <input type="text" name="titulo" class="form-control-custom" required value="<?= htmlspecialchars((string)$projeto['titulo']) ?>">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Tipo *</label>
                                <select name="tipo" class="form-control-custom" required>
                                    <?php foreach ($labelsTipo as $tk => $tl): ?>
                                        <option value="<?= htmlspecialchars($tk) ?>" <?= (($projeto['tipo'] ?? '') === $tk) ? 'selected' : '' ?>><?= htmlspecialchars($tl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Área Temática</label>
                                <select name="area" class="form-control-custom">
                                    <?php
                                    $areasMap = ['tecnologia' => 'Tecnologia', 'saude' => 'Saúde', 'educacao' => 'Educação', 'agro' => 'Agronegócio', 'financas' => 'Finanças', 'outro' => 'Outro'];
                                    foreach ($areasMap as $ak => $al):
                                    ?>
                                        <option value="<?= $ak ?>" <?= (($projeto['area_tematica'] ?? '') === $ak) ? 'selected' : '' ?>><?= $al ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Descrição Geral *</label>
                            <textarea name="descricao" class="form-control-custom" rows="3" required><?= htmlspecialchars((string)($projeto['descricao'] ?? '')) ?></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Problema que Resolve</label>
                                <textarea name="problema" class="form-control-custom" rows="3"><?= htmlspecialchars((string)($projeto['problema'] ?? '')) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Solução Proposta</label>
                                <textarea name="solucao" class="form-control-custom" rows="3"><?= htmlspecialchars((string)($projeto['solucao'] ?? '')) ?></textarea>
                            </div>
                        </div>
                        <div>
                            <label class="form-label-custom">Actualizar Pitch Deck (PDF, PPT, PPTX — Opcional)</label>
                            <input type="file" name="pitch_ficheiro" class="form-control-custom" accept=".pdf,.ppt,.pptx,.zip">
                        </div>
                    </div>
                    <div class="modal-footer-custom">
                        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-primary-custom"><i class="fa fa-floppy-disk me-1"></i>Guardar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: ADICIONAR MEMBRO -->
    <div class="modal fade" id="modalAddMembro" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-custom">
                <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="adicionar_membro">
                    <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfUrlSec) ?>">
                    <div class="modal-header-custom">
                        <h5 class="modal-title fw-bold m-0"><i class="fa fa-user-plus me-2"></i>Adicionar Membro à Equipa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body-custom">
                        <div class="mb-3">
                            <label class="form-label-custom">E-mail do Utilizador Registado *</label>
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

    <!-- MODAL: CARREGAR DOCUMENTO -->
    <div class="modal fade" id="modalDoc" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-custom">
                <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_documento">
                    <input type="hidden" name="id_projeto" value="<?= $idProjeto ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfUrlSec) ?>">
                    <div class="modal-header-custom">
                        <h5 class="modal-title fw-bold m-0"><i class="fa fa-upload me-2"></i>Carregar Documento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body-custom">
                        <div class="mb-3">
                            <label class="form-label-custom">Título do Documento *</label>
                            <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Pitch Deck, Business Model Canvas">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Categoria</label>
                            <select name="tipo" class="form-control-custom">
                                <option value="Pitch">Pitch / Apresentação</option>
                                <option value="Plano de Negócio">Plano de Negócio</option>
                                <option value="Contrato">Contrato / Documento Legal</option>
                                <option value="Outro">Outro</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label-custom">Ficheiro (PDF, Office, ZIP ou Imagem) *</label>
                            <input type="file" name="ficheiro" class="form-control-custom" required>
                        </div>
                    </div>
                    <div class="modal-footer-custom">
                        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-primary-custom">Carregar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: SUBMETER EVIDÊNCIA DE META -->
    <div class="modal fade" id="modalSubmeterEvidencia" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-custom">
                <form action="/incubadora_ispsn/app/controllers/metas_action.php" method="POST" enctype="multipart/form-data" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="submeter_evidencia">
                    <input type="hidden" name="id_meta_projeto" id="submeterMetaId">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($selfUrlSec) ?>">

                    <div class="modal-header-custom">
                        <h5 class="modal-title fw-bold m-0" id="submeterTituloMeta">Submeter Evidência</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body-custom">
                        <div class="alert-custom alert-warning py-2 px-3 mb-3">
                            <div><strong>Entregável esperado:</strong> <span id="submeterDescEvidencia"></span></div>
                        </div>

                        <div class="mb-3 text-start">
                            <label class="form-label-custom" for="submeterTextoInput">Relatório/Descrição da Execução *</label>
                            <textarea name="evidencia_texto" id="submeterTextoInput" class="form-control-custom" rows="4" required placeholder="Especifique em detalhe os resultados alcançados..."></textarea>
                        </div>

                        <div class="mb-3 text-start" id="submeterLinkContainer">
                            <label class="form-label-custom" for="submeterLinkInput">Link Externo (URL)</label>
                            <input type="url" name="evidencia_link" id="submeterLinkInput" class="form-control-custom" placeholder="https://exemplo.com/doc-ou-drive">
                        </div>

                        <div class="mb-0 text-start" id="submeterFicheiroContainer">
                            <label class="form-label-custom" for="submeterFicheiroInput">Ficheiro Comprovativo (PDF, Zip, Imagem)</label>
                            <input type="file" name="evidencia_ficheiro" id="submeterFicheiroInput" class="form-control-custom">
                            <div id="submeterFicheiroAtual" class="small text-muted mt-1"></div>
                        </div>
                    </div>

                    <div class="modal-footer-custom">
                        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-primary-custom"><i class="fa fa-paper-plane me-1"></i> Enviar para o Mentor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function abrirSubmeterEvidencia(meta) {
        document.getElementById('submeterMetaId').value = meta.id || '';
        document.getElementById('submeterTituloMeta').textContent = 'Submeter: ' + (meta.titulo || '');
        document.getElementById('submeterDescEvidencia').textContent = (meta.desc || '') + ' (Tipo: ' + String(meta.tipo || '').toUpperCase() + ')';
        document.getElementById('submeterTextoInput').value = meta.texto || '';

        const linkContainer = document.getElementById('submeterLinkContainer');
        const linkInput     = document.getElementById('submeterLinkInput');
        const fileContainer = document.getElementById('submeterFicheiroContainer');
        const fileInput     = document.getElementById('submeterFicheiroInput');
        const fileAtualDiv  = document.getElementById('submeterFicheiroAtual');

        linkInput.value = meta.link || '';
        fileInput.value = '';
        fileAtualDiv.textContent = '';

        if (meta.tipo === 'link') {
            linkContainer.style.display = 'block';
            linkInput.required = true;
            fileContainer.style.display = 'none';
            fileInput.required = false;
        } else if (meta.tipo === 'ficheiro') {
            linkContainer.style.display = 'block';
            linkInput.required = false;
            fileContainer.style.display = 'block';
            fileInput.required = !meta.path;
            if (meta.path) {
                fileAtualDiv.textContent = 'Já existe um comprovativo submetido anteriormente.';
            }
        } else {
            linkContainer.style.display = 'none';
            linkInput.required = false;
            fileContainer.style.display = 'none';
            fileInput.required = false;
        }

        new bootstrap.Modal(document.getElementById('modalSubmeterEvidencia')).show();
    }
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
