<?php
// app/views/admin/avaliacoes.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin','superadmin','mentor']);

// Auto-healing: se a tabela de atribuição não existir no BD, executa o schema silenciosamente
$checkAtrib = @$mysqli->query("SHOW TABLES LIKE 'avaliacoes_atribuicao'");
if (!$checkAtrib || $checkAtrib->num_rows === 0) {
    $schemaFile = __DIR__ . '/../../controllers/update_schema.php';
    if (file_exists($schemaFile)) {
        ob_start();
        include $schemaFile;
        ob_end_clean();
    }
}

$tituloPagina = 'Painel de Avaliação Multicritério';
$paginaActiva = 'avaliacoes';

$idLogado = (int)$_SESSION['usuario_id'];
$perfil   = $_SESSION['usuario_perfil'] ?? 'utilizador';

// ── Filtros via GET ────────────────────────
$filtroAba   = $_GET['aba'] ?? 'fila_aberta';
$filtroBusca = trim($_GET['q'] ?? '');
$pagina      = max(1, (int)($_GET['pag'] ?? 1));
$porPagina   = 15;
$offset      = ($pagina - 1) * $porPagina;

// ── Construir WHERE dinâmico ───────────────
$where  = ['p.criado_por != ' . $idLogado]; // Impedir auto-avaliação (conflito de interesse)
$params = [];
$types  = '';

if ($filtroAba === 'fila_aberta') {
    // Fila aberta: projectos não consolidados e com < 3 avaliadores atribuídos
    $where[] = "(p.estado_avaliacao IS NULL OR p.estado_avaliacao != 'avaliado') AND p.estado NOT IN ('aprovado','rejeitado') AND (SELECT COUNT(*) FROM avaliacoes_atribuicao WHERE projeto_id = p.id) < 3";
} elseif ($filtroAba === 'minhas_avaliacoes') {
    // Atribuídos a este avaliador
    $where[] = "p.id IN (SELECT projeto_id FROM avaliacoes_atribuicao WHERE avaliador_id = $idLogado)";
} elseif ($filtroAba === 'em_avaliacao') {
    // Projectos em processo de avaliação
    $where[] = "p.estado_avaliacao = 'em_avaliacao' AND p.estado NOT IN ('aprovado','rejeitado')";
} elseif ($filtroAba === 'concluidos') {
    // Consolidados ou decisão final emitida
    $where[] = "(p.estado_avaliacao = 'avaliado' OR p.estado IN ('aprovado','rejeitado','em_revisao','incubado'))";
}

if ($filtroBusca) {
    $like = "%$filtroBusca%";
    $where[] = '(p.titulo LIKE ? OR u.nome LIKE ?)';
    $params[] = $like; $params[] = $like;
    $types .= 'ss';
}
$whereSQL = implode(' AND ', $where);

// ── Contar total ───────────────────────────
$sqlCount = "
    SELECT COUNT(DISTINCT p.id) n 
    FROM projetos p 
    JOIN usuarios u ON u.id = p.criado_por 
    WHERE $whereSQL
";
$stmtCount = $mysqli->prepare($sqlCount);
if ($types && $stmtCount) {
    $stmtCount->bind_param($types, ...$params);
}
if ($stmtCount) {
    $stmtCount->execute();
    $total = (int)$stmtCount->get_result()->fetch_assoc()['n'];
    $stmtCount->close();
} else {
    $total = 0;
}
$totalPaginas = max(1, ceil($total / $porPagina));

// ── Buscar projectos ───────────────────────
$sqlMain = "
    SELECT p.id, p.titulo, p.tipo, p.area_tematica, p.criado_em, p.estado, p.estado_avaliacao, p.media_final,
           u.nome autor, u.email email_autor,
           (SELECT COUNT(*) FROM avaliacoes_atribuicao WHERE projeto_id = p.id) n_atribuidores,
           (SELECT COUNT(*) FROM avaliacoes_atribuicao WHERE projeto_id = p.id AND estado = 'concluido') n_concluidos,
           (SELECT id FROM avaliacoes_atribuicao WHERE projeto_id = p.id AND avaliador_id = $idLogado LIMIT 1) id_minha_atribuicao,
           (SELECT estado FROM avaliacoes_atribuicao WHERE projeto_id = p.id AND avaliador_id = $idLogado LIMIT 1) estado_minha_atribuicao,
           a.pontuacao_total minha_nota, a.avaliado_em meu_avaliado_em
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    LEFT JOIN avaliacoes a ON a.id_projeto = p.id AND a.id_avaliador = $idLogado
    WHERE $whereSQL
    ORDER BY p.criado_em DESC
    LIMIT ? OFFSET ?
