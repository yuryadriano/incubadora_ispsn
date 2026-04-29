<?php
require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$idProjeto = (int)($_GET['id'] ?? 0);
$perfil    = $_SESSION['usuario_perfil'];

// Buscar dados do projecto
$stmt = $mysqli->prepare("
    SELECT p.*, u.nome as autor_nome, u.email as autor_email 
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    WHERE p.id = ?
");
$stmt->bind_param('i', $idProjeto);
$stmt->execute();
$projeto = $stmt->get_result()->fetch_assoc();

if (!$projeto) {
    die("Projecto não encontrado.");
}

// Buscar Membros
$stmtM = $mysqli->prepare("
    SELECT m.*, u.nome, u.email 
    FROM membros_projeto m
    JOIN usuarios u ON u.id = m.id_usuario
    WHERE m.id_projeto = ?
");
$stmtM->bind_param('i', $idProjeto);
$stmtM->execute();
$membros = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar Financiamento e Despesas
$stmtF = $mysqli->prepare("SELECT * FROM financiamentos WHERE id_projeto = ?");
$stmtF->bind_param('i', $idProjeto);
$stmtF->execute();
$finan = $stmtF->get_result()->fetch_assoc();

$despesas = [];
if ($finan) {
    $stmtD = $mysqli->prepare("SELECT * FROM despesas WHERE id_financiamento = ? ORDER BY data_despesa DESC");
    $stmtD->bind_param('i', $finan['id']);
    $stmtD->execute();
    $despesas = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Buscar KPIs e Registos
$stmtK = $mysqli->prepare("
    SELECT k.*, (SELECT valor FROM registos_kpi r WHERE r.id_kpi = k.id ORDER BY registado_em DESC LIMIT 1) as valor_atual
    FROM kpis k WHERE k.id_projeto = ?
");
$stmtK->bind_param('i', $idProjeto);
$stmtK->execute();
$kpis = $stmtK->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar Sessões de Mentoria
$stmtS = $mysqli->prepare("
    SELECT s.*, m.data_inicio, u.nome as mentor_nome
    FROM sessoes_mentoria s
    JOIN mentorias m ON m.id = s.id_mentoria
    JOIN mentores mt ON mt.id = m.id_mentor
    JOIN usuarios u ON u.id = mt.id_usuario
    WHERE m.id_projeto = ?
    ORDER BY s.data_sessao DESC
");
$stmtS->bind_param('i', $idProjeto);
$stmtS->execute();
$sessoes = $stmtS->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório - <?= htmlspecialchars($projeto['titulo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; color: #333; }
        .report-paper {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 25mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header-report { border-bottom: 2px solid #1a365d; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .logo-placeholder { font-weight: 800; font-size: 1.5rem; color: #1a365d; }
        h1 { font-size: 1.8rem; font-weight: 800; color: #1a365d; margin: 0; }
        h2 { font-size: 1.2rem; font-weight: 700; color: #2d3748; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-item label { display: block; font-size: 0.75rem; color: #718096; text-transform: uppercase; font-weight: 600; }
        .info-item span { font-size: 1rem; font-weight: 500; }
        .badge-status { padding: 4px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .status-em_andamento { background: #E0F2FE; color: #0369A1; }
        .status-concluido { background: #DCFCE7; color: #166534; }
        .status-em_analise { background: #FEF9C3; color: #854D0E; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8fafc; color: #475569; font-size: 0.75rem; text-transform: uppercase; padding: 10px; border: 1px solid #e2e8f0; text-align: left; }
        td { padding: 10px; border: 1px solid #e2e8f0; font-size: 0.875rem; }
        .footer-report { margin-top: 50px; border-top: 1px solid #e2e8f0; padding-top: 20px; font-size: 0.75rem; color: #a0aec0; text-align: center; }
        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .report-paper { box-shadow: none; margin: 0; width: 100%; padding: 15mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="container no-print mt-4 mb-2 text-center">
    <button onclick="window.print()" class="btn btn-dark shadow-sm px-4">
        <i class="fa fa-print me-2"></i> Imprimir / Guardar PDF
    </button>
    <a href="/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=<?= $idProjeto ?>" class="btn btn-outline-secondary ms-2">Voltar</a>
</div>

<div class="report-paper" id="reportContent">
    <div class="header-report">
        <div>
            <div class="logo-placeholder">INCUBADORA ISPSN</div>
            <div style="font-size: 0.8rem; color: #718096;">Relatório de Desempenho de Startup</div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 0.8rem; color: #718096;">Data de Emissão</div>
            <div style="font-weight: 600;"><?= date('d/m/Y') ?></div>
        </div>
    </div>

    <h1><?= htmlspecialchars($projeto['titulo']) ?></h1>
    <div class="mt-2">
        <span class="badge-status status-<?= $projeto['estado'] ?>">
            <?= ucfirst(str_replace('_',' ',$projeto['estado'])) ?>
        </span>
        <span class="ms-3 text-muted" style="font-size: 0.9rem;">Área: <?= strtoupper($projeto['tipo']) ?></span>
    </div>

    <h2>1. Resumo Executivo</h2>
    <p style="font-size: 0.9rem; line-height: 1.6;"><?= nl2br(htmlspecialchars($projeto['descricao'])) ?></p>
    
    <div class="info-grid mt-4">
        <div class="info-item">
            <label>Promotor Principal</label>
            <span><?= htmlspecialchars($projeto['autor_nome']) ?></span>
        </div>
        <div class="info-item">
            <label>E-mail de Contacto</label>
            <span><?= htmlspecialchars($projeto['autor_email']) ?></span>
        </div>
        <div class="info-item">
            <label>Data de Candidatura</label>
            <span><?= date('d/m/Y', strtotime($projeto['criado_em'])) ?></span>
        </div>
        <div class="info-item">
            <label>ID do Sistema</label>
            <span>#<?= str_pad($projeto['id'], 5, '0', STR_PAD_LEFT) ?></span>
        </div>
    </div>

    <h2>2. Equipa do Projecto</h2>
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Função</th>
                <th>E-mail</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($membros as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m['nome']) ?></td>
                <td><?= htmlspecialchars($m['papel']) ?></td>
                <td><?= htmlspecialchars($m['email']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>3. Indicadores de Desempenho (KPIs)</h2>
    <table>
        <thead>
            <tr>
                <th>Indicador</th>
                <th>Meta</th>
                <th>Valor Atual</th>
                <th>Progresso</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($kpis as $k): 
                $perc = $k['meta'] > 0 ? min(100, round(($k['valor_atual'] / $k['meta']) * 100)) : 0;
            ?>
            <tr>
                <td><?= htmlspecialchars($k['nome']) ?></td>
                <td><?= number_format($k['meta'], 0, ',', '.') ?> <?= $k['unidade'] ?></td>
                <td><?= number_format($k['valor_atual'] ?? 0, 0, ',', '.') ?> <?= $k['unidade'] ?></td>
                <td><strong><?= $perc ?>%</strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>4. Execução Financeira</h2>
    <?php if ($finan): ?>
    <div class="info-grid mb-3">
        <div class="info-item">
            <label>Montante Aprovado</label>
            <span style="color: #1a365d; font-weight: 700;"><?= number_format($finan['montante_aprovado'], 2, ',', '.') ?> Kz</span>
        </div>
        <div class="info-item">
            <label>Montante Executado</label>
            <span style="color: #c53030; font-weight: 700;"><?= number_format($finan['montante_executado'], 2, ',', '.') ?> Kz</span>
        </div>
    </div>
    <div style="font-size: 0.8rem; color: #718096; margin-bottom: 5px;">Últimas Despesas Registadas:</div>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Descrição</th>
                <th>Categoria</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($despesas, 0, 5) as $d): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($d['data_despesa'])) ?></td>
                <td><?= htmlspecialchars($d['descricao']) ?></td>
                <td><?= htmlspecialchars($d['categoria']) ?></td>
                <td><?= number_format($d['valor'], 2, ',', '.') ?> Kz</td>
            </tr>
            <?php endforeach; if (empty($despesas)) echo '<tr><td colspan="4" class="text-center">Sem despesas registadas.</td></tr>'; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="text-muted small">Este projecto não possui financiamento activo registado.</p>
    <?php endif; ?>

    <h2>5. Histórico de Mentoria</h2>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Mentor</th>
                <th>Tópicos Abordados</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sessoes as $s): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($s['data_sessao'])) ?></td>
                <td><?= htmlspecialchars($s['mentor_nome']) ?></td>
                <td><?= htmlspecialchars($s['topicos']) ?></td>
            </tr>
            <?php endforeach; if (empty($sessoes)) echo '<tr><td colspan="3" class="text-center">Nenhuma sessão de mentoria registada.</td></tr>'; ?>
        </tbody>
    </table>

    <div class="footer-report">
        Este documento é um relatório gerado automaticamente pelo Sistema de Gestão de Incubadora ISPSN.<br>
        &copy; <?= date('Y') ?> Instituto Superior Politécnico Sol Nascente - Huambo, Angola.
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
</body>
</html>
