<?php
// config/config.php

// Configurações de ligação à BD
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'imcubadora_ispsn');

// Versão fixa para cache busting de CSS/JS (alterar após cada deploy)
define('ASSET_VERSION', '2026061601');

$mysqli = mysqli_init();
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);

// Timeout de leitura — evita queries suspensas indefinidamente
if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
    $mysqli->options(MYSQLI_OPT_READ_TIMEOUT, 5);
}

$connected = @$mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$connected || $mysqli->connect_errno) {
    http_response_code(503);
    header('Retry-After: 30');
    // Permitir que o Cloudflare sirva uma cópia antiga (stale) se existir
    header('Cache-Control: public, max-age=0, stale-if-error=86400');
    // SEM auto-refresh — evita loop de requests que sobrecarrega o servidor
    die('<div style="font-family: sans-serif; text-align: center; padding: 50px; color: #333;">
        <h2>⚠️ Serviço Temporariamente Indisponível</h2>
        <p>O servidor de base de dados não está acessível neste momento.</p>
        <p style="color:#888;font-size:0.9rem;">Por favor, tente novamente dentro de 30 segundos.</p>
        <a href="javascript:location.reload()" style="display:inline-block;margin-top:20px;padding:10px 24px;background:#D97706;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">Tentar Novamente</a>
    </div>');
}

$mysqli->set_charset('utf8mb4');

// Auto-verificação de Schema: Se a tabela metas_projeto não existir, executa o update_schema.php automaticamente
$checkTable = $mysqli->query("SHOW TABLES LIKE 'metas_projeto'");
if ($checkTable && $checkTable->num_rows === 0) {
    $schemaFile = __DIR__ . '/../app/controllers/update_schema.php';
    if (file_exists($schemaFile)) {
        ob_start();
        include $schemaFile;
        ob_end_clean();
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

// Defina IS_DEV = true apenas em ambiente local (XAMPP)
// Em produção, remova esta linha ou defina como false
if (!defined('IS_DEV')) define('IS_DEV', true); // ⚠️ Alterar para false em produção!

// Autoload do Composer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
