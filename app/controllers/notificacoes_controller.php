<?php
// app/controllers/notificacoes_controller.php
require_once __DIR__ . '/../../config/auth.php';
obrigarLogin();

$idUsuario = (int)$_SESSION['usuario_id'];
$action    = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json');

if ($action === 'check') {
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_usuario = ? AND lida = 0");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    echo json_encode(['unread' => (int)$res['total']]);
    exit;
}

if ($action === 'list') {
    $stmt = $mysqli->prepare("SELECT id, titulo, mensagem, tipo, criado_em FROM notificacoes WHERE id_usuario = ? ORDER BY criado_em DESC LIMIT 5");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['notificacoes' => $list]);
    exit;
}

if ($action === 'read_all') {
    $stmt = $mysqli->prepare("UPDATE notificacoes SET lida = 1 WHERE id_usuario = ?");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Ação inválida']);
exit;
