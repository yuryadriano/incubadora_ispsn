<?php
require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$realPerfil = $_SESSION['usuario_perfil'] ?? 'utilizador';
$isAdminOrSuper = in_array($realPerfil, ['admin', 'superadmin'], true);

$idUsuario = ($isAdminOrSuper && !empty($_GET['id_usuario']))
    ? (int)$_GET['id_usuario']
    : (int)($_SESSION['usuario_id'] ?? 0);

// Procurar o projecto mais recente deste utilizador
$stmt = $mysqli->prepare("SELECT id FROM projetos WHERE criado_por = ? ORDER BY criado_em DESC LIMIT 1");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($res) {
    // Redireciona para a página de detalhes que já existe e tem permissão para o autor/admin
    $qsExtra = ($isAdminOrSuper && !empty($_GET['id_usuario'])) ? ('&id_usuario=' . $idUsuario) : '';
    header("Location: /incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=" . (int)$res['id'] . $qsExtra);
} else {
    // Se não tem projecto, volta para o dashboard do estudante com aviso
    $_SESSION['flash_erro'] = "Ainda não tem nenhum projecto submetido. Por favor, crie um primeiro.";
    if ($isAdminOrSuper && !empty($_GET['id_usuario'])) {
        header("Location: /incubadora_ispsn/app/views/dashboard/utilizador.php?id_usuario=" . $idUsuario);
    } else {
        header("Location: /incubadora_ispsn/public/index.php");
    }
}
exit;
