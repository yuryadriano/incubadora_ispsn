<?php
// scratch/check_db.php
require_once __DIR__ . '/../config/config.php';

echo "=== VERIFICAÇÃO DE DADOS ===\n";

$tablesToVerify = [
    'acompanhamentos',
    'avaliacoes',
    'avaliacoes_mentor',
    'candidaturas',
    'comentarios_projetos',
    'convites',
    'despesas',
    'emprestimos_equipamento',
    'ficheiros_projeto',
    'financiamentos',
    'historico_estados',
    'kpis',
    'membros_projeto',
    'mensagens',
    'mentorias',
    'metas_projeto',
    'notificacoes',
    'registos_kpi',
    'relatorios',
    'reservas_espaco',
    'reunioes',
    'sessoes_mentoria',
    'tarefas',
    'termos_incubacao',
    'visitantes',
    'logs_acesso',
    'publicacoes_website',
    'galeria_website',
    'espacos',
    'equipamentos',
    'metas_padrao'
];

foreach ($tablesToVerify as $table) {
    $res = $mysqli->query("SELECT COUNT(*) FROM `$table`");
    $count = $res ? $res->fetch_row()[0] : 'Error';
    echo "- Tabela $table: $count registos\n";
}

echo "\n=== VERIFICAÇÃO DE UTILIZADORES ===\n";
$res = $mysqli->query("SELECT id, nome, email, perfil FROM usuarios WHERE email IN ('yuryadriano.2019@gmail.com', 'evaristo.adriano@ispsn.org')");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
