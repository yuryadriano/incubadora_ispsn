<?php
// config/config.php

// Configurações de ligação à BD
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'imcubadora_ispsn');

// Versão fixa para cache busting de CSS/JS (alterar após cada deploy)
define('ASSET_VERSION', '2026062403');

// Configuração resiliente ultra-rápida (compatível com Cloudflare, evita 504 Gateway Timeout)
$maxTries = 4;
$connected = false;
$mysqli = null;

for ($attempt = 1; $attempt <= $maxTries; $attempt++) {
    if (function_exists('mysqli_init')) {
        $mysqli = @mysqli_init();
        if ($mysqli) {
            @$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
            $connected = @$mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        }
    }
    
    // Fallback para mysqli_connect padrão se real_connect oscilar
    if (!$connected || !$mysqli || $mysqli->connect_errno) {
        $mysqli = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli && !mysqli_connect_errno()) {
            $connected = true;
        }
    }
    
    if ($connected && $mysqli && !$mysqli->connect_errno) {
        break;
    }
    
    if ($attempt < $maxTries) {
        usleep(250000); // 0.25 segundos de micro-espera (máximo 1s total)
    }
}

if (!$connected || !$mysqli || $mysqli->connect_errno) {
    $errMessage = ($mysqli && $mysqli->connect_error) ? $mysqli->connect_error : mysqli_connect_error();
    error_log("DB connection failed after {$maxTries} attempts: " . $errMessage);
    http_response_code(503);
    header('Retry-After: 8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    die('<div style="font-family: sans-serif; text-align: center; padding: 50px; color: #333;">
        <h2 style="color:#d97706;">⏳ A Conectar ao Servidor...</h2>
        <p>O servidor de base de dados está temporariamente a inicializar ou indisponível.</p>
        <p style="color:#888;font-size:0.9rem;">O sistema tentará reconectar automaticamente em 8 segundos.</p>
        <meta http-equiv="refresh" content="8">
        <a href="javascript:location.reload()" style="display:inline-block;margin-top:20px;padding:10px 24px;background:#D97706;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">Recarregar Agora</a>
    </div>');
}

$mysqli->set_charset('utf8mb4');

// Guarda para reconectar automaticamente se a conexão oscilar no meio do script
function ensure_db_connection() {
    global $mysqli;
    if (!$mysqli || !@$mysqli->ping()) {
        $mysqli = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli) {
            $mysqli->set_charset('utf8mb4');
        }
    }
    return $mysqli;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inicializar token CSRF imediatamente se não existir
if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

// Helper para fecho antecipado manual em endpoints pesados
function close_session_early(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}


// Auto-verificação de Schema: Para evitar bloqueios concorrentes e erro 504 no servidor de produção,
// as migrações automáticas foram desativadas de cada requisição.
// Para correr as migrações na base de dados, aceda a qualquer página com o parâmetro '?run_migrations=1' na URL.
$runUpdate = false;
if (isset($_GET['run_migrations']) && $_GET['run_migrations'] == '1') {
    $runUpdate = true;
}

if ($runUpdate) {
    $schemaFile = __DIR__ . '/../app/controllers/update_schema.php';
    if (file_exists($schemaFile)) {
        ob_start();
        include $schemaFile;
        ob_end_clean();
    }
}

/* =============================================================
   CSRF PROTECTION
   Uso nas views: <?= csrf_field() ?>
   Uso nos controllers: csrf_verificar();
   ============================================================= */
function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verificar(): void {
    $token     = $_POST['_csrf_token'] ?? '';
    $esperado  = $_SESSION['_csrf_token'] ?? '';
    if (!$token || !$esperado || !hash_equals($esperado, $token)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;text-align:center;padding:60px"><h2>&#x26A0;&#xFE0F; Pedido Inválido</h2><p>Token de segurança inválido ou expirado. Por favor, recarregue a página e tente novamente.</p><a href="javascript:history.back()" style="display:inline-block;margin-top:20px;padding:10px 24px;background:#D97706;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">Voltar</a></div>');
    }
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
// IMPORTANTE: Defina estas variáveis no servidor/painel de hospedagem ou num ficheiro .env
// NÃO USE senhas hardcoded em produção!
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.office365.com');
define('MAIL_USER', getenv('MAIL_USER') ?: 'evaristo.adriano@ispsn.org');
// MAIL_PASS: DEVE ser definida via variável de ambiente em produção
define('MAIL_PASS', getenv('MAIL_PASS') ?: (defined('IS_DEV') && IS_DEV ? 'escoladohuambo' : ''));
define('MAIL_PORT', getenv('MAIL_PORT') ? (int)getenv('MAIL_PORT') : 587);
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'evaristo.adriano@ispsn.org');
define('MAIL_NAME', getenv('MAIL_NAME') ?: 'Incubadora Académica ISPSN');

// Detecção automática de Ambiente (IS_DEV)
// Super seguro: define true para Windows/XAMPP local e false para o container Docker (Linux)
if (!defined('IS_DEV')) {
    $isLocal = (str_starts_with(strtoupper(PHP_OS), 'WIN') || getenv('DB_HOST') === false || getenv('DB_HOST') === '127.0.0.1');
    define('IS_DEV', $isLocal);
}



// Autoload do Composer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Fila de E-mails: garante persistência de sessão e apenas processa em shutdown se a resposta HTTP já tiver sido entregue ao cliente (PHP-FPM)
register_shutdown_function(function() {
    close_session_early();
    
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
        if (file_exists(__DIR__ . '/../app/utils/QueueManager.php')) {
            require_once __DIR__ . '/../app/utils/QueueManager.php';
            \App\Utils\QueueManager::processar();
        }
    }
});


