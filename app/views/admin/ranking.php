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

    <!-- HERO DO LEADERBOARD -->
    <header class="std-command-hero">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 position-relative" style="z-index:2;">
            <span class="std-live-pill" style="background: rgba(245,158,11,0.2); color: #FCD34D; border: 1px solid rgba(245,158,11,0.45);">
                <i class="fa-solid fa-trophy" style="color: #FBBF24;"></i>
                <span>Leaderboard Oficial ISPSN</span>
            </span>
            <span class="std-live-pill" style="background: rgba(255,255,255,0.08); color: #E2E8F0; border: 1px solid rgba(255,255,255,0.14);">
                <span><?= count($topProjetos) ?> startup(s) em competição</span>
            </span>
        </div>
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 position-relative" style="z-index:2;">
            <div>
                <h1 style="font-size: 1.65rem; font-weight: 800; color: #FFFFFF; margin: 4px 0 6px;">
                    Ranking de Startups &amp; Mérito Académico
                </h1>
                <div style="font-size: 0.84rem; color: #CBD5E1;">
                    Classificação em tempo real por <strong>Startup Points (SP)</strong> atribuídos por avanço de maturidade e metas validadas.
                </div>
            </div>
        </div>
    </header>

    <!-- PÓDIO TOP 3 STARTUPS -->
    <?php if (count($topProjetos) >= 1):
        $podium = array_slice($topProjetos, 0, 3);
        $medals = [
            0 => ['cls' => 'rank-1', 'lbl' => '1.º Lugar · Ouro',   'cor' => '#F59E0B', 'icon' => 'fa-crown'],
            1 => ['cls' => 'rank-2', 'lbl' => '2.º Lugar · Prata',  'cor' => '#64748B', 'icon' => 'fa-medal'],
            2 => ['cls' => 'rank-3', 'lbl' => '3.º Lugar · Bronze', 'cor' => '#D97706', 'icon' => 'fa-award'],
        ];
    ?>
        <div class="std-podium-grid">
            <?php foreach ($podium as $iP => $pod):
                $m = $medals[$iP];
                $fLbl = $labelsFase[$pod['fase'] ?? 'ideacao'] ?? ucfirst((string)($pod['fase'] ?? 'Ideação'));
            ?>
                <div class="std-podium-card <?= $m['cls'] ?>">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="sp-chip sp-chip-warn" style="color: <?= $m['cor'] ?>;">
                            <i class="fa-solid <?= $m['icon'] ?>"></i>
                            <span><?= $m['lbl'] ?></span>
                        </span>
                        <span class="sp-code fw-bold" style="font-size: 1.05rem; color: var(--sp-accent);">
                            <?= (int)($pod['pontos'] ?? 0) ?> SP
                        </span>
                    </div>
                    <h3 class="fw-bold mb-1" style="font-size: 1.05rem; color: var(--sp-text);">
                        <?= htmlspecialchars((string)$pod['titulo'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>
                    <div style="font-size: 0.78rem; color: var(--sp-text-muted); margin-bottom: 10px;">
                        <i class="fa-regular fa-user me-1"></i><?= htmlspecialchars((string)$pod['autor_nome'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="border-color: var(--sp-border) !important; font-size: 0.75rem;">
                        <span class="sp-chip sp-chip-neutral">Fase: <?= htmlspecialchars($fLbl, ENT_QUOTES, 'UTF-8') ?></span>
                        <span style="color: var(--sp-text-sec);"><?= htmlspecialchars(ucfirst((string)($pod['area_tematica'] ?? 'Tecnologia')), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- TABELA INTERATIVA COM PESQUISA E FILTRO POR FASE -->
    <section class="sp-surface-primary">
        <div class="sp-card-head">
            <div class="sp-card-head-title">
                <span class="sp-icon-badge"><i class="fa-solid fa-ranking-star"></i></span>
                <div>
                    <div class="sp-kicker">Classificação Geral</div>
                    <h2 class="fw-bold m-0" style="font-size: var(--sp-fs-lg);">Tabela de Mérito das Startups</h2>
                </div>
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

        <?php if (empty($topProjetos)): ?>
            <div class="sp-empty-line">
                <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                <span>Ainda não existem startups classificadas no ranking.</span>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="sp-table" id="tabelaRankingStartups">
                    <thead>
                        <tr>
                            <th style="width: 72px;">Posição</th>
                            <th>Startup &amp; Líder</th>
                            <th>Área</th>
                            <th>Fase Atual</th>
                            <th>Estado</th>
                            <th class="text-end">Pontuação</th>
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

                            $chipEst = 'sp-chip-neutral';
                            if (in_array($estKey, ['incubado', 'fundo_investimento', 'concluido', 'aprovado'], true)) {
                                $chipEst = 'sp-chip-ok';
                            } elseif ($estKey === 'em_avaliacao' || $estKey === 'submetido') {
                                $chipEst = 'sp-chip-warn';
                            }
                        ?>
                            <tr class="linha-ranking"
                                data-fase="<?= htmlspecialchars($faseKey, ENT_QUOTES, 'UTF-8') ?>"
                                data-search="<?= htmlspecialchars(strtolower((string)$p['titulo'] . ' ' . (string)$p['autor_nome']), ENT_QUOTES, 'UTF-8') ?>"
                                style="<?= $isMinha ? 'background: var(--sp-warn-bg);' : '' ?>">
                                <td class="sp-code fw-bold">
                                    <?php if ($pos === 1): ?>
                                        <span class="sp-chip sp-chip-warn">#1</span>
                                    <?php elseif ($pos === 2 || $pos === 3): ?>
                                        <span class="sp-chip sp-chip-neutral">#<?= $pos ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--sp-text-muted);">#<?= $pos ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold" style="color: var(--sp-text);">
                                        <?= htmlspecialchars((string)$p['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($isMinha): ?>
                                            <span class="sp-chip sp-chip-warn ms-1">A sua startup</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: var(--sp-fs-xs); color: var(--sp-text-muted);">
                                        <?= htmlspecialchars((string)$p['autor_nome'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 0.8rem; color: var(--sp-text-sec);">
                                        <?= htmlspecialchars(ucfirst((string)($p['area_tematica'] ?? 'Tecnologia')), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="sp-chip sp-chip-neutral"><?= htmlspecialchars($faseLbl, ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td>
                                    <span class="sp-chip <?= $chipEst ?>"><?= htmlspecialchars($estLbl, ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="text-end">
                                    <span class="sp-code fw-bold" style="color: var(--sp-accent);">
                                        <?= (int)($p['pontos'] ?? 0) ?> SP
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

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
