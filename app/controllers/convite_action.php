<?php
// app/controllers/convite_action.php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/config.php';

obrigarPerfil(['admin', 'superadmin']);

$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? '/incubadora_ispsn/app/views/admin/usuarios.php';

if ($action === 'enviar_convite' || $action === 'gerar_link') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $perfil = $_POST['perfil'] ?? 'mentor';
    $mensagemCustom = trim($_POST['mensagem'] ?? '');
    $idAdmin = $_SESSION['usuario_id'];
    $idProjeto = (int)($_POST['id_projeto'] ?? 0);

    if (empty($email)) $email = 'convidado_' . time() . '@ispsn.org';

    // 1. Gerar token único
    $token = bin2hex(random_bytes(16));
    
    // 2. Assegurar que a tabela existe
    $mysqli->query("CREATE TABLE IF NOT EXISTS convites (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
        email VARCHAR(150) NOT NULL, 
        token VARCHAR(100) NOT NULL, 
        perfil VARCHAR(50) NOT NULL DEFAULT 'utilizador', 
        id_projeto INT UNSIGNED DEFAULT NULL,
        criado_por INT UNSIGNED NOT NULL, 
        aceite TINYINT(1) DEFAULT 0, 
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $mysqli->prepare("INSERT INTO convites (email, token, perfil, criado_por, id_projeto) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sssii', $email, $token, $perfil, $idAdmin, $idProjeto);
    
    if ($stmt->execute()) {
        $link = "http://" . $_SERVER['HTTP_HOST'] . "/incubadora_ispsn/public/register.php?invite=" . $token;
        
        if ($action === 'enviar_convite') {
             // Tentar enviar e-mail real (opcional agora)
             require_once __DIR__ . '/../utils/Mailer.php';
             $assunto = "Convite para a Incubadora";
             \App\Utils\Mailer::send($email, $assunto, "Clique no link para se registar: $link");
             $_SESSION['flash_ok'] = "Convite registado!";
        } else {
             // Para geração manual, guardamos o link na sessão para mostrar no modal
             $_SESSION['convite_link'] = $link;
             $_SESSION['convite_msg'] = $mensagemCustom . " \n\nRegiste-se aqui: " . $link;
             $_SESSION['flash_ok'] = "Link de convite gerado com sucesso! Copie abaixo.";
        }
    } else {
        $_SESSION['flash_erro'] = "Erro ao gerar convite: " . $mysqli->error;
    }
    $stmt->close();

    header("Location: $redirect");
    exit;
}

header("Location: $redirect");
exit;
