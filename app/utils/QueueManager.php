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
     * Processa os e-mails pendentes na fila. Utiliza locks e limite de tempo estrito para evitar tempo limite.
     */
    public static function processar() {
        global $mysqli;
        
        $lockFile = __DIR__ . '/processar_fila_emails.lock';
        $isCLI = (PHP_SAPI === 'cli');
        
        // Cooldown em requisições Web: evita processar a fila mais do que uma vez a cada 15 segundos no servidor Web.
        // Se for CLI (cron), permite execução normal.
        if (!$isCLI && file_exists($lockFile) && (time() - filemtime($lockFile) < 15)) {
            return;
        }
        
        $fp = fopen($lockFile, 'c');
        if (!$fp) return;
        
        // Evita concorrência: apenas um processo pode obter o lock exclusivo sem bloquear
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return; 
        }
        
        // Atualizar o timestamp para marcar o início do processamento actual
        touch($lockFile);
        
        $startTime = time();
        $maxRunSeconds = 10; // Máximo de 10 segundos por ciclo de processamento
        
        try {
            // Obter até 5 e-mails pendentes com menos de 3 tentativas por lote
            $res = $mysqli->query("
                SELECT * FROM fila_emails 
                WHERE estado = 'pendente' AND tentativas < 3 
                ORDER BY id ASC
                LIMIT 5
            ");
            
            if ($res && $res->num_rows > 0) {
                $emails = [];
                while ($row = $res->fetch_assoc()) {
                    $emails[] = $row;
                }
                
                foreach ($emails as $email) {
                    // Guard de tempo: parar se exceder o tempo limite seguro de execução
                    if ((time() - $startTime) >= $maxRunSeconds) {
                        break;
                    }
                    
                    $id = (int)$email['id'];
                    $tentativaAtual = (int)$email['tentativas'] + 1;
                    
                    $mysqli->query("UPDATE fila_emails SET tentativas = $tentativaAtual WHERE id = $id");
                    
                    $errorInfo = "";
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
    }
    
    public static function dispararAssincrono() {
        $script = __DIR__ . '/processar_fila_emails.php';
        $script = realpath($script);
        if (!$script) return;
        
        // Detetar corretamente o binário PHP CLI em ambientes Windows ou Linux (cPanel / Docker)
        $phpPath = 'php';
        if (PHP_SAPI === 'cli' && defined('PHP_BINARY') && !empty(PHP_BINARY)) {
            $phpPath = PHP_BINARY;
        } elseif (defined('PHP_BINDIR') && !empty(PHP_BINDIR)) {
            $candidate = PHP_BINDIR . (str_starts_with(strtoupper(PHP_OS), 'WIN') ? '/php.exe' : '/php');
            if (file_exists($candidate)) {
                $phpPath = $candidate;
            }
        }
        
        // Se ainda for apenas 'php', procurar em caminhos padrão de servidores cPanel/Linux
        if ($phpPath === 'php' && !str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            $commonPaths = [
                '/usr/bin/php',
                '/usr/local/bin/php',
                '/opt/cpanel/ea-php82/root/usr/bin/php',
                '/opt/cpanel/ea-php81/root/usr/bin/php',
                '/opt/cpanel/ea-php80/root/usr/bin/php',
                '/opt/cpanel/ea-php74/root/usr/bin/php'
            ];
            foreach ($commonPaths as $p) {
                if (file_exists($p) && is_executable($p)) {
                    $phpPath = $p;
                    break;
                }
            }
        }
        
        if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            if (function_exists('popen')) {
                @pclose(popen("start /B " . escapeshellarg($phpPath) . " " . escapeshellarg($script) . " > NUL 2>&1", "r"));
            }
        } else {
            // Linux / Unix
            if (function_exists('exec')) {
                @exec(escapeshellarg($phpPath) . " " . escapeshellarg($script) . " > /dev/null 2>&1 < /dev/null &");
            }
        }
    }
}

