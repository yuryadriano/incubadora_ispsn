<?php
// app/controllers/convite_action.php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/config.php';

obrigarPerfil(['admin', 'superadmin']);

csrf_verificar();

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
             require_once __DIR__ . '/../utils/Mailer.php';
             $assunto = "Convite Oficial — Incubadora Académica ISPSN";
             
             $introMsg = !empty($mensagemCustom) ? nl2br(limpar($mensagemCustom)) : "Foste convidado a integrar a <strong>Incubadora Académica do ISPSN</strong> como <strong>" . ($perfil === 'mentor' ? 'Mentor Externo' : 'Empreendedor/Estudante') . "</strong>.";
             
             $body = "
             <!DOCTYPE html>
             <html>
             <head>
                 <meta charset='utf-8'>
                 <style>
                     body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 0; }
                     .email-container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; }
                     .header { background: #0f172a; padding: 32px; text-align: center; border-bottom: 3px solid #f59e0b; }
                     .logo { font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; }
                     .logo span { color: #f59e0b; }
                     .content { padding: 40px; line-height: 1.6; }
                     .greeting { font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 12px; }
                     .intro-box { font-size: 15px; color: #475569; margin-bottom: 24px; }
                     .invite-card { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 24px; text-align: center; margin: 24px 0; }
                     .invite-role { font-size: 13px; font-weight: 700; text-transform: uppercase; color: #f59e0b; letter-spacing: 1px; margin-bottom: 8px; }
                     .button { display: inline-block; padding: 14px 32px; font-size: 14px; font-weight: 700; color: #ffffff !important; background: #f59e0b; border-radius: 8px; text-decoration: none; text-align: center; box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.2); }
                     .footer { background: #f8fafc; padding: 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
                     .footer a { color: #f59e0b; text-decoration: none; font-weight: 600; }
                 </style>
             </head>
             <body>
                 <div class='email-container'>
                     <div class='header'>
                         <div class='logo'>INCUBADORA<span>ISPSN</span></div>
                     </div>
                     <div class='content'>
                         <div class='greeting'>Bem-vindo à nossa comunidade!</div>
                         <div class='intro-box'>
                             {$introMsg}
                         </div>
                         <p>Para concluir a tua ativação e registar os teus dados de acesso no nosso ecossistema, clica no botão abaixo:</p>
                         
                         <div class='invite-card'>
                             <div class='invite-role'>Perfil Atribuído: " . ($perfil === 'mentor' ? 'Mentor Externo' : 'Empreendedor') . "</div>
                             <div style='margin-top: 16px;'>
                                 <a href='{$link}' class='button'>Ativar Minha Conta</a>
                             </div>
                         </div>
                         
                         <p style='font-size: 13px; color: #64748b; margin-top: 24px;'>Caso o botão não funcione, podes copiar e colar o seguinte link no teu navegador:<br>
                         <a href='{$link}' style='color:#f59e0b; word-break:break-all;'>{$link}</a></p>
                     </div>
                     <div class='footer'>
                         Este é um convite oficial enviado pela Incubadora Académica ISPSN.<br>
                         &copy; " . date('Y') . " ISPSN. Todos os direitos reservados.<br>
                         <a href='http://www.ispsn.org' target='_blank'>Website Oficial</a>
                     </div>
                 </div>
             </body>
             </html>
             ";
             
             $mailErr = "";
             \App\Utils\Mailer::send($email, $assunto, $body, $mailErr);
             $_SESSION['flash_ok'] = "Convite registado e enviado por e-mail com sucesso!";
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
