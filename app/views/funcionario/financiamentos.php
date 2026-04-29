<?php
// app/views/funcionario/financiamentos.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['funcionario','admin','superadmin']);

$tituloPagina = 'Financiamentos';
$paginaActiva = 'financiamentos';

// ── Dados ──────────────────────────────────
$financiamentos = [];
$r = $mysqli->query("
    SELECT f.id, f.fonte, f.montante_aprovado, f.montante_executado, f.estado,
           f.data_aprovacao, f.data_limite, f.descricao,
           p.titulo projeto, p.id id_projeto,
           (SELECT COUNT(*) FROM despesas WHERE id_financiamento=f.id) n_despesas
    FROM financiamentos f
    JOIN projetos p ON p.id = f.id_projeto
    ORDER BY f.criado_em DESC
");
if ($r) while ($row = $r->fetch_assoc()) $financiamentos[] = $row;

// KPIs financeiras
$totalAprovado  = array_sum(array_column($financiamentos, 'montante_aprovado'));
$totalExecutado = array_sum(array_column($financiamentos, 'montante_executado'));
$totalSaldo     = $totalAprovado - $totalExecutado;
$pctExec        = $totalAprovado > 0 ? min(100, round($totalExecutado / $totalAprovado * 100)) : 0;
$totalProjetos  = count(array_unique(array_column($financiamentos, 'id_projeto')));

// Projectos para formulário
$projetosLista = [];
$rp = $mysqli->query("SELECT id, titulo FROM projetos WHERE estado IN ('em_analise','em_andamento') ORDER BY titulo");
if ($rp) while ($row = $rp->fetch_assoc()) $projetosLista[] = $row;

// Flash messages
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

// Salvar novo financiamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'criar_financiamento') {
    $idProjeto  = (int)($_POST['id_projeto']        ?? 0);
    $fonte      = trim($_POST['fonte']               ?? '');
    $montante   = (float)($_POST['montante_aprovado']?? 0);
    $dataAprov  = $_POST['data_aprovacao']           ?? null;
    $dataLimite = $_POST['data_limite']              ?? null;
    $descricao  = trim($_POST['descricao']           ?? '');

    if ($idProjeto && $fonte && $montante > 0) {
        $stmt = $mysqli->prepare("
            INSERT INTO financiamentos (id_projeto, fonte, montante_aprovado, data_aprovacao, data_limite, descricao, estado)
            VALUES (?,?,?,?,?,'pendente')
        ");
        // Note: descricao is 6th param, estado is hardcoded
        $stmt = $mysqli->prepare("
            INSERT INTO financiamentos (id_projeto, fonte, montante_aprovado, data_aprovacao, data_limite, descricao)
            VALUES (?,?,?,?,?,?)
        ");
        $stmt->bind_param('isdsss', $idProjeto, $fonte, $montante, $dataAprov, $dataLimite, $descricao);
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = 'Financiamento registado com sucesso!';
        } else {
            $_SESSION['flash_erro'] = 'Erro ao registar financiamento: ' . $mysqli->error;
        }
    } else {
        $_SESSION['flash_erro'] = 'Preencha todos os campos obrigatórios.';
    }
    header('Location: /incubadora_ispsn/app/views/funcionario/financiamentos.php');
    exit;
}

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- FLASH -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger  mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa fa-money-bill-wave me-2" style="color:#EC4899"></i>
            Acompanhamento de Financiamentos
        </div>
        <div class="page-header-sub"><?= count($financiamentos) ?> financiamento(s) registado(s) · <?= $totalProjetos ?> projecto(s)</div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovoFin">
        <i class="fa fa-plus"></i> Registar Financiamento
    </button>
</div>

<!-- KPI GRID -->
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-bottom:24px">
    <div class="kpi-card" style="--kpi-color:#EC4899">
        <div class="kpi-icon"><i class="fa fa-money-bill-wave"></i></div>
        <div class="kpi-value"><?= number_format($totalAprovado/1000,0) ?>K</div>
        <div class="kpi-label">Kz Aprovados</div>
        <div class="kpi-trend trend-up"><i class="fa fa-check"></i> Total</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--primary)">
        <div class="kpi-icon"><i class="fa fa-coins"></i></div>
        <div class="kpi-value"><?= number_format($totalExecutado/1000,0) ?>K</div>
        <div class="kpi-label">Kz Executados</div>
        <div class="kpi-trend" style="color:var(--primary)"><i class="fa fa-chart-bar"></i> <?= $pctExec ?>% do total</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--success)">
        <div class="kpi-icon"><i class="fa fa-piggy-bank"></i></div>
        <div class="kpi-value"><?= number_format($totalSaldo/1000,0) ?>K</div>
        <div class="kpi-label">Kz Disponível</div>
        <div class="kpi-trend trend-up"><i class="fa fa-wallet"></i> Saldo restante</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--warning)">
        <div class="kpi-icon"><i class="fa fa-percent"></i></div>
        <div class="kpi-value"><?= $pctExec ?>%</div>
        <div class="kpi-label">Taxa de Execução</div>
        <div class="kpi-trend" style="color:var(--warning)"><i class="fa fa-circle-dot"></i> Global</div>
    </div>
</div>

