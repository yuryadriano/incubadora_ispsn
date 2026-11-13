<?php
// app/views/utilizador/meu_projeto.php
// Páginas Dedicadas do Menu do Estudante — 1 Único Cartão Limpo por Página
// Secções: ?sec=proposta | ?sec=metas | ?sec=mentoria | ?sec=documentos

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

if ($isAdminOrSuper) {
    $stmtU = $mysqli->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1");
    if ($stmtU) {
        $stmtU->bind_param('i', $idUsuario);
        $stmtU->execute();
        $uRow = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();
        if ($uRow) {
            $nomeUsuario = $uRow['nome'];
            $perfil      = 'utilizador';
        }
    }
}

$secoesValidas = ['proposta', 'metas', 'mentoria', 'documentos'];
$secAtiva      = isset($_GET['sec']) && in_array($_GET['sec'], $secoesValidas, true) ? $_GET['sec'] : 'proposta';

$mapaMenu = [
    'proposta'   => ['id' => 'projetos',   'titulo' => 'Minha Startup'],
    'metas'      => ['id' => 'metas',      'titulo' => 'Metas & Evidências'],
    'mentoria'   => ['id' => 'mentoria',   'titulo' => 'Mentoria & Chat'],
    'documentos' => ['id' => 'documentos', 'titulo' => 'Doc Hub / Ficheiros'],
];
$paginaActiva = $mapaMenu[$secAtiva]['id'];
$tituloPagina = $mapaMenu[$secAtiva]['titulo'];

$userParam = $isAdminOrSuper ? ('?id_usuario=' . (int)$idUsuario) : '';
$userAmp   = $isAdminOrSuper ? ('&id_usuario=' . (int)$idUsuario) : '';

// Carregar projeto do estudante (colunas reais da tabela projetos)
$idProjeto = (int)($_GET['id'] ?? 0);
$projeto   = null;

