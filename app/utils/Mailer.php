<?php
// app/utils/Mailer.php
namespace App\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/QueueManager.php';

class Mailer {
    public static function send($to, $subject, $body, &$error = "", $attachmentPath = null) {
        return QueueManager::adicionar($to, $subject, $body, $attachmentPath, $error);
    }

    public static function sendImmediate($to, $subject, $body, &$error = "", $attachmentPath = null) {
        $mail = new PHPMailer(true);

        try {
            // Configurações do servidor
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->Timeout    = 8;
            
            if (MAIL_PORT == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->Port       = MAIL_PORT;
            $mail->CharSet    = 'UTF-8';

            // Correção para ambientes locais (XAMPP) que falham na validação SSL
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Destinatários
            $mail->setFrom(MAIL_FROM, MAIL_NAME);
            $mail->addAddress($to);

            // Anexo
            if ($attachmentPath !== null && file_exists($attachmentPath)) {
                $mail->addAttachment($attachmentPath);
            }

            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            $error = $mail->ErrorInfo;
            error_log("Erro ao enviar e-mail: {$mail->ErrorInfo}");
            return false;
        }
    }
}