";
$stmtMain = $mysqli->prepare($sqlMain);
$projectos = [];
if ($stmtMain) {
    $allParams = array_merge($params, [$porPagina, $offset]);
    $allTypes  = $types . 'ii';
    $stmtMain->bind_param($allTypes, ...$allParams);
    $stmtMain->execute();
    $projectos = $stmtMain->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtMain->close();
}

// ── Contadores de Abas ──
$cntFila = 0; $cntMinhas = 0; $cntEmAval = 0; $cntConcl = 0;

$r1 = $mysqli->query("SELECT COUNT(*) n FROM projetos p WHERE p.criado_por != $idLogado AND (p.estado_avaliacao IS NULL OR p.estado_avaliacao != 'avaliado') AND p.estado NOT IN ('aprovado','rejeitado') AND (SELECT COUNT(*) FROM avaliacoes_atribuicao WHERE projeto_id = p.id) < 3");
if ($r1) $cntFila = (int)$r1->fetch_assoc()['n'];

$r2 = $mysqli->query("SELECT COUNT(*) n FROM projetos p WHERE p.id IN (SELECT projeto_id FROM avaliacoes_atribuicao WHERE avaliador_id = $idLogado)");
if ($r2) $cntMinhas = (int)$r2->fetch_assoc()['n'];

$r3 = $mysqli->query("SELECT COUNT(*) n FROM projetos p WHERE p.estado_avaliacao = 'em_avaliacao' AND p.estado NOT IN ('aprovado','rejeitado')");
if ($r3) $cntEmAval = (int)$r3->fetch_assoc()['n'];

$r4 = $mysqli->query("SELECT COUNT(*) n FROM projetos p WHERE (p.estado_avaliacao = 'avaliado' OR p.estado IN ('aprovado','rejeitado','em_revisao','incubado'))");
if ($r4) $cntConcl = (int)$r4->fetch_assoc()['n'];

