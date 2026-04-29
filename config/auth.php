<?php
require_once __DIR__ . '/config.php';

/* =========================
   FUNÇÕES DE AUTENTICAÇÃO
   ========================= */

function encontrarUsuarioPorEmail($email) {
    global $mysqli;
    $sql = "SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function registarLogAcesso($id_usuario, $sucesso) {
    global $mysqli;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido';

    $sql = "INSERT INTO logs_acesso (id_usuario, ip, user_agent, sucesso)
            VALUES (?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('issi', $id_usuario, $ip, $agent, $sucesso);
    $stmt->execute();
}

function obrigarLogin() {
    if (empty($_SESSION['usuario_id'])) {
        header('Location: /incubadora_ispsn/public/login.php');
        exit;
    }
}

function obrigarPerfil(array $perfisPermitidos) {
    if (empty($_SESSION['usuario_id'])) {
        header('Location: /incubadora_ispsn/public/login.php');
        exit;
    }
    
    $perfil = $_SESSION['usuario_perfil'] ?? '';
    
    // Super Admin tem passe livre para tudo (facilitador de Gestão/Dev)
    if ($perfil === 'superadmin') {
        return;
    }

    if (!in_array($perfil, $perfisPermitidos)) {
        header('Location: /incubadora_ispsn/public/login.php?erro=permissao');
        exit;
    }
}

function enviarNotificacao($idUsuario, $titulo, $mensagem, $tipo = 'info') {
    global $mysqli;
    $sql = "INSERT INTO notificacoes (id_usuario, titulo, mensagem, tipo) VALUES (?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('isss', $idUsuario, $titulo, $mensagem, $tipo);
    return $stmt->execute();
}
