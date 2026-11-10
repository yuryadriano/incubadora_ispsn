<?php
// app/views/admin/ranking.php
// Leaderboard Interativo & Pódio de Startups — Incubadora ISPSN

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

$labelsEstado = [
    'submetido'          => 'Submetido (Em Triagem)',
    'em_avaliacao'       => 'Em Avaliação Técnica',
    'aprovado'           => 'Aprovado para Incubação',
    'rejeitado'          => 'Rejeitado',
    'incubado'           => 'Em Incubação',
    'fundo_investimento' => 'Pronto p/ Investimento',
    'concluido'          => 'Graduado',
];

$labelsFase = [
    'ideacao'            => 'Ideação',
    'validacao'          => 'Validação',
    'mvp'                => 'MVP',
    'tracao'             => 'Tração',
    'mercado'            => 'Mercado',
    'fundo_investimento' => 'Financiado',
];

// Buscar projetos ativos ordenados por pontos
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

<div class="std-painel">

    <!-- PAGE HEADER NATIVO DO SISTEMA -->
    <div class="page-header">
        <div>
            <div class="page-header-title">
                <i class="fa-solid fa-trophy me-2" style="color:var(--primary)"></i>
                Ranking de Startups &amp; Mérito Académico
            </div>
            <div class="page-header-sub">
                Leaderboard Oficial ISPSN · <?= count($topProjetos) ?> startup(s) em competição por Startup Points (SP).
            </div>
        </div>
    </div>

    <!-- PÓDIO TOP 3 STARTUPS (USANDO KPI-CARD NATIVO) -->
    <?php if (count($topProjetos) >= 1):
        $podium = array_slice($topProjetos, 0, 3);
        $medals = [
            0 => ['lbl' => '1.º Lugar · Ouro',   'cor' => '#F59E0B', 'icon' => 'fa-crown', 'iconCls' => 'amber'],
            1 => ['lbl' => '2.º Lugar · Prata',  'cor' => '#64748B', 'icon' => 'fa-medal', 'iconCls' => 'blue'],
            2 => ['lbl' => '3.º Lugar · Bronze', 'cor' => '#D97706', 'icon' => 'fa-award', 'iconCls' => 'amber'],
        ];
    ?>
        <div class="kpi-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
            <?php foreach ($podium as $iP => $pod):
                $m = $medals[$iP];
                $fLbl = $labelsFase[$pod['fase'] ?? 'ideacao'] ?? ucfirst((string)($pod['fase'] ?? 'Ideação'));
            ?>
                <div class="kpi-card" style="--kpi-color:<?= $m['cor'] ?>">
                    <div class="kpi-top">
                        <span class="kpi-label"><?= $m['lbl'] ?></span>
                        <div class="kpi-icon <?= $m['iconCls'] ?>"><i class="fa-solid <?= $m['icon'] ?>"></i></div>
                    </div>
                    <div class="fw-bold mb-1" style="font-size:1.05rem;color:var(--text);">
                        <?= htmlspecialchars((string)$pod['titulo'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="text-muted mb-2" style="font-size:0.78rem;">
                        <i class="fa-regular fa-user me-1"></i><?= htmlspecialchars((string)$pod['autor_nome'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="kpi-footer justify-content-between">
                        <span class="kpi-badge up"><?= (int)($pod['pontos'] ?? 0) ?> SP</span>
                        <span>Fase: <?= htmlspecialchars($fLbl, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- TABELA INTERATIVA COM PESQUISA E FILTRO POR FASE -->
    <div class="card-custom">
        <div class="card-header-custom">
            <div class="card-title-custom">
                <i class="fa-solid fa-ranking-star" style="color:var(--primary)"></i>
                Tabela de Mérito das Startups
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <input type="search" id="filtroTextoRanking" class="form-control form-control-sm" style="width: 210px;" placeholder="Pesquisar startup ou líder..." oninput="filtrarTabelaRanking()">
                <select id="filtroFaseRanking" class="form-select form-select-sm" style="width: 155px;" onchange="filtrarTabelaRanking()">
                    <option value="">Todas as Fases</option>
                    <?php foreach ($labelsFase as $fk => $fl): ?>
                        <option value="<?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fl, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card-body-custom p-0">
            <?php if (empty($topProjetos)): ?>
                <div class="empty-state py-4">
                    <div class="empty-state-icon"><i class="fa-solid fa-trophy"></i></div>
                    <div class="empty-state-title">Sem startups classificadas</div>
                    <div class="empty-state-desc">Ainda não existem startups classificadas no ranking.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabelaRankingStartups">
                        <thead style="background:var(--surface-2);font-size:0.75rem;text-transform:uppercase;color:var(--text-muted)">
                            <tr>
                                <th class="ps-4" style="width: 72px;">Posição</th>
                                <th>Startup &amp; Líder</th>
                                <th>Área</th>
                                <th>Fase Atual</th>
                                <th>Estado</th>
                                <th class="text-end pe-4">Pontuação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProjetos as $idx => $p):
                                $pos     = $idx + 1;
                                $isMinha = ((int)($p['criado_por'] ?? 0) === (int)($idUsuario ?? 0));
                                $estKey  = (string)($p['estado'] ?? '');
                                $faseKey = (string)($p['fase'] ?? 'ideacao');
                                $estLbl  = $labelsEstado[$estKey] ?? $estKey;
                                $faseLbl = $labelsFase[$faseKey] ?? ucfirst($faseKey);

                                $badgeEst = 'badge-submetido';
                                if (in_array($estKey, ['incubado', 'fundo_investimento', 'concluido', 'aprovado'], true)) {
                                    $badgeEst = 'badge-incubado';
                                } elseif ($estKey === 'em_avaliacao' || $estKey === 'submetido') {
                                    $badgeEst = 'badge-avaliacao';
                                }
                            ?>
                                <tr class="linha-ranking"
                                    data-fase="<?= htmlspecialchars($faseKey, ENT_QUOTES, 'UTF-8') ?>"
                                    data-search="<?= htmlspecialchars(strtolower((string)$p['titulo'] . ' ' . (string)$p['autor_nome']), ENT_QUOTES, 'UTF-8') ?>"
                                    style="<?= $isMinha ? 'background: rgba(245, 158, 11, 0.08);' : '' ?>">
                                    <td class="ps-4 fw-bold">
                                        <?php if ($pos === 1): ?>
                                            <span class="badge-estado badge-avaliacao">#1</span>
                                        <?php elseif ($pos === 2 || $pos === 3): ?>
                                            <span class="badge-estado badge-submetido">#<?= $pos ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">#<?= $pos ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold" style="font-size:0.88rem;color:var(--text);">
                                            <?= htmlspecialchars((string)$p['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($isMinha): ?>
                                                <span class="badge-estado badge-avaliacao ms-1">A sua startup</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted" style="font-size:0.75rem;">
                                            <?= htmlspecialchars((string)$p['autor_nome'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted" style="font-size:0.8rem;">
                                            <?= htmlspecialchars(ucfirst((string)($p['area_tematica'] ?? 'Tecnologia')), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-estado badge-submetido"><?= htmlspecialchars($faseLbl, ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-estado <?= $badgeEst ?>"><?= htmlspecialchars($estLbl, ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <span class="fw-bold" style="color:var(--primary);">
                                            <?= (int)($p['pontos'] ?? 0) ?> SP
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function filtrarTabelaRanking() {
    const q = (document.getElementById('filtroTextoRanking')?.value || '').toLowerCase().trim();
    const f = (document.getElementById('filtroFaseRanking')?.value || '').trim();

    document.querySelectorAll('.linha-ranking').forEach(tr => {
        const s = tr.getAttribute('data-search') || '';
        const fase = tr.getAttribute('data-fase') || '';
        const matchQ = !q || s.includes(q);
        const matchF = !f || fase === f;
        tr.style.display = (matchQ && matchF) ? '' : 'none';
    });
}
</script>

<?php include __DIR__ . '/../partials/_layout_end.php'; ?>
