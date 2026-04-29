<?php
require_once __DIR__ . '/../../config/auth.php';
header('Content-Type: application/json');

$hoje = date('Y-m-d');
$horaAtual = date('H:i:s');

// Buscar ocupação ativa
$sql = "
    SELECT e.id as id_espaco, e.nome as espaco_nome, e.tipo, e.capacidade,
           r.id as id_reserva, r.hora_inicio, r.hora_fim, r.check_in_at,
           u.nome as usuario_nome
    FROM espacos e
    LEFT JOIN reservas_espaco r ON e.id = r.id_espaco 
         AND r.data_reserva = '$hoje' 
         AND r.status = 'confirmada' 
         AND '$horaAtual' BETWEEN r.hora_inicio AND r.hora_fim
    LEFT JOIN usuarios u ON u.id = r.id_usuario
    ORDER BY e.tipo DESC, e.nome ASC
";

$res = $mysqli->query($sql);
$dados = [];
while($row = $res->fetch_assoc()) {
    $dados[] = $row;
}

echo json_encode($dados);
