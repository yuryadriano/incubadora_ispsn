<?php
// app/views/admin/projetos.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin','superadmin','funcionario']);

$tituloPagina = 'Gestão de Projectos';
$paginaActiva = 'projetos';

// ── Filtros via GET ────────────────────────
$filtroEstado = $_GET['estado']  ?? '';
$filtroTipo   = $_GET['tipo']    ?? '';
$filtroBusca  = trim($_GET['q']  ?? '');
$pagina       = max(1, (int)($_GET['pag'] ?? 1));
$porPagina    = 15;
$offset       = ($pagina - 1) * $porPagina;

// ── Construir WHERE dinâmico ───────────────
$where  = ['1=1'];
$params = [];
$types  = '';

if ($filtroEstado) { $where[] = 'p.estado = ?';  $params[] = $filtroEstado; $types .= 's'; }
if ($filtroTipo)   { $where[] = 'p.tipo = ?';    $params[] = $filtroTipo;   $types .= 's'; }
if ($filtroBusca)  {
    $like = "%$filtroBusca%";
    $where[] = '(p.titulo LIKE ? OR u.nome LIKE ?)';
    $params[] = $like; $params[] = $like;
    $types .= 'ss';
}
$whereSQL = implode(' AND ', $where);

// ── Contar total ───────────────────────────
$sqlCount = "SELECT COUNT(*) n FROM projetos p JOIN usuarios u ON u.id=p.criado_por WHERE $whereSQL";
$stmtCount = $mysqli->prepare($sqlCount);
if ($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$total = (int)$stmtCount->get_result()->fetch_assoc()['n'];
$totalPaginas = max(1, ceil($total / $porPagina));

// ── Buscar projectos ───────────────────────
$sqlMain = "
    SELECT p.id, p.titulo, p.tipo, p.area_tematica, p.estado, p.criado_em,
           u.nome autor, u.email email_autor,
           (SELECT COUNT(*) FROM comentarios_projetos WHERE id_projeto=p.id) n_comentarios,
           (SELECT pontuacao_total FROM avaliacoes WHERE id_projeto=p.id LIMIT 1) nota
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
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

// ── Contadores rápidos ─────────────────────
$contadores = [];
$rc = $mysqli->query("SELECT estado, COUNT(*) n FROM projetos GROUP BY estado");
if ($rc) while ($row = $rc->fetch_assoc()) $contadores[$row['estado']] = (int)$row['n'];

// ── Flash messages ─────────────────────────
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
            <i class="fa fa-inbox me-2" style="color:var(--primary)"></i>
            Gestão de Projectos
        </div>
        <div class="page-header-sub"><?= $total ?> projecto(s) encontrado(s)</div>
    </div>
</div>

<!-- CONTADOR RÁPIDO POR ESTADO -->
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:24px">
    <?php
    $estadosMeta = [
        'em_analise'   => ['label'=>'Em Análise',   'cor'=>'#F59E0B', 'icon'=>'fa-hourglass-half'],
        'em_andamento' => ['label'=>'Em Andamento',  'cor'=>'#3B82F6', 'icon'=>'fa-circle-play'],
        'concluido'    => ['label'=>'Concluídos',    'cor'=>'#10B981', 'icon'=>'fa-circle-check'],
        'cancelado'    => ['label'=>'Cancelados',    'cor'=>'#EF4444', 'icon'=>'fa-circle-xmark'],
    ];
    foreach ($estadosMeta as $est => $meta):
        $n = $contadores[$est] ?? 0;
        $ativo = ($filtroEstado === $est) ? 'box-shadow:0 0 0 2px '.$meta['cor'].';' : '';
    ?>
    <a href="?estado=<?= $est ?><?= $filtroTipo ? '&tipo='.$filtroTipo : '' ?><?= $filtroBusca ? '&q='.urlencode($filtroBusca) : '' ?>"
       style="text-decoration:none">
        <div class="kpi-card" style="--kpi-color:<?= $meta['cor'] ?>;<?= $ativo ?>">
            <div class="kpi-icon"><i class="fa <?= $meta['icon'] ?>"></i></div>
            <div class="kpi-value"><?= $n ?></div>
            <div class="kpi-label"><?= $meta['label'] ?></div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php if ($filtroEstado): ?>
    <a href="?" style="text-decoration:none">
        <div class="kpi-card" style="--kpi-color:#94A3B8">
            <div class="kpi-icon"><i class="fa fa-filter-circle-xmark"></i></div>
            <div class="kpi-value">×</div>
            <div class="kpi-label">Limpar Filtro</div>
        </div>
    </a>
    <?php endif; ?>
</div>

<!-- FILTROS + BUSCA -->
<div class="card-custom mb-4">
    <div class="card-body-custom">
        <form method="get" class="d-flex gap-3 flex-wrap align-items-end">
            <div style="flex:1;min-width:220px">
                <label class="form-label-custom">Pesquisar</label>
                <input type="text" name="q" class="form-control-custom"
                       placeholder="Título ou nome do autor…"
                       value="<?= htmlspecialchars($filtroBusca) ?>">
            </div>
            <div style="min-width:160px">
                <label class="form-label-custom">Estado</label>
                <select name="estado" class="form-control-custom">
                    <option value="">Todos os estados</option>
                    <option value="em_analise"   <?= $filtroEstado==='em_analise'   ? 'selected':'' ?>>Em Análise</option>
                    <option value="em_andamento" <?= $filtroEstado==='em_andamento' ? 'selected':'' ?>>Em Andamento</option>
                    <option value="concluido"    <?= $filtroEstado==='concluido'    ? 'selected':'' ?>>Concluído</option>
                    <option value="cancelado"    <?= $filtroEstado==='cancelado'    ? 'selected':'' ?>>Cancelado</option>
                </select>
            </div>
            <div style="min-width:140px">
                <label class="form-label-custom">Tipo</label>
                <select name="tipo" class="form-control-custom">
                    <option value="">Todos os tipos</option>
                    <option value="incubado" <?= $filtroTipo==='incubado' ? 'selected':'' ?>>Incubado</option>
                    <option value="pfc"      <?= $filtroTipo==='pfc'      ? 'selected':'' ?>>PFC</option>
                    <option value="artigo"   <?= $filtroTipo==='artigo'   ? 'selected':'' ?>>Artigo</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn-primary-custom">
                    <i class="fa fa-magnifying-glass"></i> Filtrar
                </button>
                <a href="?" class="btn-ghost"><i class="fa fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- TABELA DE PROJECTOS -->
<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">
            <i class="fa fa-table-list"></i>
            Lista de Startups <?= $filtroEstado ? '— '.ucfirst(str_replace('_',' ',$filtroEstado)) : '' ?>
            <?= $filtroTipo ? '('.strtoupper($filtroTipo).')' : '' ?>
        </div>
        <span style="font-size:0.8rem;color:var(--text-muted)">
            Página <?= $pagina ?> de <?= $totalPaginas ?>
        </span>
    </div>

    <?php if (empty($projectos)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa fa-inbox"></i></div>
        <div class="empty-state-title">Nenhuma Startup encontrada</div>
        <div class="empty-state-text">Ajuste os filtros ou aguarde novas submissões dos empreendedores</div>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Título</th>
                    <th>Tipo</th>
                    <th>Área</th>
                    <th>Autor</th>
                    <th>Estado</th>
                    <th>Nota</th>
                    <th>Data</th>
                    <th>Acções</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($projectos as $p): ?>
            <tr>
                <td style="color:var(--text-muted);font-size:0.8rem">#<?= $p['id'] ?></td>
                <td>
                    <a href="/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=<?= $p['id'] ?>"
                       style="font-weight:600;color:var(--primary);text-decoration:none;display:block;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                       title="<?= htmlspecialchars($p['titulo']) ?>">
                        <?= htmlspecialchars($p['titulo']) ?>
                    </a>
                    <?php if ($p['n_comentarios'] > 0): ?>
                    <small style="color:var(--text-muted)">
                        <i class="fa fa-comment" style="color:#8B5CF6"></i>
                        <?= $p['n_comentarios'] ?> comentário(s)
                    </small>
                    <?php endif; ?>
                </td>
                <td>
                    <span style="font-size:0.72rem;padding:3px 9px;border-radius:20px;background:var(--surface-2);font-weight:600;color:var(--text-secondary)">
                        <?= strtoupper($p['tipo']) ?>
                    </span>
                </td>
                <td><small class="text-muted"><?= ucfirst($p['area_tematica'] ?? '—') ?></small></td>
                <td>
                    <div style="font-weight:500;font-size:0.85rem"><?= htmlspecialchars($p['autor']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($p['email_autor']) ?></small>
                </td>
                <td>
                    <span class="badge-estado badge-<?= $p['estado'] ?>">
                        <?= ucfirst(str_replace('_',' ',$p['estado'])) ?>
                    </span>
                </td>
                <td>
                    <?php if ($p['nota'] !== null): ?>
                        <span style="font-weight:700;color:<?= $p['nota']>=7?'var(--success)':($p['nota']>=4?'var(--warning)':'var(--danger)') ?>">
                            <?= $p['nota'] ?>/10
                        </span>
                    <?php else: ?>
                        <span style="color:var(--text-muted);font-size:0.8rem">—</span>
                    <?php endif; ?>
                </td>
                <td><small class="text-muted"><?= date('d/m/Y', strtotime($p['criado_em'])) ?></small></td>
                <td>
                    <div class="d-flex gap-2">
                        <a href="/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=<?= $p['id'] ?>"
                           class="btn-primary-custom" style="padding:6px 12px;font-size:0.78rem"
                           title="Ver detalhes">
                            <i class="fa fa-eye"></i>
                        </a>
                        <!-- Quick estado change -->
                        <div class="dropdown">
                            <button class="btn-ghost" style="padding:6px 12px;font-size:0.78rem"
                                    data-bs-toggle="dropdown" title="Mudar estado">
                                <i class="fa fa-sliders"></i>
                            </button>
                            <ul class="dropdown-menu shadow-sm border-0">
                                <?php foreach (['em_analise'=>'Em Análise','em_andamento'=>'Em Andamento','concluido'=>'Concluído','cancelado'=>'Cancelado'] as $est => $lbl): ?>
                                <li>
                                    <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
                                        <input type="hidden" name="action" value="mudar_estado">
                                        <input type="hidden" name="id_projeto" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="estado" value="<?= $est ?>">
                                        <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/admin/projetos.php<?= $_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : '' ?>">
                                        <button type="submit" class="dropdown-item"
                                                style="font-size:0.82rem;<?= $p['estado']===$est ? 'font-weight:700;color:var(--primary)' : '' ?>">
                                            <?php if ($p['estado']===$est): ?><i class="fa fa-check me-1"></i><?php endif; ?>
                                            <?= $lbl ?>
                                        </button>
                                    </form>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
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
            'estado' => $filtroEstado, 'tipo' => $filtroTipo, 'q' => $filtroBusca
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
