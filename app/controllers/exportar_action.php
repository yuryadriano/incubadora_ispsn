<?php
// app/controllers/exportar_action.php
// Exportação de dados para CSV/Excel
// Acessível apenas por admin e superadmin

require_once __DIR__ . '/../../config/auth.php';
obrigarPerfil(['admin', 'superadmin']);

$tipo = $_GET['tipo'] ?? '';

// ─── Helper: enviar CSV ──────────────────────────────────────────────
function enviarCSV(string $nomeArquivo, array $cabecalhos, array $linhas): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // BOM para o Excel reconhecer UTF-8 correctamente
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, $cabecalhos, ';');
    foreach ($linhas as $linha) {
        fputcsv($out, $linha, ';');
    }
    fclose($out);
    exit;
}

// ══════════════════════════════════════════════════
//  EXPORTAR CANDIDATURAS
// ══════════════════════════════════════════════════
if ($tipo === 'candidaturas') {
    $idProcesso = (int)($_GET['processo'] ?? 0);
    $estado     = $_GET['estado'] ?? '';

    $sql = "SELECT 
                c.id,
                c.nome,
                c.email,
                c.telefone,
                c.numero_estudante,
                c.curso,
                c.titulo_ideia,
                c.descricao_ideia,
                c.estado,
                DATE_FORMAT(c.criado_em, '%d/%m/%Y %H:%i') as data_candidatura,
                DATE_FORMAT(c.avaliado_em, '%d/%m/%Y %H:%i') as data_avaliacao,
                u.nome as avaliado_por_nome,
                p.nome as processo_nome,
                c.observacoes_admin
            FROM candidaturas c
            LEFT JOIN usuarios u ON u.id = c.avaliado_por
            LEFT JOIN processos_candidatura p ON p.id = c.id_processo
            WHERE 1=1";

    $params = [];
    $types  = '';

    if ($idProcesso > 0) {
        $sql    .= " AND c.id_processo = ?";
        $params[] = $idProcesso;
        $types   .= 'i';
    }
    if ($estado) {
        $estados_validos = ['pendente','em_analise','selecionado','rejeitado','convite_enviado','registado'];
        if (in_array($estado, $estados_validos)) {
            $sql    .= " AND c.estado = ?";
            $params[] = $estado;
            $types   .= 's';
        }
    }

    $sql .= " ORDER BY c.criado_em DESC";

    $stmt = $mysqli->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $cabecalhos = ['ID','Nome','Email','Telefone','Nº Estudante','Curso','Título da Ideia','Descrição','Estado','Data Candidatura','Data Avaliação','Avaliado Por','Processo','Observações'];
    $linhas = array_map(fn($r) => array_values($r), $resultado);

    $filtro = $idProcesso ? "_proc{$idProcesso}" : '';
    $filtro .= $estado ? "_{$estado}" : '';
    enviarCSV("candidaturas{$filtro}_" . date('Ymd_His') . ".csv", $cabecalhos, $linhas);
}

