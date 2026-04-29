<?php
require_once __DIR__ . '/../config/config.php';

// 1. Encontrar o primeiro mentor e a sua primeira mentoria activa
$res = $mysqli->query("
    SELECT m.id as id_mentoria, mt.id_usuario 
    FROM mentorias m 
    JOIN mentores mt ON mt.id = m.id_mentor 
    WHERE m.estado = 'activa' 
    LIMIT 1
");

if ($res && $res->num_rows > 0) {
    $data = $res->fetch_assoc();
    $idMentoria = $data['id_mentoria'];
    
    $dataSessao = date('Y-m-d', strtotime('-1 day'));
    $duracao = 90;
    $topicos = "Marketing Digital, Plano de Negócios, MVP, Finanças, Pitch Deck";
    $proximos = "Finalizar o protótipo funcional e preparar a apresentação para investidores.";
    $avalMentor = 5;
    $avalEquipa = 4;

    $stmt = $mysqli->prepare("
        INSERT INTO sessoes_mentoria (id_mentoria, data_sessao, duracao_min, topicos, proximos_passos, avaliacao_mentor, avaliacao_equipa)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('isisiii', $idMentoria, $dataSessao, $duracao, $topicos, $proximos, $avalMentor, $avalEquipa);
    
    if ($stmt->execute()) {
        echo "SUCESSO: Sessão de teste inserida com sucesso para a mentoria ID: $idMentoria.\n";
        echo "Tópicos inseridos: $topicos\n";
    } else {
        echo "ERRO ao inserir sessão: " . $mysqli->error . "\n";
    }
} else {
    echo "AVISO: Não foi encontrada nenhuma mentoria activa no sistema para realizar o teste.\n";
    echo "Por favor, certifique-se de que existe pelo menos um projeto atribuído a um mentor.\n";
}
?>
