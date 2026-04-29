<?php
// app/views/admin/avaliacoes.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin','superadmin']);

$tituloPagina = 'Painel de Avaliação';
$paginaActiva = 'avaliacoes';

// ── Filtros via GET ────────────────────────
$filtroDecisao= $_GET['decisao'] ?? '';
$filtroBusca  = trim($_GET['q']  ?? '');
$pagina       = max(1, (int)($_GET['pag'] ?? 1));
$porPagina    = 15;
$offset       = ($pagina - 1) * $porPagina;

// ── Construir WHERE dinâmico ───────────────
$where  = ['p.estado = "submetido"']; // Mostrar, por defeito, o que aguarda triagem
if (isset($_GET['all'])) $where = ['1=1']; // Ver todos se solicitado
$params = [];
$types  = '';

if ($filtroDecisao) { 
    if ($filtroDecisao === 'pendente_avaliacao') {
        $where[] = 'a.id IS NULL'; 
    } else {
        $where[] = 'a.decisao = ?';  
        $params[] = $filtroDecisao; 
        $types .= 's'; 
    }
}

if ($filtroBusca)  {
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
    JOIN usuarios u ON u.id=p.criado_por 
    LEFT JOIN avaliacoes a ON a.id_projeto = p.id AND a.id_avaliador = {$_SESSION['usuario_id']}
    WHERE $whereSQL
";
$stmtCount = $mysqli->prepare($sqlCount);
if ($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$total = (int)$stmtCount->get_result()->fetch_assoc()['n'];
$totalPaginas = max(1, ceil($total / $porPagina));

// ── Buscar projectos ───────────────────────
$sqlMain = "
    SELECT p.id, p.titulo, p.tipo, p.area_tematica, p.criado_em,
           u.nome autor, u.email email_autor,
           a.pontuacao_total nota, a.decisao, a.avaliado_em
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    LEFT JOIN avaliacoes a ON a.id_projeto = p.id AND a.id_avaliador = {$_SESSION['usuario_id']}
    WHERE $whereSQL
    ORDER BY p.criado_em DESC
    LIMIT ? OFFSET ?
";
$stmtMain = $mysqli->prepare($sqlMain);
$allParams = array_merge($params, [$porPagina, $offset]);
$allTypes  = $types . 'ii';
$stmtMain->bind_param($allTypes, ...$allParams);
$stmtMain->execute();
$projectos = $stmtMain->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Contadores rápidos para o User Logado ──
$idLogado = (int)$_SESSION['usuario_id'];
$contadores = [
    'pendente_avaliacao' => 0,
    'submetido' => 0,
    'em_avaliacao' => 0,
    'aprovado' => 0,
    'rejeitado' => 0,
    'incubado' => 0
];

// Submetidos (Aguardando triagem)
$rPend = $mysqli->query("SELECT COUNT(*) n FROM projetos WHERE estado = 'submetido'");
$contadores['submetido'] = $rPend ? (int)$rPend->fetch_assoc()['n'] : 0;

// Avaliados por este admin, agrupados por decisão
$rAv = $mysqli->query("SELECT decisao, COUNT(*) n FROM avaliacoes WHERE id_avaliador = $idLogado GROUP BY decisao");
if ($rAv) while ($row = $rAv->fetch_assoc()) $contadores[$row['decisao']] = (int)$row['n'];

// Flash messages
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- FLASH MESSAGES -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa fa-star me-2" style="color:var(--warning)"></i>
            Painel de Avaliação
        </div>
        <div class="page-header-sub">
            As suas avaliações de projectos pendentes e concluídas
        </div>
    </div>
</div>

<!-- CONTADORES -->
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:24px">
    <?php
    $decisoesMeta = [
        'pendente_avaliacao' => ['label'=>'A Aguardar Avaliação','cor'=>'#F59E0B', 'icon'=>'fa-clock'],
        'aprovado'           => ['label'=>'Aprovados por si',    'cor'=>'#10B981', 'icon'=>'fa-check-double'],
        'rejeitado'          => ['label'=>'Rejeitados por si',   'cor'=>'#EF4444', 'icon'=>'fa-xmark'],
        'em_revisao'         => ['label'=>'Em Revisão',          'cor'=>'#3B82F6', 'icon'=>'fa-rotate-left'],
    ];
    foreach ($decisoesMeta as $dec => $meta):
        $n = $contadores[$dec] ?? 0;
        $ativo = ($filtroDecisao === $dec) ? 'box-shadow:0 0 0 2px '.$meta['cor'].';' : '';
    ?>
    <a href="?decisao=<?= $dec ?><?= $filtroBusca ? '&q='.urlencode($filtroBusca) : '' ?>"
       style="text-decoration:none">
        <div class="kpi-card" style="--kpi-color:<?= $meta['cor'] ?>;<?= $ativo ?>">
            <div class="kpi-icon"><i class="fa <?= $meta['icon'] ?>"></i></div>
            <div class="kpi-value"><?= $n ?></div>
            <div class="kpi-label"><?= $meta['label'] ?></div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php if ($filtroDecisao): ?>
    <a href="?" style="text-decoration:none">
        <div class="kpi-card" style="--kpi-color:#94A3B8">
            <div class="kpi-icon"><i class="fa fa-filter-circle-xmark"></i></div>
            <div class="kpi-value">×</div>
            <div class="kpi-label">Limpar Filtros</div>
        </div>
    </a>
    <?php endif; ?>
</div>

<!-- FILTROS + BUSCA -->
<div class="card-custom mb-4">
    <div class="card-body-custom">
        <form method="get" class="d-flex gap-3 flex-wrap align-items-end">
            <div style="flex:1;min-width:220px">
                <label class="form-label-custom">Pesquisar projeto ou autor</label>
                <input type="text" name="q" class="form-control-custom"
                       placeholder="Título ou nome…"
                       value="<?= htmlspecialchars($filtroBusca) ?>">
            </div>
            <div style="min-width:200px">
                <label class="form-label-custom">Estado da Sua Avaliação</label>
                <select name="decisao" class="form-control-custom">
                    <option value="">Fila de Triagem (Submetidos)</option>
                    <option value="em_avaliacao" <?= $filtroDecisao==='em_avaliacao'? 'selected':'' ?>>Em Avaliação</option>
                    <option value="aprovado"     <?= $filtroDecisao==='aprovado'    ? 'selected':'' ?>>Aprovados</option>
                    <option value="rejeitado"    <?= $filtroDecisao==='rejeitado'   ? 'selected':'' ?>>Rejeitados</option>
                    <option value="incubado"     <?= $filtroDecisao==='incubado'    ? 'selected':'' ?>>Incubados</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn-primary-custom">
                    <i class="fa fa-magnifying-glass"></i> Procurar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- TABELA DE AVALIAÇÕES -->
<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">
            <i class="fa fa-list-check"></i>
            Lista para Avaliação
        </div>
        <span style="font-size:0.8rem;color:var(--text-muted)">
            <?= $total ?> registos
        </span>
    </div>

    <?php if (empty($projectos)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa fa-clipboard-check"></i></div>
        <div class="empty-state-title">Nenhum projecto a exibir</div>
        <div class="empty-state-text">Não existem projectos correspondentes aos filtros actuais.</div>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Projecto</th>
                    <th>Submetido por</th>
                    <th>Tipo / Área</th>
                    <th>Sua Nota</th>
                    <th>Decisão</th>
                    <th>Acções</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($projectos as $p): 
                $foiAvaliado = !is_null($p['nota']);
            ?>
            <tr>
                <td>
                    <a href="/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=<?= $p['id'] ?>"
                       style="font-weight:600;color:var(--primary);text-decoration:none;display:block;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                       title="<?= htmlspecialchars($p['titulo']) ?>">
                        <?= htmlspecialchars($p['titulo']) ?>
                    </a>
                    <small class="text-muted">Submetido: <?= date('d/m/Y', strtotime($p['criado_em'])) ?></small>
                </td>
                <td>
                    <div style="font-weight:500;font-size:0.85rem"><?= htmlspecialchars($p['autor']) ?></div>
                </td>
                <td>
                    <span style="font-size:0.75rem;padding:3px 8px;border-radius:4px;background:var(--surface-2);font-weight:600;color:var(--text-primary)">
                        <?= strtoupper($p['tipo']) ?>
                    </span>
                    <br>
                    <small class="text-muted"><?= ucfirst($p['area_tematica'] ?? '—') ?></small>
                </td>
                <td>
                    <?php if ($foiAvaliado): ?>
                        <span style="font-weight:800;color:<?= $p['nota']>=7?'var(--success)':($p['nota']>=4?'var(--warning)':'var(--danger)') ?>;font-size:1.1rem">
                            <?= $p['nota'] ?>/10
                        </span>
                        <br>
                        <small class="text-muted"><?= date('d/m', strtotime($p['avaliado_em'])) ?></small>
                    <?php else: ?>
                        <span style="color:var(--text-muted)">Sem nota</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($foiAvaliado): ?>
                        <?php
                            $corDecisao = [
                                'submetido'    => 'secondary',
                                'em_avaliacao' => 'warning',
                                'aprovado'     => 'success',
                                'rejeitado'    => 'danger',
                                'incubado'     => 'primary',
                                'fundo_investimento' => 'success'
                            ][$p['decisao'] ?? $p['estado']] ?? 'info';
                            
                            $labelEstado = [
                                'submetido'    => 'Submetido',
                                'em_avaliacao' => 'A Avaliar',
                                'aprovado'     => 'Aprovado',
                                'rejeitado'    => 'Rejeitado',
                                'incubado'     => 'Incubado',
                                'fundo_investimento' => 'Financiamento'
                            ][$p['decisao'] ?? $p['estado']] ?? $p['estado'];
                        ?>
                        <span class="badge-estado badge-<?= $corDecisao ?>">
                            <?= ucfirst(str_replace('_',' ',$labelEstado)) ?>
                        </span>
                    <?php else: ?>
                        <span class="badge-estado badge-warning">
                            <i class="fa fa-clock me-1"></i> Pendente
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-2">
                        <?php if ($p['estado'] === 'submetido'): ?>
                            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST">
                                <input type="hidden" name="action" value="mudar_estado">
                                <input type="hidden" name="id_projeto" value="<?= $p['id'] ?>">
                                <input type="hidden" name="estado" value="em_avaliacao">
                                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                                <button type="submit" class="btn-primary-custom" style="padding:6px 16px;font-size:0.8rem;">
                                    <i class="fa fa-play me-1"></i> Iniciar Avaliação
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=<?= $p['id'] ?>#modalAvaliar"
                               class="btn-primary-custom" style="padding:6px 16px;font-size:0.8rem;text-decoration:none;display:inline-block"
                               title="<?= $foiAvaliado ? 'Editar Avaliação' : 'Avaliar Agora' ?>">
                                <i class="fa <?= $foiAvaliado ? 'fa-pen' : 'fa-star' ?>"></i>
                                <?= $foiAvaliado ? 'Editar' : 'Avaliar' ?>
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
            'decisao' => $filtroDecisao, 'q' => $filtroBusca
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

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
