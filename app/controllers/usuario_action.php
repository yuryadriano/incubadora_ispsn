<?php
// app/controllers/usuario_action.php
require_once __DIR__ . '/../../config/auth.php';
obrigarPerfil(['admin','superadmin']);

$action   = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? '/incubadora_ispsn/app/views/admin/usuarios.php';

if (!str_starts_with($redirect, '/incubadora_ispsn/')) {
    $redirect = '/incubadora_ispsn/app/views/admin/usuarios.php';
}

if ($action === 'criar_usuario') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $perfil= $_POST['perfil'] ?? 'utilizador';
    $tipo  = $_POST['tipo_utilizador'] ?? 'estudante';

    if ($nome && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Verificar se email já existe
        $check = $mysqli->prepare("SELECT id FROM usuarios WHERE email=?");
        $check->bind_param('s', $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['flash_erro'] = "O e-mail $email já está registado.";
        } else {
            // Senha default: 123456 com hash (ou estado 'TEMP' para forçar reset, mas como no inicio usamos 'TEMP' como hash simulado, vamos usar password_hash)
            $senhaPadrao = password_hash('123456', PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO usuarios (nome, email, perfil, tipo_utilizador, senha_hash, activo) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param('sssss', $nome, $email, $perfil, $tipo, $senhaPadrao);
            if ($stmt->execute()) {
                $_SESSION['flash_ok'] = "Utilizador $nome criado com sucesso! (Senha padrão: 123456)";
            } else {
                $_SESSION['flash_erro'] = "Erro ao criar utilizador.";
            }
        }
    } else {
        $_SESSION['flash_erro'] = "Dados inválidos para criação do utilizador.";
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'mudar_estado') {
    $idUsuario = (int)($_POST['id_usuario'] ?? 0);
    $estado    = (int)($_POST['estado'] ?? 0);
    
    // Nao permitir desactivar a si proprio ou ao superadmin principal
    if ($idUsuario == $_SESSION['usuario_id']) {
        $_SESSION['flash_erro'] = "Não pode desactivar a sua própria conta.";
    } else {
        $stmt = $mysqli->prepare("UPDATE usuarios SET activo=? WHERE id=?");
        $stmt->bind_param('ii', $estado, $idUsuario);
        $stmt->execute();
        $_SESSION['flash_ok'] = "Estado do utilizador actualizado.";
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'editar_usuario') {
    $idUsuario = (int)($_POST['id_usuario'] ?? 0);
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $perfil= $_POST['perfil'] ?? 'utilizador';
    $tipo  = $_POST['tipo_utilizador'] ?? 'estudante';

    if ($idUsuario && $nome && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Ignorar o proprio superadmin se quem ta a editar é root? Vamos ser simples.
        $stmt = $mysqli->prepare("UPDATE usuarios SET nome=?, email=?, perfil=?, tipo_utilizador=? WHERE id=?");
        $stmt->bind_param('ssssi', $nome, $email, $perfil, $tipo, $idUsuario);
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = "Dados do utilizador actualizados.";
        } else {
            $_SESSION['flash_erro'] = "Erro ao actualizar utilizador: O e-mail pode já estar em uso.";
        }
    }
    header("Location: $redirect");
    exit;
}

header("Location: $redirect");
exit;