if ($idProjeto > 0) {
    $stmt = $mysqli->prepare("
        SELECT p.*, u.nome AS lider_nome
        FROM projetos p
        JOIN usuarios u ON u.id = p.criado_por
        LEFT JOIN membros_projeto mp ON mp.id_projeto = p.id
        WHERE p.id = ? AND (p.criado_por = ? OR mp.id_usuario = ?)
        LIMIT 1
    ");
    $stmt->bind_param('iii', $idProjeto, $idUsuario, $idUsuario);
    $stmt->execute();
    $projeto = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$projeto) {
    $stmtUlt = $mysqli->prepare("
        SELECT p.*, u.nome AS lider_nome
        FROM projetos p
        JOIN usuarios u ON u.id = p.criado_por
        LEFT JOIN membros_projeto mp ON mp.id_projeto = p.id
        WHERE p.criado_por = ? OR mp.id_usuario = ?
        ORDER BY p.criado_em DESC
        LIMIT 1
    ");
    $stmtUlt->bind_param('ii', $idUsuario, $idUsuario);
    $stmtUlt->execute();
    $projeto = $stmtUlt->get_result()->fetch_assoc();
    $stmtUlt->close();
    if ($projeto) {
        $idProjeto = (int)$projeto['id'];
    }
}

$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

if (!$projeto) {
    require_once __DIR__ . '/../partials/_layout.php';
    ?>
    <div class="page-header">
        <div>
            <div class="page-header-title"><?= htmlspecialchars($tituloPagina) ?></div>
            <div class="page-header-sub">Submeta a sua proposta no Painel Geral para aceder a esta área.</div>
        </div>
    </div>
    <div class="card-custom">
        <div class="empty-state py-5">
            <div class="empty-state-icon"><i class="fa fa-rocket" style="color:var(--primary)"></i></div>
            <div class="empty-state-title">Sem startup ativa</div>
            <div class="empty-state-text mb-4">Ainda não tem nenhuma proposta de startup registada.</div>
            <a href="/incubadora_ispsn/app/views/dashboard/utilizador.php<?= htmlspecialchars($userParam) ?>" class="btn-primary-custom">
                Ir para o Painel Geral
            </a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../partials/_layout_end.php';
    exit;
}

$redirectSelf = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php?id=' . $idProjeto . '&sec=' . $secAtiva . $userAmp;
$faseAtual    = (string)($projeto['fase'] ?? 'ideacao');
$estadoAtual  = (string)($projeto['estado'] ?? 'submetido');

$fasesOrdem = [
    'ideacao'   => 'Ideação',
    'validacao' => 'Validação',
    'mvp'       => 'MVP',
    'tracao'    => 'Tração',
    'mercado'   => 'Mercado',
];

// Carregar apenas os dados necessários para a secção ativa
$membros        = [];
$metasPorFase   = ['ideacao' => [], 'validacao' => [], 'mvp' => [], 'tracao' => [], 'mercado' => []];
$tarefas        = [];
$mentorAtivo    = null;
$mensagensChat  = [];
$proximaReuniao = null;
$ficheiros      = [];
$termoAtivo     = null;

if ($secAtiva === 'proposta') {
    $stmtEq = $mysqli->prepare("
        SELECT mp.*, u.nome, u.email
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
} elseif ($secAtiva === 'metas') {
    $stmtMP = $mysqli->prepare("
        SELECT mp.*, mpd.fase, mpd.numero, mpd.titulo AS meta_titulo, mpd.descricao AS meta_descricao,
               mpd.evidencia_tipo, mpd.evidencia_desc, mpd.peso_percentual
        FROM metas_projeto mp
        JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
        WHERE mp.id_projeto = ?
        ORDER BY mpd.fase, mpd.numero ASC
    ");
    $temMetasInstanciadas = false;
    if ($stmtMP) {
        $stmtMP->bind_param('i', $idProjeto);
        $stmtMP->execute();
        $rowsMP = $stmtMP->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMP->close();
        foreach ($rowsMP as $rm) {
            $fk = $rm['fase'] ?? 'ideacao';
            if (isset($metasPorFase[$fk])) {
                $metasPorFase[$fk][] = $rm;
                $temMetasInstanciadas = true;
            }
        }
    }
    if (!$temMetasInstanciadas) {
        $resPad = $mysqli->query("
            SELECT id AS id_meta_padrao, fase, numero, titulo AS meta_titulo, descricao AS meta_descricao,
                   evidencia_tipo, evidencia_desc, peso_percentual
            FROM metas_padrao
            WHERE activo = 1
            ORDER BY fase, numero ASC
        ");
        if ($resPad) {
            while ($rp = $resPad->fetch_assoc()) {
                $rp['id']     = 0;
                $rp['estado'] = 'prevista';
                $fk = $rp['fase'] ?? 'ideacao';
                if (isset($metasPorFase[$fk])) {
                    $metasPorFase[$fk][] = $rp;
                }
            }
        }
    }

    $stmtTar = $mysqli->prepare("SELECT * FROM tarefas WHERE id_projeto = ? ORDER BY COALESCE(validada_mentor,0) ASC, data_limite ASC");
    if ($stmtTar) {
        $stmtTar->bind_param('i', $idProjeto);
        $stmtTar->execute();
        $tarefas = $stmtTar->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtTar->close();
    }
} elseif ($secAtiva === 'mentoria') {
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
        $stmtMent->bind_param('i', $idProjeto);
        $stmtMent->execute();
        $mentorAtivo = $stmtMent->get_result()->fetch_assoc();
        $stmtMent->close();
    }

    $stmtReu = $mysqli->prepare("
        SELECT titulo, data_reuniao, link_reuniao
        FROM reunioes
        WHERE id_projeto = ? AND data_reuniao >= NOW() AND status = 'agendada'
        ORDER BY data_reuniao ASC
        LIMIT 1
    ");
    if ($stmtReu) {
        $stmtReu->bind_param('i', $idProjeto);
        $stmtReu->execute();
        $proximaReuniao = $stmtReu->get_result()->fetch_assoc();
        $stmtReu->close();
    }

    $stmtMsg = $mysqli->prepare("
        SELECT m.*, u.nome, u.perfil
        FROM mensagens m
        JOIN usuarios u ON u.id = m.id_usuario
        WHERE m.id_projeto = ?
        ORDER BY m.id ASC
        LIMIT 100
    ");
    if ($stmtMsg) {
        $stmtMsg->bind_param('i', $idProjeto);
        $stmtMsg->execute();
        $mensagensChat = $stmtMsg->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMsg->close();
    }
} elseif ($secAtiva === 'documentos') {
    $stmtFic = $mysqli->prepare("
        SELECT f.*, u.nome AS autor
        FROM ficheiros_projeto f
        LEFT JOIN usuarios u ON u.id = f.id_usuario_up
        WHERE f.id_projeto = ?
        ORDER BY f.id DESC
    ");
    if ($stmtFic) {
        $stmtFic->bind_param('i', $idProjeto);
        $stmtFic->execute();
        $ficheiros = $stmtFic->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtFic->close();
    }

    $stmtTermo = $mysqli->prepare("SELECT * FROM termos_incubacao WHERE id_projeto = ? ORDER BY id DESC LIMIT 1");
    if ($stmtTermo) {
        $stmtTermo->bind_param('i', $idProjeto);
        $stmtTermo->execute();
        $termoAtivo = $stmtTermo->get_result()->fetch_assoc();
        $stmtTermo->close();
    }
}

require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
.chat-box { height: 440px; overflow-y: auto; padding: 20px; background: var(--bg); display: flex; flex-direction: column; gap: 12px; }
.chat-msg { max-width: 76%; padding: 11px 15px; border-radius: 14px; font-size: 0.86rem; line-height: 1.45; }
.chat-msg.me { align-self: flex-end; background: var(--primary); color: #fff; border-bottom-right-radius: 3px; }
.chat-msg.other { align-self: flex-start; background: var(--surface); color: var(--text-primary); border: 1px solid var(--border); border-bottom-left-radius: 3px; }
.chat-meta { font-size: 0.68rem; opacity: 0.78; margin-top: 4px; display: block; text-align: right; }
</style>

<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<!-- CABEÇALHO SIMPLES DA PÁGINA -->
<div class="page-header">
    <div>
        <div class="page-header-title"><?= htmlspecialchars($tituloPagina) ?></div>
        <div class="page-header-sub">
            Startup: <strong><?= htmlspecialchars($projeto['titulo']) ?></strong> · Fase de <?= htmlspecialchars($fasesOrdem[$faseAtual] ?? 'Ideação') ?>
        </div>
    </div>
    <?php if ($secAtiva === 'proposta'): ?>
        <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalEditarProposta">
            <i class="fa fa-pen-to-square"></i> Editar Ficha
        </button>
    <?php elseif ($secAtiva === 'documentos'): ?>
        <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalSubirFicheiro">
            <i class="fa fa-cloud-arrow-up"></i> Anexar Ficheiro
        </button>
    <?php endif; ?>
</div>

<?php if ($secAtiva === 'proposta'): ?>
<!-- =========================================================================
     MINHA STARTUP — 1 ÚNICO CARTÃO COM A FICHA COMPLETA E EQUIPA
     ========================================================================= -->
<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">
            <i class="fa fa-rocket" style="color:var(--primary)"></i>
            Ficha da Startup
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (!empty($projeto['pitch_path'])): ?>
                <a href="/incubadora_ispsn/<?= htmlspecialchars(ltrim((string)$projeto['pitch_path'], '/')) ?>" target="_blank" class="btn-ghost" style="padding:4px 10px; font-size:0.78rem;">
                    <i class="fa fa-file-powerpoint"></i> Abrir Pitch Deck
                </a>
            <?php endif; ?>
            <span class="badge-estado badge-<?= htmlspecialchars($estadoAtual) ?>">
                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $estadoAtual))) ?>
            </span>
        </div>
    </div>
    <div class="card-body-custom p-4">
        <div class="mb-4">
            <div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:0.72rem;">Resumo Executivo</div>
            <div style="font-size:0.92rem; line-height:1.65; color:var(--text-primary);">
                <?= nl2br(htmlspecialchars((string)($projeto['descricao'] ?? 'Sem descrição registada.'))) ?>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:0.72rem;">Problema Identificado</div>
                <div style="font-size:0.88rem; line-height:1.55; color:var(--text-secondary);">
                    <?= nl2br(htmlspecialchars((string)(!empty($projeto['problema']) ? $projeto['problema'] : 'Não especificado.'))) ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:0.72rem;">Solução Proposta</div>
                <div style="font-size:0.88rem; line-height:1.55; color:var(--text-secondary);">
                    <?= nl2br(htmlspecialchars((string)(!empty($projeto['solucao']) ? $projeto['solucao'] : 'Não especificada.'))) ?>
                </div>
            </div>
        </div>

        <!-- Equipa integrada de forma limpa no rodapé do mesmo cartão -->
        <div class="pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-3" style="border-color:var(--border) !important;">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="text-muted small fw-bold me-1">Equipa:</span>
                <span class="badge-estado badge-submetido">
                    <i class="fa fa-crown text-warning me-1"></i><?= htmlspecialchars($projeto['lider_nome'] ?? 'Líder') ?> (Líder)
                </span>
                <?php foreach ($membros as $mb):
                    if ((int)$mb['id_usuario'] === (int)$projeto['criado_por']) continue;
                ?>
                    <span class="badge-estado badge-submetido">
                        <?= htmlspecialchars($mb['nome']) ?> (<?= htmlspecialchars($mb['papel'] ?: 'Membro') ?>)
                    </span>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-ghost" style="padding:4px 12px; font-size:0.78rem;" data-bs-toggle="modal" data-bs-target="#modalAddMembro">
                <i class="fa fa-user-plus"></i> Adicionar Membro
            </button>
        </div>
    </div>
</div>

<!-- MODAL EDITAR PROPOSTA -->
<div class="modal fade" id="modalEditarProposta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="editar_proposta">
                <input type="hidden" name="id_projeto" value="<?= (int)$idProjeto ?>">
                <input type="hidden" name="tipo" value="<?= htmlspecialchars((string)($projeto['tipo'] ?? 'startup_tecnologica')) ?>">
                <input type="hidden" name="area" value="<?= htmlspecialchars((string)($projeto['area_tematica'] ?? 'tecnologia')) ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectSelf) ?>">

                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold m-0">Editar Ficha da Startup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label-custom">Nome da Startup *</label>
                            <input type="text" name="titulo" class="form-control-custom" value="<?= htmlspecialchars((string)$projeto['titulo']) ?>" required minlength="5">
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Resumo Executivo *</label>
                            <textarea name="descricao" class="form-control-custom" rows="3" required minlength="20"><?= htmlspecialchars((string)($projeto['descricao'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Problema Identificado</label>
                            <textarea name="problema" class="form-control-custom" rows="3"><?= htmlspecialchars((string)($projeto['problema'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Solução Proposta</label>
                            <textarea name="solucao" class="form-control-custom" rows="3"><?= htmlspecialchars((string)($projeto['solucao'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Atualizar Pitch Deck (PDF, PPT, PPTX ou ZIP)</label>
                            <input type="file" name="pitch_ficheiro" class="form-control form-control-custom" accept=".pdf,.ppt,.pptx,.zip">
                        </div>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Guardar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL ADICIONAR MEMBRO -->
<div class="modal fade" id="modalAddMembro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="adicionar_membro">
                <input type="hidden" name="id_projeto" value="<?= (int)$idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectSelf) ?>">

                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold m-0">Adicionar Membro à Equipa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">E-mail Institucional do Utilizador *</label>
                        <input type="email" name="email" class="form-control-custom" placeholder="colega@ispsn.ao" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Função / Papel na Startup *</label>
                        <input type="text" name="papel" class="form-control-custom" placeholder="Ex: CTO, Marketing, Finanças" required>
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

<?php elseif ($secAtiva === 'metas'):
    $abaVista = isset($_GET['aba']) && $_GET['aba'] === 'tarefas' ? 'tarefas' : 'metas';
    $faseSel  = isset($_GET['fase']) && isset($fasesOrdem[$_GET['fase']]) ? $_GET['fase'] : $faseAtual;
    $listaM   = $metasPorFase[$faseSel] ?? [];
?>
<!-- =========================================================================
     METAS & EVIDÊNCIAS — 1 ÚNICO CARTÃO LIMPO
     ========================================================================= -->
<div class="card-custom">
    <div class="card-header-custom flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= htmlspecialchars($redirectSelf) ?>&aba=metas" class="<?= $abaVista === 'metas' ? 'btn-primary-custom' : 'btn-ghost' ?>" style="padding:5px 14px; font-size:0.8rem;">
                <i class="fa fa-bullseye"></i> Metas por Fase
            </a>
            <a href="<?= htmlspecialchars($redirectSelf) ?>&aba=tarefas" class="<?= $abaVista === 'tarefas' ? 'btn-primary-custom' : 'btn-ghost' ?>" style="padding:5px 14px; font-size:0.8rem;">
                <i class="fa fa-list-check"></i> Tarefas do Mentor (<?= count($tarefas) ?>)
            </a>
        </div>

        <?php if ($abaVista === 'metas'): ?>
            <div class="d-flex align-items-center gap-1 flex-wrap">
                <?php foreach ($fasesOrdem as $fKey => $fLbl): ?>
                    <a href="<?= htmlspecialchars($redirectSelf) ?>&aba=metas&fase=<?= $fKey ?>"
                       class="<?= $faseSel === $fKey ? 'badge-estado badge-avaliacao' : 'badge-estado badge-submetido' ?> text-decoration-none">
                        <?= htmlspecialchars($fLbl) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body-custom p-0">
        <?php if ($abaVista === 'metas'): ?>
            <?php if (empty($listaM)): ?>
                <div class="empty-state py-5">
                    <div class="empty-state-title">Sem metas registadas para esta fase</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead style="background:var(--surface-2); font-size:0.74rem; text-transform:uppercase; color:var(--text-muted);">
                            <tr>
                                <th class="ps-4">#</th>
                                <th>Meta &amp; Entregável</th>
                                <th>Peso</th>
                                <th>Estado</th>
                                <th class="text-end pe-4">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listaM as $m):
                                $estM = (string)($m['estado'] ?? 'inactiva');
                                $badgeCls = 'badge-submetido';
                                $badgeTxt = 'Bloqueada';
                                if ($estM === 'concluida') {
                                    $badgeCls = 'badge-aprovado';
                                    $badgeTxt = 'Validada ✓';
                                } elseif ($estM === 'activa') {
                                    $badgeCls = 'badge-avaliacao';
                                    $badgeTxt = 'Ativa';
                                } elseif ($estM === 'em_avaliacao') {
                                    $badgeCls = 'badge-incubado';
                                    $badgeTxt = 'Em validação';
                                } elseif ($estM === 'reprovada') {
                                    $badgeCls = 'badge-rejeitado';
                                    $badgeTxt = 'Devolvida';
                                } elseif ($estM === 'prevista') {
                                    $badgeCls = 'badge-submetido';
                                    $badgeTxt = 'Prevista';
                                }
                            ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-muted">#<?= (int)$m['numero'] ?></td>
                                    <td>
                                        <div class="fw-bold" style="font-size:0.88rem;"><?= htmlspecialchars($m['meta_titulo']) ?></div>
                                        <div class="text-muted" style="font-size:0.78rem;"><?= htmlspecialchars($m['meta_descricao']) ?></div>
                                        <?php if (!empty($m['feedback_mentor'])): ?>
                                            <div class="text-danger small mt-1">
                                                <strong>Feedback:</strong> <?= htmlspecialchars($m['feedback_mentor']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold" style="font-size:0.82rem;"><?= (int)($m['peso_percentual'] ?? 20) ?>%</td>
                                    <td><span class="badge-estado <?= $badgeCls ?>"><?= $badgeTxt ?></span></td>
                                    <td class="text-end pe-4">
                                        <?php if (($estM === 'activa' || $estM === 'reprovada') && (int)$m['id'] > 0): ?>
                                            <button type="button" class="btn-primary-custom" style="padding:5px 12px; font-size:0.76rem;"
                                                    onclick="abrirModalMeta(<?= (int)$m['id'] ?>, <?= htmlspecialchars(json_encode($m['meta_titulo']), ENT_QUOTES) ?>)">
                                                <i class="fa fa-upload"></i> Enviar Evidência
                                            </button>
                                        <?php elseif (!empty($m['evidencia_path'])): ?>
                                            <a href="/incubadora_ispsn/<?= htmlspecialchars(ltrim((string)$m['evidencia_path'], '/')) ?>" target="_blank" class="btn-ghost" style="padding:4px 10px; font-size:0.75rem;">
                                                Ver Ficheiro
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- TAREFAS DO MENTOR -->
            <?php if (empty($tarefas)): ?>
                <div class="empty-state py-5">
                    <div class="empty-state-icon"><i class="fa fa-check-circle" style="color:var(--success)"></i></div>
                    <div class="empty-state-title">Sem tarefas atribuídas pelo mentor</div>
                </div>
            <?php else: ?>
                <div class="p-4 d-flex flex-column gap-3">
                    <?php foreach ($tarefas as $tar):
                        $valMentor = (int)($tar['validada_mentor'] ?? 0) === 1;
                    ?>
                        <div class="p-3 rounded-3 border d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <div class="fw-bold" style="font-size:0.9rem;"><?= htmlspecialchars($tar['titulo']) ?></div>
                                <div class="text-muted small">
                                    Prazo: <?= !empty($tar['data_limite']) ? date('d/m/Y', strtotime($tar['data_limite'])) : 'Sem prazo' ?>
                                </div>
                            </div>
                            <?php if ($valMentor): ?>
                                <span class="badge-estado badge-aprovado">Validada ✓</span>
                            <?php else: ?>
                                <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2 flex-wrap">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="atualizar_estado_tarefa">
                                    <input type="hidden" name="id_tarefa" value="<?= (int)$tar['id'] ?>">
                                    <input type="hidden" name="status" value="concluida">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectSelf) ?>&aba=tarefas">
                                    <input type="file" name="evidencia_ficheiro" class="form-control form-control-sm" style="width:210px;" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.zip">
                                    <button type="submit" class="btn-primary-custom" style="padding:5px 12px; font-size:0.76rem;">
                                        <i class="fa fa-check"></i> Entregar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL ENVIAR EVIDÊNCIA DE META -->
<div class="modal fade" id="modalSubmeterMeta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/metas_action.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="submeter_evidencia">
                <input type="hidden" name="id_meta_projeto" id="inputMetaId" value="0">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectSelf) ?>&aba=metas&fase=<?= htmlspecialchars($faseSel) ?>">

                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold m-0" id="tituloModalMeta">Submeter Evidência</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Notas / Resumo da Entrega *</label>
                        <textarea name="evidencia_texto" class="form-control-custom" rows="3" required placeholder="Explique o resultado alcançado..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Ficheiro Comprovativo (PDF, DOCX, PPTX, ZIP, Imagem)</label>
                        <input type="file" name="evidencia_ficheiro" class="form-control form-control-custom" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg,.zip">
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Link Externo (Opcional — GitHub, Figma, Demo)</label>
                        <input type="url" name="evidencia_url" class="form-control-custom" placeholder="https://...">
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
function abrirModalMeta(idMeta, titulo) {
    document.getElementById('inputMetaId').value = idMeta;
    document.getElementById('tituloModalMeta').textContent = 'Evidência: ' + titulo;
    new bootstrap.Modal(document.getElementById('modalSubmeterMeta')).show();
}
</script>

<?php elseif ($secAtiva === 'mentoria'): ?>
<!-- =========================================================================
     MENTORIA & CHAT — 1 ÚNICO CARTÃO COM O CHAT DIRETO
     ========================================================================= -->
<div class="card-custom">
    <div class="card-header-custom flex-wrap gap-2">
        <div class="card-title-custom">
            <i class="fa fa-comments" style="color:var(--primary)"></i>
            Chat com o Mentor &amp; Coordenação
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if ($mentorAtivo): ?>
                <span class="badge-estado badge-aprovado">
                    Mentor: <?= htmlspecialchars($mentorAtivo['mentor_nome']) ?>
                </span>
            <?php else: ?>
                <span class="badge-estado badge-submetido">Canal com a Coordenação</span>
            <?php endif; ?>

            <?php if ($proximaReuniao): ?>
                <span class="badge-estado badge-avaliacao">
                    <i class="fa fa-calendar me-1"></i>Reunião: <?= date('d/m H:i', strtotime($proximaReuniao['data_reuniao'])) ?>
                </span>
                <?php if (!empty($proximaReuniao['link_reuniao'])): ?>
                    <a href="<?= htmlspecialchars($proximaReuniao['link_reuniao']) ?>" target="_blank" class="btn-primary-custom" style="padding:3px 10px; font-size:0.74rem;">
                        Entrar
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat-box" id="chatWindow">
        <?php if (empty($mensagensChat)): ?>
            <div class="text-center my-auto text-muted">
                <i class="fa fa-comments fa-2x mb-2 d-block opacity-50"></i>
                Envie a primeira mensagem para o seu mentor ou coordenação.
            </div>
        <?php else: ?>
            <?php foreach ($mensagensChat as $msg):
                $souEu = ((int)$msg['id_usuario'] === (int)$idUsuario);
                $dataEnvio = $msg['criado_at'] ?? ($msg['data_envio'] ?? ($msg['criado_em'] ?? ''));
            ?>
                <div class="chat-msg <?= $souEu ? 'me' : 'other' ?>">
                    <?php if (!$souEu): ?>
                        <div class="fw-bold mb-1" style="font-size:0.74rem; color:var(--primary);">
                            <?= htmlspecialchars($msg['nome']) ?> (<?= htmlspecialchars(ucfirst($msg['perfil'])) ?>)
                        </div>
                    <?php endif; ?>
                    <div><?= nl2br(htmlspecialchars($msg['mensagem'])) ?></div>
                    <?php if ($dataEnvio): ?>
                        <span class="chat-meta"><?= date('d/m H:i', strtotime($dataEnvio)) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="p-3 border-top" style="background:var(--surface);">
        <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="enviar_mensagem">
            <input type="hidden" name="id_projeto" value="<?= (int)$idProjeto ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectSelf) ?>">
            <div class="input-group">
                <input type="text" name="mensagem" class="form-control" placeholder="Escreva a sua mensagem..." required>
                <button type="submit" class="btn-primary-custom px-4">
                    <i class="fa fa-paper-plane"></i> Enviar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const chatWin = document.getElementById('chatWindow');
if (chatWin) chatWin.scrollTop = chatWin.scrollHeight;
</script>

<?php elseif ($secAtiva === 'documentos'): ?>
<!-- =========================================================================
     DOC HUB / FICHEIROS — 1 ÚNICO CARTÃO COM OS DOCUMENTOS E TERMO
     ========================================================================= -->
<div class="card-custom">
    <div class="card-header-custom flex-wrap gap-2">
        <div class="card-title-custom">
            <i class="fa fa-folder-open" style="color:var(--primary)"></i>
            Repositório de Ficheiros da Startup
        </div>
        <?php if ($termoAtivo): ?>
            <a href="/incubadora_ispsn/app/views/admin/termo_incubacao.php?id=<?= (int)$termoAtivo['id'] ?>" target="_blank" class="btn-ghost" style="padding:4px 12px; font-size:0.78rem;">
                <i class="fa fa-file-contract text-warning"></i> Termo de Incubação (<?= htmlspecialchars($termoAtivo['codigo_termo']) ?>)
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body-custom p-0">
        <?php if (empty($ficheiros) && empty($projeto['pitch_path'])): ?>
            <div class="empty-state py-5">
                <div class="empty-state-icon"><i class="fa fa-folder-open" style="color:var(--primary)"></i></div>
                <div class="empty-state-title">Nenhum ficheiro carregado</div>
                <div class="empty-state-text">Use o botão "Anexar Ficheiro" no topo para guardar documentos da startup.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:var(--surface-2); font-size:0.74rem; text-transform:uppercase; color:var(--text-muted);">
                        <tr>
                            <th class="ps-4">Documento</th>
                            <th>Tipo</th>
                            <th>Submetido por</th>
                            <th>Data</th>
                            <th class="text-end pe-4">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($projeto['pitch_path'])): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><i class="fa fa-file-powerpoint text-warning me-2"></i>Pitch Deck Oficial</td>
                                <td class="text-muted small">Pitch Deck</td>
                                <td class="text-muted small"><?= htmlspecialchars($projeto['lider_nome'] ?? 'Líder') ?></td>
                                <td class="text-muted small"><?= date('d/m/Y', strtotime($projeto['criado_em'])) ?></td>
                                <td class="text-end pe-4">
                                    <a href="/incubadora_ispsn/<?= htmlspecialchars(ltrim((string)$projeto['pitch_path'], '/')) ?>" target="_blank" class="btn-ghost" style="padding:4px 10px; font-size:0.76rem;">
                                        Descarregar
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($ficheiros as $fic): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><i class="fa fa-file-lines text-primary me-2"></i><?= htmlspecialchars($fic['titulo'] ?? 'Documento') ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($fic['tipo'] ?? 'Documento') ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($fic['autor'] ?? 'Equipa') ?></td>
                                <td class="text-muted small"><?= !empty($fic['criado_em']) ? date('d/m/Y H:i', strtotime($fic['criado_em'])) : '—' ?></td>
                                <td class="text-end pe-4">
                                    <a href="/incubadora_ispsn/<?= htmlspecialchars(ltrim((string)$fic['path'], '/')) ?>" target="_blank" class="btn-ghost" style="padding:4px 10px; font-size:0.76rem;">
                                        Descarregar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL SUBIR FICHEIRO -->
<div class="modal fade" id="modalSubirFicheiro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_documento">
                <input type="hidden" name="id_projeto" value="<?= (int)$idProjeto ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectSelf) ?>">

                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold m-0">Anexar Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Título do Documento *</label>
                        <input type="text" name="titulo" class="form-control-custom" placeholder="Ex: Modelo de Negócio / Canvas" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Categoria</label>
                        <select name="tipo" class="form-select form-control-custom">
                            <option value="Documento">Documento Geral</option>
                            <option value="Pitch">Pitch Deck</option>
                            <option value="Financeiro">Plano Financeiro</option>
                            <option value="Relatório">Relatório</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Ficheiro (PDF, DOCX, XLSX, PPTX, ZIP, Imagem — máx. 10 MB) *</label>
                        <input type="file" name="ficheiro" class="form-control form-control-custom" required>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Carregar Ficheiro</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
