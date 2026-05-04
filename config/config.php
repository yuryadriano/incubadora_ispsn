<?php
// config/config.php

// Configurações de ligação à BD
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'imcubadora_ispsn');

// Conexão com timeout curto para evitar 504 Gateway Timeout
$mysqli = mysqli_init();
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

// Suprimir warning para capturar o erro manualmente
$connected = @$mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$connected || $mysqli->connect_errno) {
    // Log do erro para diagnóstico
    error_log('[Incubadora] DB connection failed: ' . $mysqli->connect_error);

    // Resposta de emergência — evita que o servidor fique pendurado e cause 504
    http_response_code(503);
    header('Retry-After: 60');
    ?>
    <!DOCTYPE html>
    <html lang="pt-pt">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="refresh" content="30">
        <title>A carregar — Incubadora ISPSN</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Inter', sans-serif; background: #0F172A; color: #fff; display: flex; align-items: center; justify-content: center; min-height: 100vh; text-align: center; padding: 24px; }
            .box { max-width: 480px; }
            .logo { font-size: 2rem; font-weight: 900; color: #D97706; margin-bottom: 24px; }
            h1 { font-size: 1.5rem; margin-bottom: 12px; }
            p { color: rgba(255,255,255,0.5); line-height: 1.7; margin-bottom: 24px; }
            .spinner { width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.1); border-top-color: #D97706; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 24px; }
            @keyframes spin { to { transform: rotate(360deg); } }
            small { color: rgba(255,255,255,0.3); font-size: 0.75rem; }
        </style>
    </head>
    <body>
        <div class="box">
            <div class="spinner"></div>
            <div class="logo">ISPSN</div>
            <h1>Portal em manutenção</h1>
            <p>Estamos a realizar atualizações. O portal voltará ao normal em breve.<br>Esta página recarrega automaticamente em 30 segundos.</p>
            <small>Incubadora Académica ISPSN — Huambo, Angola</small>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$mysqli->set_charset('utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Função helper para escapar dados
function limpar($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// Função para buscar configurações do website
function get_config($chave, $default = '') {
    global $mysqli;
    static $config_cache = [];

    if (empty($config_cache)) {
        $res = $mysqli->query("SELECT chave, valor FROM config_website");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $config_cache[$r['chave']] = $r['valor'];
            }
        }
    }

    return $config_cache[$chave] ?? $default;
}

// Configurações de E-mail (SMTP)
define('MAIL_HOST', 'smtp.office365.com');
define('MAIL_USER', 'evaristo.adriano@ispsn.org');
define('MAIL_PASS', 'escoladohuambo');
define('MAIL_PORT', 587);
define('MAIL_FROM', 'evaristo.adriano@ispsn.org');
define('MAIL_NAME', 'Incubadora Académica ISPSN');

// Autoload do Composer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