// ══════════════════════════════════════════════════
//  EXPORTAR PROJETOS / STARTUPS
// ══════════════════════════════════════════════════
if ($tipo === 'projetos') {
    $estado = $_GET['estado'] ?? '';

    $sql = "SELECT
                p.id,
                p.titulo,
                p.tipo,
                p.area,
                p.estado,
                p.fase,
                p.pontos,
                u.nome as criado_por_nome,
                u.email as email_founder,
                DATE_FORMAT(p.criado_em, '%d/%m/%Y %H:%i') as data_submissao,
                (SELECT COUNT(*) FROM membros_projeto WHERE id_projeto = p.id) as total_membros,
                (SELECT SUM(montante_aprovado) FROM financiamentos WHERE id_projeto = p.id AND estado != 'cancelado') as total_financiamento,
                (SELECT COUNT(*) FROM metas_projeto WHERE id_projeto = p.id AND estado = 'concluida') as metas_concluidas,
                (SELECT COUNT(*) FROM metas_projeto WHERE id_projeto = p.id) as total_metas
            FROM projetos p
            JOIN usuarios u ON u.id = p.criado_por
            WHERE 1=1";

    $params = [];
    $types  = '';

    if ($estado) {
        $estados_validos = ['submetido','em_avaliacao','aprovado','incubado','fundo_investimento','concluido','rejeitado'];
        if (in_array($estado, $estados_validos)) {
            $sql    .= " AND p.estado = ?";
            $params[] = $estado;
            $types   .= 's';
        }
    }

    $sql .= " ORDER BY p.pontos DESC, p.criado_em DESC";

    $stmt = $mysqli->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $cabecalhos = ['ID','Título','Tipo','Área','Estado','Fase','SP (Pontos)','Fundador','Email Fundador','Data Submissão','Total Membros','Financiamento Total (Kz)','Metas Concluídas','Total Metas'];
    $linhas = array_map(fn($r) => array_values($r), $resultado);

    $filtro = $estado ? "_{$estado}" : '';
    enviarCSV("startups{$filtro}_" . date('Ymd_His') . ".csv", $cabecalhos, $linhas);
}

// ══════════════════════════════════════════════════
//  EXPORTAR UTILIZADORES
// ══════════════════════════════════════════════════
if ($tipo === 'utilizadores') {
    $perfil = $_GET['perfil'] ?? '';

    $sql = "SELECT
                u.id,
                u.nome,
                u.email,
                u.perfil,
                CASE WHEN u.activo = 1 THEN 'Activo' ELSE 'Inactivo' END as status,
                DATE_FORMAT(u.criado_em, '%d/%m/%Y %H:%i') as data_registo,
                (SELECT MAX(data_acesso) FROM logs_acesso WHERE id_usuario = u.id AND sucesso = 1) as ultimo_login
            FROM usuarios u
            WHERE 1=1";

    $params = [];
    $types  = '';

    if ($perfil && in_array($perfil, ['admin','mentor','funcionario','utilizador','superadmin'])) {
        $sql    .= " AND u.perfil = ?";
        $params[] = $perfil;
        $types   .= 's';
    }

    $sql .= " ORDER BY u.criado_em DESC";

    $stmt = $mysqli->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $cabecalhos = ['ID','Nome','Email','Perfil','Status','Data Registo','Último Login'];
    $linhas = array_map(fn($r) => array_values($r), $resultado);

    $filtro = $perfil ? "_{$perfil}" : '';
    enviarCSV("utilizadores{$filtro}_" . date('Ymd_His') . ".csv", $cabecalhos, $linhas);
}

// ══════════════════════════════════════════════════
//  EXPORTAR RELATÓRIO DE IMPACTO (resumo geral)
// ══════════════════════════════════════════════════
if ($tipo === 'impacto') {
    $ano = (int)($_GET['ano'] ?? date('Y'));

    // Candidaturas por mês
    $stmt = $mysqli->prepare("
        SELECT MONTH(criado_em) as mes, MONTHNAME(criado_em) as nome_mes, COUNT(*) as total,
               SUM(CASE WHEN estado IN ('selecionado','registado','convite_enviado') THEN 1 ELSE 0 END) as aprovados,
               SUM(CASE WHEN estado = 'rejeitado' THEN 1 ELSE 0 END) as rejeitados
        FROM candidaturas
        WHERE YEAR(criado_em) = ?
        GROUP BY mes, nome_mes
        ORDER BY mes
    ");
    $stmt->bind_param('i', $ano);
    $stmt->execute();
    $candPorMes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $cabecalhos = ['Mês','Total Candidaturas','Aprovados','Rejeitados'];
    $linhas = array_map(fn($r) => [$r['nome_mes'], $r['total'], $r['aprovados'], $r['rejeitados']], $candPorMes);
    enviarCSV("relatorio_impacto_{$ano}_" . date('Ymd') . ".csv", $cabecalhos, $linhas);
}

// Tipo não reconhecido
http_response_code(400);
die('Tipo de exportação inválido. Use: ?tipo=candidaturas|projetos|utilizadores|impacto');
