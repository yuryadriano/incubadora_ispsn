<?php
// app/views/admin/relatorios.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin','superadmin','funcionario']);

$tituloPagina = 'Relatórios de Impacto';
$paginaActiva = 'relatorios';

// Buscar a listagem mestra
$relatorios = [];
$sql = "
    SELECT p.id, p.titulo, p.area_tematica, p.tipo, p.estado,
           u.nome as autor,
           COUNT(DISTINCT c.id) as n_comentarios,
           COUNT(DISTINCT mt.id) as n_mentorias,
           COALESCE(SUM(f.montante_aprovado), 0) as financiamento,
           (SELECT pontuacao_total FROM avaliacoes WHERE id_projeto=p.id ORDER BY id DESC LIMIT 1) as nota_final
    FROM projetos p
    LEFT JOIN usuarios u ON u.id = p.criado_por
    LEFT JOIN comentarios_projetos c ON c.id_projeto = p.id
    LEFT JOIN mentorias mt ON mt.id_projeto = p.id
    LEFT JOIN financiamentos f ON f.id_projeto = p.id
    GROUP BY p.id
    ORDER BY p.criado_em DESC
";
$r = $mysqli->query($sql);
if ($r) while ($row = $r->fetch_assoc()) $relatorios[] = $row;

$totalF = array_sum(array_column($relatorios, 'financiamento'));

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- Secção Oculta para a Impressão (Apenas visível no window.print) -->
<style>
@media print {
    .sidebar, .page-header button, .btn-ghost, .btn-primary-custom, nav { display: none !important; }
    .main { margin: 0 !important; padding: 0 !important; }
    .card-custom { box-shadow: none !important; border: 1px solid #ccc !important; }
    body { background: #fff !important; }
    .print-header { display: block !important; text-align: center; margin-bottom: 30px; }
    .badge-estado { border: 1px solid #333; background: transparent !important; color: #000 !important; }
}
.print-header { display: none; }
</style>

<div class="print-header">
    <img src="/incubadora_ispsn/assets/img/logo_sn.png" style="max-height:80px">
    <h3>Relatório Global de Impacto da Incubadora</h3>
    <p>Instituto Superior Politécnico Sol Nascente (Data: <?= date('d/m/Y') ?>)</p>
    <hr>
</div>

<!-- PAGE HEADER -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <div class="page-header-title">
            <i class="fa fa-file-chart-line me-2" style="color:var(--success)"></i>
            Relatórios de Impacto
        </div>
        <div class="page-header-sub">Análise transversal de todos os projectos submetidos.</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn-primary-custom" onclick="window.print()">
            <i class="fa fa-print"></i> Exportar / Imprimir PDF
        </button>
    </div>
</div>

<div class="card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <div class="card-title-custom"><i class="fa fa-table"></i> Dados do Sistema Master</div>
        <div style="font-weight:800;color:var(--success)">Total Kz Envolvidos: <?= number_format($totalF, 2, ',', '.') ?></div>
    </div>
    <div class="table-wrapper">
        <table class="table-custom" id="impactTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Projecto / Startup</th>
                    <th>Área / Tipo</th>
                    <th>Autor / Estudante</th>
                    <th>Interacções</th>
                    <th>Avaliação</th>
                    <th>Orçamento (Kz)</th>
                    <th>Estado Final</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($relatorios as $rel): ?>
                <tr>
                    <td style="color:#94a3af">#<?= str_pad($rel['id'], 3, '0', STR_PAD_LEFT) ?></td>
                    <td style="font-weight:700;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                        <?= htmlspecialchars($rel['titulo']) ?>
                    </td>
                    <td>
                        <div style="font-size:0.8rem;text-transform:uppercase;color:var(--primary);font-weight:800"><?= $rel['tipo'] ?></div>
                        <small class="text-muted"><?= ucfirst($rel['area_tematica'] ?? '—') ?></small>
                    </td>
                    <td style="font-weight:500"><?= htmlspecialchars($rel['autor'] ?? '—') ?></td>
                    <td>
                        <span class="text-muted"><i class="fa fa-comments me-1"></i><?= $rel['n_comentarios'] ?></span>
                        <span class="text-muted ms-2"><i class="fa fa-handshake me-1"></i><?= $rel['n_mentorias'] ?></span>
                    </td>
                    <td>
                        <?php if(!is_null($rel['nota_final'])): ?>
                            <span style="font-weight:700;color:<?= $rel['nota_final']>=7?'#10b981':($rel['nota_final']>=5?'#f59e0b':'#ef4444')?>">
                                <?= $rel['nota_final'] ?>/10
                            </span>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:0.8rem">S/N</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:700;color:var(--text-secondary)">
                        <?= $rel['financiamento'] > 0 ? number_format($rel['financiamento'], 2, ',', '.') : '—' ?>
                    </td>
                    <td>
                         <span class="badge-estado badge-<?= $rel['estado'] ?>">
                            <?= ucfirst(str_replace('_',' ',$rel['estado'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
