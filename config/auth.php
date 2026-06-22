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
    $executado = $stmt->execute();
    $stmt->close();

    // Enviar e-mail de notificação em background
    $stmtU = $mysqli->prepare("SELECT nome, email FROM usuarios WHERE id = ? LIMIT 1");
    if ($stmtU) {
        $stmtU->bind_param('i', $idUsuario);
        $stmtU->execute();
        $stmtU->bind_result($nome, $email);
        if ($stmtU->fetch()) {
            $stmtU->close();
            
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                require_once __DIR__ . '/../app/utils/Mailer.php';
                
                $colorMap = [
                    'sucesso' => '#10b981',
                    'erro'    => '#ef4444',
                    'warning' => '#f59e0b',
                    'info'    => '#3b82f6'
                ];
                $color = $colorMap[$tipo] ?? '#3b82f6';
                
                $body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='utf-8'>
                    <style>
                        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 0; }
                        .email-container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; }
                        .header { background: #0f172a; padding: 32px; text-align: center; border-bottom: 3px solid {$color}; }
                        .logo { font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; }
                        .logo span { color: #f59e0b; }
                        .content { padding: 40px; line-height: 1.6; }
                        .greeting { font-size: 18px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 12px; }
                        .title-badge { display: inline-block; padding: 6px 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; border-radius: 9999px; background: " . $color . "15; color: {$color}; margin-bottom: 24px; letter-spacing: 0.5px; }
                        .message-box { background: #f1f5f9; border-left: 4px solid {$color}; padding: 20px; border-radius: 8px; margin: 24px 0; color: #334155; font-size: 15px; }
                        .button { display: inline-block; padding: 12px 28px; font-size: 14px; font-weight: 600; color: #ffffff !important; background: #0f172a; border-radius: 8px; text-decoration: none; margin-top: 16px; text-align: center; }
                        .footer { background: #f8fafc; padding: 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
                        .footer a { color: {$color}; text-decoration: none; font-weight: 600; }
                    </style>
                </head>
                <body>
                    <div class='email-container'>
                        <div class='header'>
                            <div class='logo'>INCUBADORA<span>ISPSN</span></div>
                        </div>
                        <div class='content'>
                            <div class='greeting'>Olá, " . htmlspecialchars(explode(' ', $nome)[0]) . "!</div>
                            <div class='title-badge'>" . htmlspecialchars($titulo) . "</div>
                            <p>Tens uma nova notificação no sistema de gestão da Incubadora ISPSN:</p>
                            <div class='message-box'>
                                " . nl2br(htmlspecialchars($mensagem)) . "
                            </div>
                            <div style='text-align: center; margin-top: 32px;'>
                                <a href='http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/incubadora_ispsn/public/login.php' class='button'>Aceder ao Painel</a>
                            </div>
                        </div>
                        <div class='footer'>
                            Este é um e-mail automático enviado pelo Sistema da Incubadora Académica ISPSN.<br>
                            &copy; " . date('Y') . " ISPSN. Todos os direitos reservados.<br>
                            <a href='http://www.ispsn.org' target='_blank'>Website Oficial</a>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $assunto = "Notificação: " . $titulo;
                $mailErr = "";
                \App\Utils\Mailer::send($email, $assunto, $body, $mailErr);
            }
        } else {
            $stmtU->close();
        }
    }

    return $executado;
}