<!-- BARRA DE EXECUÇÃO GLOBAL -->
<?php if ($totalAprovado > 0): ?>
<div class="card-custom mb-4">
    <div class="card-body-custom">
        <div class="d-flex justify-content-between mb-2">
            <span style="font-weight:600;font-size:0.875rem"><i class="fa fa-chart-line me-2" style="color:var(--primary)"></i>Execução Financeira Global</span>
            <span style="font-weight:800;font-size:1.1rem;color:var(--primary)"><?= $pctExec ?>%</span>
        </div>
        <div class="progress-custom" style="height:12px">
            <div class="progress-bar-custom" style="width:<?= $pctExec ?>%"></div>
        </div>
        <div class="d-flex justify-content-between mt-2" style="font-size:0.78rem;color:var(--text-muted)">
            <span>Executado: <strong><?= number_format($totalExecutado, 2, ',', '.') ?> Kz</strong></span>
            <span>Aprovado: <strong><?= number_format($totalAprovado, 2, ',', '.') ?> Kz</strong></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- TABELA -->
<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom"><i class="fa fa-table-list"></i> Financiamentos por Projecto</div>
    </div>
    <?php if (empty($financiamentos)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa fa-money-bill-trend-up"></i></div>
        <div class="empty-state-title">Nenhum financiamento registado</div>
        <div class="empty-state-text">Clique em "Registar Financiamento" para adicionar o primeiro</div>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table-custom">
            <thead>
                <tr><th>Projecto</th><th>Fonte</th><th>Aprovado (Kz)</th><th>Executado (Kz)</th><th>Execução</th><th>Validade</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($financiamentos as $f):
                $pctF = $f['montante_aprovado'] > 0
                    ? min(100, round($f['montante_executado'] / $f['montante_aprovado'] * 100))
                    : 0;
                $corPct = $pctF >= 80 ? 'var(--danger)' : ($pctF >= 50 ? 'var(--warning)' : 'var(--success)');
                $vencido = $f['data_limite'] && $f['data_limite'] < date('Y-m-d') && $f['estado'] === 'activo';
            ?>
            <tr>
                <td>
                    <a href="/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=<?= $f['id_projeto'] ?>"
                       style="font-weight:600;color:var(--primary);text-decoration:none;display:block;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= htmlspecialchars($f['projeto']) ?>
                    </a>
                </td>
                <td style="font-weight:500"><?= htmlspecialchars($f['fonte']) ?></td>
                <td style="font-weight:700"><?= number_format($f['montante_aprovado'], 2, ',', '.') ?></td>
                <td style="font-weight:700;color:<?= $corPct ?>"><?= number_format($f['montante_executado'], 2, ',', '.') ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="flex:1;height:6px;background:var(--border);border-radius:3px;overflow:hidden">
                            <div style="height:100%;width:<?= $pctF ?>%;background:<?= $corPct ?>;border-radius:3px"></div>
                        </div>
                        <small style="font-weight:700;color:<?= $corPct ?>;min-width:32px"><?= $pctF ?>%</small>
                    </div>
                </td>
                <td>
                    <small class="<?= $vencido ? 'text-danger fw-bold' : 'text-muted' ?>">
                        <?= $f['data_limite'] ? date('d/m/Y', strtotime($f['data_limite'])) : '—' ?>
                        <?= $vencido ? ' ⚠' : '' ?>
                    </small>
                </td>
                <td><span class="badge-estado badge-<?= $f['estado'] ?>"><?= ucfirst($f['estado']) ?></span></td>
                <td>
                    <button class="btn-ghost" style="padding:5px 10px;font-size:0.78rem"
                            title="Ver detalhes" data-bs-toggle="modal"
                            data-bs-target="#modalDetalhe<?= $f['id'] ?>">
                        <i class="fa fa-eye"></i>
                    </button>
                </td>
            </tr>
            <!-- Modal detalhe financiamento -->
            <div class="modal fade" id="modalDetalhe<?= $f['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content modal-content-custom">
                        <div class="modal-header-custom">
                            <h5 class="modal-title fw-bold"><i class="fa fa-money-bill-wave me-2"></i><?= htmlspecialchars($f['fonte']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body-custom">
                            <p><strong>Projecto:</strong> <?= htmlspecialchars($f['projeto']) ?></p>
                            <p><strong>Montante Aprovado:</strong> <?= number_format($f['montante_aprovado'], 2, ',', '.') ?> Kz</p>
                            <p><strong>Montante Executado:</strong> <?= number_format($f['montante_executado'], 2, ',', '.') ?> Kz</p>
                            <p><strong>Data Aprovação:</strong> <?= $f['data_aprovacao'] ? date('d/m/Y', strtotime($f['data_aprovacao'])) : '—' ?></p>
                            <p><strong>Validade:</strong> <?= $f['data_limite'] ? date('d/m/Y', strtotime($f['data_limite'])) : '—' ?></p>
                            <p><strong>Nº Despesas:</strong> <?= $f['n_despesas'] ?></p>
                            <?php if ($f['descricao']): ?>
                            <p><strong>Descrição:</strong> <?= nl2br(htmlspecialchars($f['descricao'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer-custom">
                            <button type="button" class="btn-ghost" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL: Novo Financiamento -->
<div class="modal fade" id="modalNovoFin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post">
                <input type="hidden" name="action" value="criar_financiamento">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-money-bill-wave me-2"></i>Registar Financiamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Projecto *</label>
                            <select name="id_projeto" class="form-control-custom" required>
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($projetosLista as $pr): ?>
                                <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['titulo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Fonte / Entidade Financiadora *</label>
                            <input type="text" name="fonte" class="form-control-custom" required placeholder="Ex: ISPSN, BPC, Fundo XYZ">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Montante Aprovado (Kz) *</label>
                            <input type="number" name="montante_aprovado" class="form-control-custom" required min="1" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Data de Aprovação</label>
                            <input type="date" name="data_aprovacao" class="form-control-custom" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Data Limite de Execução</label>
                            <input type="date" name="data_limite" class="form-control-custom">
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Descrição / Observações</label>
                            <textarea name="descricao" class="form-control-custom" rows="2" placeholder="Condições, objectivos, etc."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-save"></i> Registar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