// Flash messages
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);
$flashAviso= $_SESSION['flash_aviso']?? ''; unset($_SESSION['flash_aviso']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- FLASH MESSAGES -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle me-2"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>
<?php if ($flashAviso):?><div class="alert-custom alert-warning mb-4"><i class="fa fa-circle-info me-2"></i> <?= htmlspecialchars($flashAviso) ?></div><?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header mb-4">
    <div>
        <div class="page-header-title">
            <i class="fa fa-star me-2" style="color:var(--warning)"></i>
            Painel de Avaliação Multicritério (3 Avaliadores)
        </div>
        <div class="page-header-sub">
            Sistema de atribuição autónoma, avaliação cega e decisão 100% consolidada
        </div>
    </div>
</div>

<!-- ABAS DE NAVEGAÇÃO / FILTROS -->
<div class="d-flex gap-2 flex-wrap mb-4" style="border-bottom:1px solid var(--border);padding-bottom:12px">
    <a href="?aba=fila_aberta<?= $filtroBusca ? '&q='.urlencode($filtroBusca) : '' ?>"
       class="btn-tab <?= $filtroAba==='fila_aberta'?'active':'' ?>">
        <i class="fa fa-inbox me-1"></i> Fila Aberta
        <span class="badge bg-warning ms-1"><?= $cntFila ?></span>
    </a>
    <a href="?aba=minhas_avaliacoes<?= $filtroBusca ? '&q='.urlencode($filtroBusca) : '' ?>"
       class="btn-tab <?= $filtroAba==='minhas_avaliacoes'?'active':'' ?>">
        <i class="fa fa-user-check me-1"></i> Atribuídos a Mim
        <span class="badge bg-primary ms-1"><?= $cntMinhas ?></span>
    </a>
    <a href="?aba=em_avaliacao<?= $filtroBusca ? '&q='.urlencode($filtroBusca) : '' ?>"
       class="btn-tab <?= $filtroAba==='em_avaliacao'?'active':'' ?>">
        <i class="fa fa-spinner me-1"></i> Em Avaliação (Fila)
        <span class="badge bg-info ms-1"><?= $cntEmAval ?></span>
    </a>
    <a href="?aba=concluidos<?= $filtroBusca ? '&q='.urlencode($filtroBusca) : '' ?>"
       class="btn-tab <?= $filtroAba==='concluidos'?'active':'' ?>">
        <i class="fa fa-check-double me-1"></i> Concluídos / Consolidados
        <span class="badge bg-success ms-1"><?= $cntConcl ?></span>
    </a>
</div>

<!-- BUSCA -->
<div class="card-custom mb-4">
    <div class="card-body-custom">
        <form method="get" class="d-flex gap-3 flex-wrap align-items-end">
            <input type="hidden" name="aba" value="<?= htmlspecialchars($filtroAba) ?>">
            <div style="flex:1;min-width:240px">
                <label class="form-label-custom">Pesquisar por título de projeto ou autor</label>
                <input type="text" name="q" class="form-control-custom"
                       placeholder="Digite o título ou nome do candidato…"
                       value="<?= htmlspecialchars($filtroBusca) ?>">
            </div>
            <div>
                <button type="submit" class="btn-primary-custom">
                    <i class="fa fa-magnifying-glass me-1"></i> Procurar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- TABELA DE PROJETOS -->
<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">
            <i class="fa fa-list-check me-2"></i>
            Projetos da Fila (<?= $total ?> registos)
        </div>
    </div>

    <?php if (empty($projectos)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa fa-clipboard-check"></i></div>
        <div class="empty-state-title">Nenhum projeto a exibir</div>
        <div class="empty-state-text">Não existem projetos nesta categoria com os filtros selecionados.</div>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Projeto / Candidato</th>
                    <th>Tipo / Área</th>
                    <th>Vagas de Avaliador</th>
                    <th>Estado de Avaliação</th>
                    <th>A Minha Avaliação (Cega)</th>
                    <th>Acções</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($projectos as $p): 
                $nAtrib = (int)$p['n_atribuidores'];
                $nConcl = (int)$p['n_concluidos'];
                $jaSouAvaliador = !empty($p['id_minha_atribuicao']);
                $jaConclui = ($p['estado_minha_atribuicao'] === 'concluido' || !is_null($p['minha_nota']));
                $vagasCheias = ($nAtrib >= 3);
                $isConsolidado = ($p['estado_avaliacao'] === 'avaliado' || in_array($p['estado'], ['aprovado','rejeitado','em_revisao','incubado']));
            ?>
            <tr>
                <td>
                    <a href="/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=<?= $p['id'] ?>"
                       style="font-weight:700;color:var(--primary);text-decoration:none;display:block;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                       title="<?= htmlspecialchars($p['titulo']) ?>">
                        <?= htmlspecialchars($p['titulo']) ?>
                    </a>
                    <small class="text-muted">Autor: <?= htmlspecialchars($p['autor']) ?> &bull; <?= date('d/m/Y', strtotime($p['criado_em'])) ?></small>
                </td>
                <td>
                    <span style="font-size:0.75rem;padding:3px 8px;border-radius:4px;background:var(--surface-2);font-weight:600;">
                        <?= strtoupper($p['tipo']) ?>
                    </span>
                    <br>
                    <small class="text-muted"><?= ucfirst($p['area_tematica'] ?? '—') ?></small>
                </td>
                <td>
                    <!-- Indicador visual de 3 Vagas -->
                    <div style="font-weight:700;font-size:0.9rem;">
                        <i class="fa fa-users me-1" style="color:<?= $vagasCheias?'#10B981':'#F59E0B' ?>"></i>
                        <?= $nAtrib ?> / 3 Atribuídos
                    </div>
                    <small class="text-muted"><?= $nConcl ?> / 3 Notas Submetidas</small>
                </td>
                <td>
                    <?php if ($isConsolidado): ?>
                        <span class="badge bg-success" style="font-weight:700;font-size:0.8rem">
                            <i class="fa fa-check-double me-1"></i> CONSOLIDADO
                        </span>
                        <?php if ($p['media_final'] !== null): ?>
                            <br><small class="text-muted">Média: <strong><?= number_format($p['media_final'],2) ?>/10</strong></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark" style="font-weight:600">
                            <?= $vagasCheias ? '3/3 Em Avaliação' : ($nAtrib > 0 ? 'Fila Aberta ('.$nAtrib.'/3)' : 'Fila Aberta (0/3)') ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($jaConclui): ?>
                        <span style="font-weight:800;color:var(--success);font-size:1rem">
                            <i class="fa fa-eye-slash me-1" title="Avaliação cega submetida"></i> <?= $p['minha_nota'] ?>/10
                        </span>
                        <br><small class="text-muted">Concluída</small>
                    <?php elseif ($jaSouAvaliador): ?>
                        <span style="color:var(--warning);font-weight:600">
                            <i class="fa fa-clock me-1"></i> Pendente de nota
                        </span>
                    <?php else: ?>
                        <span style="color:var(--text-muted)">Não Atribuído</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-2 align-items-center">
                        <?php if ($jaSouAvaliador): ?>
                            <!-- Botão de Avaliar para quem já pegou o caso -->
                            <button type="button" class="btn-primary-custom" style="padding:6px 14px;font-size:0.8rem;"
                                    onclick="abrirModalAvaliar(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['titulo'])) ?>')">
                                <i class="fa <?= $jaConclui ? 'fa-pen' : 'fa-star' ?> me-1"></i>
                                <?= $jaConclui ? 'Editar Nota' : 'Avaliar Agora' ?>
                            </button>
                            
                            <?php if (in_array($perfil, ['admin','superadmin']) && !$jaConclui): ?>
                            <!-- Botão de libertar vaga -->
                            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" onsubmit="return confirm('Deseja libertar esta atribuição para que outro avaliador possa pegar o caso?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="libertar_atribuicao">
                                <input type="hidden" name="id_atribuicao" value="<?= $p['id_minha_atribuicao'] ?>">
                                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Libertar Vaga">
                                    <i class="fa fa-times"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                        <?php elseif (!$vagasCheias && !$isConsolidado): ?>
                            <!-- Botão Pegar Caso (Atribuir a mim) -->
                            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="atribuir_avaliador">
                                <input type="hidden" name="id_projeto" value="<?= $p['id'] ?>">
                                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                                <button type="submit" class="btn-primary-custom" style="padding:6px 14px;font-size:0.8rem;background:#10B981;border-color:#10B981">
                                    <i class="fa fa-hand-pointer me-1"></i> Pegar Caso (<?= $nAtrib ?>/3)
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=<?= $p['id'] ?>"
                               class="btn btn-sm btn-outline-secondary" style="font-size:0.8rem;">
                                <i class="fa fa-eye me-1"></i> Detalhes
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINAÇÃO -->
    <?php if ($totalPaginas > 1): ?>
    <div style="display:flex;justify-content:center;align-items:center;gap:8px;padding:18px">
        <?php
        $baseUrl = '?' . http_build_query(array_filter([
            'aba' => $filtroAba, 'q' => $filtroBusca
        ]));
        for ($i = 1; $i <= $totalPaginas; $i++):
            $isCurrent = ($i === $pagina);
        ?>
        <a href="<?= $baseUrl ?>&pag=<?= $i ?>"
           style="width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;
                  font-size:0.85rem;font-weight:<?= $isCurrent?'700':'500' ?>;text-decoration:none;
                  background:<?= $isCurrent?'var(--primary)':'var(--surface-2)' ?>;
                  color:<?= $isCurrent?'#fff':'var(--text-secondary)' ?>;
                  border:1px solid <?= $isCurrent?'var(--primary)':'var(--border)' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- MODAL INTERATIVO DE AVALIAÇÃO MULTICRITÉRIO -->
<div class="modal fade" id="modalAvaliar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 40px rgba(0,0,0,0.2)">
            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="avaliar">
                <input type="hidden" name="id_projeto" id="modal_id_projeto" value="">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">

                <div class="modal-header" style="background:#0F172A;color:#fff;border-top-left-radius:16px;border-top-right-radius:16px;padding:20px 24px">
                    <div>
                        <h5 class="modal-title font-weight-bold" id="modal_titulo_projeto">Avaliar Projecto</h5>
                        <small style="color:#94A3B8">Pontuação cega nos 8 critérios oficiais da Incubadora ISPSN</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="padding:24px;max-height:70vh;overflow-y:auto">
                    <!-- PAINEL DE CÁLCULO EM TEMPO REAL -->
                    <div style="background:var(--surface-2);border-radius:12px;padding:16px;margin-bottom:24px;display:flex;align-items:center;justify-space-between">
                        <div>
                            <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);font-weight:700">Média Calculada</span>
                            <div style="font-size:2rem;font-weight:900;color:var(--primary)" id="live_media">0.0 / 10</div>
                        </div>
                        <div id="live_badge">
                            <span class="badge bg-secondary" style="font-size:0.9rem;padding:8px 16px">Pendente de Notas</span>
                        </div>
                    </div>

                    <!-- OS 8 CRITÉRIOS DE AVALIAÇÃO -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">1. Inovação & Diferenciação (0-10) <span class="text-danger">*</span></label>
                            <input type="number" name="nota_inovacao" class="form-control-custom input-crit" min="0" max="10" value="0" required onchange="calcularLiveScore()">
                            <small class="text-muted">Veto se &lt; 5</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">2. Viabilidade Técnica & Operacional (0-10)</label>
                            <input type="number" name="nota_viabilidade" class="form-control-custom input-crit" min="0" max="10" value="0" required onchange="calcularLiveScore()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">3. Impacto Socioeconómico (0-10)</label>
                            <input type="number" name="nota_impacto" class="form-control-custom input-crit" min="0" max="10" value="0" required onchange="calcularLiveScore()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">4. Qualificação da Equipa (0-10)</label>
                            <input type="number" name="nota_equipa" class="form-control-custom input-crit" min="0" max="10" value="0" required onchange="calcularLiveScore()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">5. Autossustentabilidade Financeira (0-10) <span class="text-danger">*</span></label>
                            <input type="number" name="nota_sustentabilidade" class="form-control-custom input-crit" min="0" max="10" value="0" required onchange="calcularLiveScore()">
                            <small class="text-muted">Veto se &lt; 4</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">6. Escalabilidade do Negócio (0-10)</label>
                            <input type="number" name="nota_escalabilidade" class="form-control-custom input-crit" min="0" max="10" value="0" required onchange="calcularLiveScore()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">7. Mercado & Potencial Comercial (0-10)</label>
                            <input type="number" name="nota_mercado" class="form-control-custom input-crit" min="0" max="10" value="0" required onchange="calcularLiveScore()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">8. Qualidade da Proposta / Pitch (0-10)</label>
                            <input type="number" name="nota_proposta" class="form-control-custom input-crit" min="0" max="10" value="0" required onchange="calcularLiveScore()">
                        </div>
                    </div>

                    <!-- PARECER TÉCNICO -->
                    <div class="mt-4">
                        <label class="form-label-custom">Observações & Parecer Técnico Fundamentado</label>
                        <textarea name="observacoes" class="form-control-custom" rows="4" placeholder="Escreva os pontos fortes, fragilidades e recomendações para o candidato…"></textarea>
                    </div>
                </div>

                <div class="modal-footer" style="background:var(--surface-2);border-bottom-left-radius:16px;border-bottom-right-radius:16px;padding:16px 24px">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom" style="padding:10px 24px">
                        <i class="fa fa-paper-plane me-1"></i> Submeter Avaliação Cega
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalAvaliar(id, titulo) {
    document.getElementById('modal_id_projeto').value = id;
    document.getElementById('modal_titulo_projeto').innerText = 'Avaliar Projecto: ' + titulo;
    var modalEl = document.getElementById('modalAvaliar');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
    calcularLiveScore();
}

function calcularLiveScore() {
    var inputs = document.querySelectorAll('.input-crit');
    var soma = 0;
    inputs.forEach(function(inp) {
        soma += parseFloat(inp.value) || 0;
    });
    var media = (soma / 8.0).toFixed(2);
    document.getElementById('live_media').innerText = media + ' / 10';

    var valInov = parseFloat(document.querySelector('input[name="nota_inovacao"]').value) || 0;
    var valSust = parseFloat(document.querySelector('input[name="nota_sustentabilidade"]').value) || 0;

    var badgeEl = document.getElementById('live_badge');
    if (valInov < 5 || valSust < 4) {
        badgeEl.innerHTML = '<span class="badge bg-danger" style="font-size:0.9rem;padding:8px 16px"><i class="fa fa-ban me-1"></i> Veto Ativo (Em Revisão / Rejeição)</span>';
    } else if (media >= 7.0) {
        badgeEl.innerHTML = '<span class="badge bg-success" style="font-size:0.9rem;padding:8px 16px"><i class="fa fa-check me-1"></i> Aprovação Recomendada</span>';
    } else if (media >= 4.0) {
        badgeEl.innerHTML = '<span class="badge bg-warning text-dark" style="font-size:0.9rem;padding:8px 16px"><i class="fa fa-rotate-left me-1"></i> Em Revisão Recomendada</span>';
    } else {
        badgeEl.innerHTML = '<span class="badge bg-danger" style="font-size:0.9rem;padding:8px 16px"><i class="fa fa-times me-1"></i> Rejeição Recomendada</span>';
    }
}
</script>

<style>
.btn-tab {
    padding:8px 16px;
    border-radius:8px;
    font-weight:600;
    font-size:0.85rem;
    color:var(--text-secondary);
    text-decoration:none;
    background:var(--surface-2);
    border:1px solid var(--border);
    transition:all 0.2s ease;
}
.btn-tab.active {
    background:var(--primary);
    color:#ffffff !important;
    border-color:var(--primary);
}
</style>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
