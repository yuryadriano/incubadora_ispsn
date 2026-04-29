<?php
require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$tituloPagina = "Cúpula da Inovação";
$paginaActiva = "ranking";

// Buscar top 10 projetos por pontos
$sql = "SELECT p.*, u.nome as autor_nome 
        FROM projetos p 
        JOIN usuarios u ON u.id = p.criado_por 
        ORDER BY p.pontos DESC, p.criado_em DESC 
        LIMIT 10";
$topProjetos = $mysqli->query($sql)->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../partials/_layout.php';
?>

<style>
    .ranking-dashboard-wrapper {
        max-width: 1200px;
        margin: 0 auto;
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        border: 1px solid rgba(0,0,0,0.05);
        overflow: hidden;
    }

    .ranking-header-section {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        padding: 30px 40px;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ranking-body-section {
        padding: 30px;
        background: #fdfdfd;
    }

    .podium-stage {
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 20px;
        margin-bottom: 40px;
        background: #f8fafc;
        padding: 40px 20px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
    }

    .podium-box {
        flex: 1;
        max-width: 240px;
        text-align: center;
        position: relative;
    }

    .podium-card-v3 {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        border: 1px solid #f1f5f9;
        transition: transform 0.3s ease;
    }

    .podium-box:hover .podium-card-v3 {
        transform: translateY(-5px);
    }

    .podium-num {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: -42px auto 12px;
        font-weight: 900;
        color: #fff;
        border: 4px solid #f8fafc;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .rank-gold { background: #fbbf24; }
    .rank-silver { background: #94a3b8; }
    .rank-bronze { background: #b45309; }

    .startup-name {
        font-weight: 800;
        font-size: 0.95rem;
        color: #1e293b;
        margin-bottom: 2px;
    }

    .startup-pts {
        font-weight: 800;
        font-size: 1.2rem;
        color: var(--primary);
    }

    .table-v3 {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px;
    }

    .table-v3 tr td {
        background: #fff;
        padding: 16px;
        border-top: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
    }

    .table-v3 tr td:first-child { border-left: 1px solid #f1f5f9; border-radius: 12px 0 0 12px; }
    .table-v3 tr td:last-child { border-right: 1px solid #f1f5f9; border-radius: 0 12px 12px 0; }

    .table-v3 tr:hover td {
        background: #fdfaf5;
        border-color: var(--primary-subtle);
    }

    .badge-v3 {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    @media (max-width: 768px) {
        .podium-stage { flex-direction: column; align-items: center; }
        .podium-box { width: 100%; max-width: 300px; margin-top: 40px; }
    }
</style>

<div class="ranking-dashboard-wrapper">
    <!-- HEADER INTEGRADO -->
    <div class="ranking-header-section">
        <div>
            <h4 class="mb-1" style="font-weight: 800; letter-spacing: -0.5px;">
                <i class="fa fa-award me-2 text-warning"></i> Cúpula da Inovação
            </h4>
            <div class="small opacity-75">As startups mais produtivas e resilientes do ISPSN</div>
        </div>
        <div class="text-end">
            <span class="badge bg-primary px-3 py-2 rounded-pill" style="font-size: 0.75rem;">Sincronizado em Tempo Real</span>
        </div>
    </div>

    <div class="ranking-body-section">
        
        <!-- PÓDIO COMPACTO EM EST ÁGIO -->
        <div class="podium-stage">
            
            <!-- 2º Lugar -->
            <?php if (count($topProjetos) >= 2): $p2 = $topProjetos[1]; ?>
            <div class="podium-box">
                <div class="podium-num rank-silver">2</div>
                <div class="podium-card-v3" style="height: 160px;">
                    <div class="startup-name"><?= htmlspecialchars($p2['titulo']) ?></div>
                    <div class="text-muted extra-small mb-3"><?= htmlspecialchars($p2['autor_nome']) ?></div>
                    <div class="startup-pts"><?= $p2['pontos'] ?> <span class="small opacity-50">SP</span></div>
                    <div class="badge-v3 mt-2" style="background:#f1f5f9; color:#475569;">Estável</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 1º Lugar -->
            <?php if (count($topProjetos) >= 1): $p1 = $topProjetos[0]; ?>
            <div class="podium-box" style="z-index: 2;">
                <div class="podium-num rank-gold">
                    <i class="fa fa-crown" style="position: absolute; top: -15px; color: #fbbf24; font-size: 1.1rem; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));"></i>
                    1
                </div>
                <div class="podium-card-v3" style="height: 200px; border: 2px solid #fbbf24; box-shadow: 0 10px 25px rgba(251,191,36,0.15);">
                    <div class="startup-name" style="font-size: 1.1rem; color: #fbbf24;">Líder do Eixo</div>
                    <div class="fw-bold mb-1" style="font-size: 1.15rem; color: #0f172a;"><?= htmlspecialchars($p1['titulo']) ?></div>
                    <div class="text-muted extra-small mb-3"><?= htmlspecialchars($p1['autor_nome']) ?></div>
                    <div class="startup-pts" style="font-size: 1.8rem;"><?= $p1['pontos'] ?> <span class="small opacity-50">PTS</span></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 3º Lugar -->
            <?php if (count($topProjetos) >= 3): $p3 = $topProjetos[2]; ?>
            <div class="podium-box">
                <div class="podium-num rank-bronze">3</div>
                <div class="podium-card-v3" style="height: 140px;">
                    <div class="startup-name"><?= htmlspecialchars($p3['titulo']) ?></div>
                    <div class="text-muted extra-small mb-3"><?= htmlspecialchars($p3['autor_nome']) ?></div>
                    <div class="startup-pts"><?= $p3['pontos'] ?> <span class="small opacity-50">SP</span></div>
                    <div class="badge-v3 mt-2" style="background:#fff7ed; color:#9a3412;">A Subir</div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- TABELA GERAL -->
        <h6 class="fw-bold text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px; color: #64748b;">Participantes Adicionais</h6>
        <div class="table-responsive">
            <table class="table-v3">
                <thead>
                    <tr class="text-muted x-small text-uppercase">
                        <th class="ps-3" width="70">Pos</th>
                        <th>Startup</th>
                        <th>Estado Atual</th>
                        <th class="text-end pe-3">Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($topProjetos) <= 3): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">Aguardando novos competidores...</td></tr>
                    <?php else: ?>
                        <?php 
                        $maxPts = $topProjetos[0]['pontos'] > 0 ? $topProjetos[0]['pontos'] : 1;
                        for($i = 3; $i < count($topProjetos); $i++): 
                            $p = $topProjetos[$i]; 
                            $pct = min(100, round(($p['pontos'] / $maxPts) * 100));
                        ?>
                        <tr>
                            <td class="ps-3 fw-bold text-center">
                                <span style="color:#94a3b8">#</span><?= $i + 1 ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['titulo']) ?></div>
                                <div class="extra-small text-muted"><?= htmlspecialchars($p['autor_nome']) ?></div>
                            </td>
                            <td>
                                <span class="badge-v3" style="background:rgba(0,0,0,0.05); color:#64748b;"><?= $p['estado'] ?></span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="fw-bold"><?= $p['pontos'] ?> SP</div>
                                <div class="d-flex justify-content-end mt-1">
                                    <div style="width: 80px; height: 4px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                                        <div style="width: <?= $pct ?>%; height: 100%; background: var(--primary);"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4 p-3 rounded-3 d-flex align-items-center gap-3" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
            <div class="text-warning fs-4"><i class="fa fa-circle-info"></i></div>
            <div class="extra-small text-muted" style="line-height: normal;">
                Este ranking é calculado com base nos marcos alcançados, produtividade em mentoria e qualidade das submissões. Atualizado automaticamente a cada 10 minutos.
            </div>
        </div>

    </div>
</div>

<?php 
$extraJs = '
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
window.onload = function() {
    confetti({
        particleCount: 150,
        spread: 80,
        origin: { y: 0.3, x: 0.5 },
        colors: ["#D97706", "#FBBF24", "#0f172a"]
    });
}
</script>';
include __DIR__ . '/../partials/_layout_end.php'; 
?>
