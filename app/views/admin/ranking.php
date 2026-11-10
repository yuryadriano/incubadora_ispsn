<?php
// app/views/admin/ranking.php
// Ranking de Startups — 1 Único Cartão Limpo

require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$tituloPagina = 'Ranking Startups';
$paginaActiva = 'ranking';

$realPerfil     = $_SESSION['usuario_perfil'] ?? 'utilizador';
$isAdminOrSuper = in_array($realPerfil, ['admin', 'superadmin'], true);
if ($isAdminOrSuper && !empty($_GET['id_usuario'])) {
    $idUsuario = (int)$_GET['id_usuario'];
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
} else {
    $idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
}

$labelsFase = [
    'ideacao'            => 'Ideação',
    'validacao'          => 'Validação',
    'mvp'                => 'MVP',
    'tracao'             => 'Tração',
    'mercado'            => 'Mercado',
    'fundo_investimento' => 'Financiado',
];

$sql = "SELECT p.*, u.nome AS autor_nome
        FROM projetos p
        JOIN usuarios u ON u.id = p.criado_por
        WHERE p.estado IN ('incubado', 'fundo_investimento', 'concluido', 'aprovado', 'em_avaliacao', 'submetido')
        ORDER BY p.pontos DESC, p.criado_em DESC
        LIMIT 25";
$resTop      = $mysqli->query($sql);
$topProjetos = $resTop ? $resTop->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/../partials/_layout.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Ranking de Startups</div>
        <div class="page-header-sub">Classificação por Startup Points (SP) e avanço de maturidade.</div>
    </div>
</div>

<!-- 1 ÚNICO CARTÃO COM A TABELA DE RANKING -->
<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">
            <i class="fa fa-trophy" style="color:var(--primary)"></i>
            Classificação Geral (<?= count($topProjetos) ?>)
        </div>
        <input type="search" id="filtroTextoRanking" class="form-control form-control-sm" style="width: 220px;" placeholder="Pesquisar startup..." oninput="filtrarTabelaRanking()">
    </div>
    <div class="card-body-custom p-0">
        <?php if (empty($topProjetos)): ?>
            <div class="empty-state py-5">
                <div class="empty-state-title">Sem startups classificadas</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:var(--surface-2); font-size:0.74rem; text-transform:uppercase; color:var(--text-muted);">
                        <tr>
                            <th class="ps-4" style="width: 72px;">#</th>
                            <th>Startup</th>
                            <th>Líder</th>
                            <th>Fase</th>
                            <th class="text-end pe-4">Pontos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProjetos as $idx => $p):
                            $pos     = $idx + 1;
                            $isMinha = ((int)($p['criado_por'] ?? 0) === (int)($idUsuario ?? 0));
                            $faseKey = (string)($p['fase'] ?? 'ideacao');
                            $faseLbl = $labelsFase[$faseKey] ?? ucfirst($faseKey);
                        ?>
                            <tr class="linha-ranking"
                                data-search="<?= htmlspecialchars(strtolower((string)$p['titulo'] . ' ' . (string)$p['autor_nome']), ENT_QUOTES, 'UTF-8') ?>"
                                style="<?= $isMinha ? 'background: rgba(245, 158, 11, 0.08);' : '' ?>">
                                <td class="ps-4 fw-bold"><?= $pos ?>.º</td>
                                <td class="fw-bold">
                                    <?= htmlspecialchars((string)$p['titulo']) ?>
                                    <?php if ($isMinha): ?>
                                        <span class="badge-estado badge-avaliacao ms-1">Sua</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars((string)$p['autor_nome']) ?></td>
                                <td><span class="badge-estado badge-submetido"><?= htmlspecialchars($faseLbl) ?></span></td>
                                <td class="text-end pe-4 fw-bold" style="color:var(--primary);"><?= (int)($p['pontos'] ?? 0) ?> SP</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filtrarTabelaRanking() {
    const q = (document.getElementById('filtroTextoRanking')?.value || '').toLowerCase().trim();
    document.querySelectorAll('.linha-ranking').forEach(tr => {
        const s = tr.getAttribute('data-search') || '';
        tr.style.display = (!q || s.includes(q)) ? '' : 'none';
    });
}
</script>

<?php include __DIR__ . '/../partials/_layout_end.php'; ?>
