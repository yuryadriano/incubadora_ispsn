<?php
require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$tituloPagina = 'Ranking Startups';
$paginaActiva = 'ranking';

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
        WHERE p.estado IN ('incubado', 'fundo_investimento', 'concluido', 'aprovado', 'em_avaliacao')
        ORDER BY p.pontos DESC, p.criado_em DESC
        LIMIT 15";
$resTop      = $mysqli->query($sql);
$topProjetos = $resTop ? $resTop->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/../partials/_layout.php';
?>

<div class="std-painel">

    <header class="sp-header">
        <div class="sp-header-top">
            <div class="sp-kicker">Incubadora Académica do ISPSN</div>
            <span class="sp-chip sp-chip-warn sp-num">
                <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                <span><?= count($topProjetos) ?> startup<?= count($topProjetos) !== 1 ? 's' : '' ?> listada<?= count($topProjetos) !== 1 ? 's' : '' ?></span>
            </span>
        </div>
        <h1 class="sp-title">Ranking de Startups</h1>
        <div class="sp-meta-row mt-1">
            <span>Classificação por Startup Points (SP) atribuídos por metas e tarefas validadas no programa.</span>
        </div>
    </header>

    <section class="sp-surface-primary">
        <?php if (empty($topProjetos)): ?>
            <div class="sp-empty-line">
                <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                <span>Ainda não existem startups classificadas no ranking.</span>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="sp-table">
                    <thead>
                        <tr>
                            <th style="width: 72px;">Posição</th>
                            <th>Startup</th>
                            <th>Fase Actual</th>
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
                            } elseif ($estKey === 'em_avaliacao') {
                                $chipEst = 'sp-chip-warn';
                            }
                        ?>
                            <tr style="<?= $isMinha ? 'background: var(--sp-warn-bg);' : '' ?>">
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

<?php include __DIR__ . '/../partials/_layout_end.php'; ?>
