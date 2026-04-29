<?php
// app/controllers/perfil_action.php
require_once __DIR__ . '/../../config/auth.php';
obrigarLogin();

$action = $_POST['action'] ?? '';
$idUsuario = (int)$_SESSION['usuario_id'];

if ($action === 'atualizar_perfil') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if ($nome && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Verifica se email já existe em outro usuário
        $check = $mysqli->prepare("SELECT id FROM usuarios WHERE email=? AND id != ?");
        $check->bind_param('si', $email, $idUsuario);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['flash_erro'] = "Este e-mail já está em uso por outra conta.";
        } else {
            $stmt = $mysqli->prepare("UPDATE usuarios SET nome=?, email=? WHERE id=?");
            $stmt->bind_param('ssi', $nome, $email, $idUsuario);
            if ($stmt->execute()) {
                $_SESSION['usuario_nome'] = $nome; // Atualiza sessão
                $_SESSION['flash_ok'] = "Perfil actualizado com sucesso.";
            } else {
                $_SESSION['flash_erro'] = "Erro ao actualizar perfil.";
            }
        }
    } else {
        $_SESSION['flash_erro'] = "Dados inválidos.";
    }
}

if ($action === 'atualizar_senha') {
    $senhaAntiga = $_POST['senha_antiga'] ?? '';
    $senhaNova = $_POST['senha_nova'] ?? '';
    $senhaConf = $_POST['senha_confirmacao'] ?? '';

    if (strlen($senhaNova) < 6) {
        $_SESSION['flash_erro'] = "A nova senha deve ter pelo menos 6 caracteres.";
    } elseif ($senhaNova !== $senhaConf) {
        $_SESSION['flash_erro'] = "As senhas novas não coincidem.";
    } else {
        // Valida senha antiga
        $stmt = $mysqli->prepare("SELECT senha_hash FROM usuarios WHERE id=?");
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && (password_verify($senhaAntiga, $user['senha_hash']) || $user['senha_hash'] === 'TEMP' && $senhaAntiga === '123456')) {
            $novoHash = password_hash($senhaNova, PASSWORD_DEFAULT);
            $upd = $mysqli->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?");
            $upd->bind_param('si', $novoHash, $idUsuario);
            $upd->execute();
            $_SESSION['flash_ok'] = "Senha alterada com segurança!";
        } else {
            $_SESSION['flash_erro'] = "Senha antiga incorrecta.";
        }
    }
}

header("Location: /incubadora_ispsn/app/views/auth/perfil.php");
exit;
