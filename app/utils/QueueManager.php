<?php
// app/utils/QueueManager.php
namespace App\Utils;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/Mailer.php';

class QueueManager {
    
    /**
     * Adiciona um e-mail à fila de envio assíncrono.
     */
    public static function adicionar($destinatario, $assunto, $corpo, $anexo = null, &$error = "") {
        global $mysqli;
        
        try {
            $stmt = $mysqli->prepare("
                INSERT INTO fila_emails (destinatario, assunto, corpo, anexo, estado, tentativas) 
                VALUES (?, ?, ?, ?, 'pendente', 0)
            ");
            if (!$stmt) {
                $error = "Erro ao preparar query da fila: " . $mysqli->error;
                return false;
            }
            
            $stmt->bind_param('ssss', $destinatario, $assunto, $corpo, $anexo);
            $res = $stmt->execute();
            $stmt->close();
            
            if ($res) {
                // Disparar o processamento em background de forma assíncrona
                self::dispararAssincrono();
                return true;
            }
        } catch (\Exception $e) {
            $error = "Erro na base de dados (fila_emails pode não existir): " . $e->getMessage();
            error_log("Erro QueueManager::adicionar: " . $e->getMessage());
            return false;
        }
        
        $error = "Erro ao inserir na fila de e-mails.";
        return false;
    }
    
    /**
     * Processa os e-mails pendentes na fila. Utiliza locks para evitar execução concorrente.
     */
    public static function processar() {
        global $mysqli;
        
        $lockFile = __DIR__ . '/processar_fila_emails.lock';
        $fp = fopen($lockFile, 'c');
        if (!$fp) return;
        
        // Evita concorrência: apenas um processo pode obter o lock exclusivo sem bloquear
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return; 
        }
        
        try {
            // Obter até 10 e-mails pendentes com menos de 3 tentativas
            $res = $mysqli->query("
                SELECT * FROM fila_emails 
                WHERE estado = 'pendente' AND tentativas < 3 
                LIMIT 10
            ");
            
            if ($res && $res->num_rows > 0) {
                $emails = [];
                while ($row = $res->fetch_assoc()) {
                    $emails[] = $row;
                }
                
                foreach ($emails as $email) {
                    $id = (int)$email['id'];
                    $tentativaAtual = (int)$email['tentativas'] + 1;
                    
                    // Apenas incrementa as tentativas no banco para evitar que e-mails fiquem presos como "enviados"
                    // se o script for interrompido a meio do envio. O lock de arquivo garante que não há concorrência.
                    $mysqli->query("UPDATE fila_emails SET tentativas = $tentativaAtual WHERE id = $id");
                    
                    $errorInfo = "";
                    // Usamos o método direto (síncrono/real) do Mailer
                    $sucesso = Mailer::sendImmediate($email['destinatario'], $email['assunto'], $email['corpo'], $errorInfo, $email['anexo']);
                    
                    if ($sucesso) {
                        $mysqli->query("UPDATE fila_emails SET estado = 'enviado', processado_em = NOW(), erro_mensagem = NULL WHERE id = $id");
                    } else {
                        $erroMsgEscaped = $mysqli->real_escape_string($errorInfo);
                        $novoEstado = $tentativaAtual >= 3 ? 'erro' : 'pendente';
                        
                        $mysqli->query("
                            UPDATE fila_emails 
                            SET estado = '$novoEstado', erro_mensagem = '$erroMsgEscaped', processado_em = NOW() 
                            WHERE id = $id
                        ");
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Erro QueueManager::processar: " . $e->getMessage());
        }
        
        flock($fp, LOCK_UN);
        fclose($fp);
        @unlink($lockFile);
    }
    
    /**
     * Dispara um processo em segundo plano (background) para processar a fila.
     */
    public static function dispararAssincrono() {
        $script = __DIR__ . '/processar_fila_emails.php';
        $script = realpath($script);
        if (!$script) return;
        
        // Obter o binário correto do PHP executado, com fallback seguro para "php"
        $phpPath = 'php';
        if (defined('PHP_BINARY') && !empty(PHP_BINARY) && strpos(PHP_BINARY, 'fpm') === false && strpos(PHP_BINARY, 'cgi') === false) {
            $phpPath = PHP_BINARY;
        }
        
        if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            // Windows (XAMPP local)
            pclose(popen("start /B " . escapeshellarg($phpPath) . " " . escapeshellarg($script) . " > NUL 2>&1", "r"));
        } else {
            // Linux / Unix (Servidor de produção)
            exec(escapeshellarg($phpPath) . " " . escapeshellarg($script) . " > /dev/null 2>&1 &");
        }
    }
}
