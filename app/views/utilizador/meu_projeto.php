<?php
require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$idUsuario = $_SESSION['usuario_id'];

// Procurar o projecto mais recente deste utilizador
$stmt = $mysqli->prepare("SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res) {
    // Redireciona para a página de detalhes que já existe e tem permissão para o autor
    header("Location: /incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=" . $res['id']);
} else {
    // Se não tem projecto, volta para o dashboard com aviso
    $_SESSION['flash_erro'] = "Ainda não tem nenhum projecto submetido. Por favor, crie um primeiro.";
    header("Location: /incubadora_ispsn/public/index.php");
}
exit;
