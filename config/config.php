<?php
// config/config.php

// Configurações de ligação à BD
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'imcubadora_ispsn');

// Versão fixa para cache busting de CSS/JS (alterar após cada deploy)
define('ASSET_VERSION', '2026110901');

// Limites estritos de socket e leitura MySQL para impedir bloqueio de workers Apache (504 Gateway Timeout)
@ini_set('default_socket_timeout', '5');
@ini_set('mysqlnd.net_read_timeout', '8');

// Configuração resiliente ultra-rápida (compatível com Cloudflare, evita 504 Gateway Timeout)
$maxTries = 3;
$connected = false;
$mysqli = null;

for ($attempt = 1; $attempt <= $maxTries; $attempt++) {
    $mysqli = @mysqli_init();
    if ($mysqli) {
        @$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
            @$mysqli->options(MYSQLI_OPT_READ_TIMEOUT, 5);
        }
        $connected = @$mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }

    if ($connected && $mysqli && !$mysqli->connect_errno) {
        break;
    }

    if ($attempt < $maxTries) {
        usleep(150000); // 0.15s entre tentativas (máximo 0.3s de espera total)
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
@$mysqli->query("SET SESSION innodb_lock_wait_timeout = 5");

// Guarda para reconectar com timeout estrito se a conexão oscilar no meio do script
function ensure_db_connection() {
    global $mysqli;
    if (!$mysqli || !@$mysqli->ping()) {
        $mysqli = @mysqli_init();
        if ($mysqli) {
            @$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
            if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
                @$mysqli->options(MYSQLI_OPT_READ_TIMEOUT, 5);
            }
            if (@$mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME)) {
                $mysqli->set_charset('utf8mb4');
                @$mysqli->query("SET SESSION innodb_lock_wait_timeout = 5");
            }
        }
    }
    return $mysqli;
}

if (!defined('NO_SESSION') && PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_set_cookie_params([
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Inicializar token CSRF imediatamente se não existir
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Helper para fecho antecipado manual em endpoints pesados
function close_session_early(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}


// Proteção de Schema: As migrações automáticas de DDL foram totalmente desativadas do ciclo de requisição HTTP para evitar bloqueios concorrentes (Metadata Locks).
// As migrações manuais exigem privilégios de SuperAdmin autenticado na sessão OU a chave configurada estritamente na variável de ambiente MIGRATION_KEY no servidor.
$migrationKey = getenv('MIGRATION_KEY');
$isAuthorizedAdmin = (!empty($_SESSION['usuario_perfil']) && in_array($_SESSION['usuario_perfil'], ['superadmin', 'admin']));
$hasValidKey = (!empty($migrationKey) && isset($_GET['key']) && !empty($_GET['key']) && hash_equals($migrationKey, $_GET['key']));

if (isset($_GET['run_migrations']) && $_GET['run_migrations'] === '1' && ($isAuthorizedAdmin || $hasValidKey)) {
    $schemaFile = __DIR__ . '/../app/controllers/update_schema.php';
    if (file_exists($schemaFile)) {
        ob_start();
        include $schemaFile;
        ob_end_clean();
    }
}

/**
 * Auto-healing para o módulo de convocatórias e histórico de candidaturas.
 * Executado sob demanda se uma consulta falhar por tabela inexistente.
 */
function garantirTabelasConvocatorias(?mysqli $db = null): bool {
    static $jaVerificado = false;
    if ($jaVerificado) return true;
    $jaVerificado = true;

    global $mysqli;
    $db = $db ?? $mysqli;
    if (!$db) return false;

    try {
        @$db->query("CREATE TABLE IF NOT EXISTS `convocatorias` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `candidatura_id` INT UNSIGNED NOT NULL,
            `data_reuniao` DATE NOT NULL,
            `hora_reuniao` TIME NOT NULL,
            `local_reuniao` VARCHAR(255) NOT NULL DEFAULT 'Gabinete da Incubadora Académica - ISPSN',
            `documentos_a_levar` TEXT NULL,
            `observacoes` TEXT NULL,
            `convocado_por` INT UNSIGNED NOT NULL,
            `convocado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `presenca_validada_em` DATETIME NULL DEFAULT NULL,
            `validado_por` INT UNSIGNED NULL DEFAULT NULL,
            `estado_convocatoria` ENUM('agendada','reagendada','compareceu','ausente','recusou','reprovada') NOT NULL DEFAULT 'agendada',
            `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_conv_cand` (`candidatura_id`),
            KEY `idx_conv_estado` (`estado_convocatoria`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        @$db->query("CREATE TABLE IF NOT EXISTS `candidatura_historico` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `candidatura_id` INT UNSIGNED NOT NULL,
            `estado_anterior` VARCHAR(50) NULL,
            `estado_novo` VARCHAR(50) NOT NULL,
            `alterado_por` INT UNSIGNED NOT NULL,
            `alterado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `nota` TEXT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_hist_cand` (`candidatura_id`),
            KEY `idx_hist_data` (`alterado_em`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // NOTA: ALTER TABLE sobre `candidaturas` removido do ciclo HTTP para evitar Exclusive Metadata Locks (504 Gateway Timeout).
        // Qualquer migração de coluna em `candidaturas` corre exclusivamente via update_schema.php.
        return true;
    } catch (Throwable $e) {
        error_log("Erro ao auto-criar tabelas de convocatórias: " . $e->getMessage());
        return false;
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
    if (empty($_POST)) {
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMaxRaw    = trim((string)ini_get('post_max_size'));
        $postMaxBytes  = 0;
        if ($postMaxRaw !== '' && $postMaxRaw !== '0') {
            if (function_exists('ini_parse_quantity')) {
                $postMaxBytes = ini_parse_quantity($postMaxRaw);
            } else {
                $unit = strtolower(substr($postMaxRaw, -1));
                $postMaxBytes = (int)$postMaxRaw;
                if ($unit === 'g') $postMaxBytes *= 1073741824;
                elseif ($unit === 'm') $postMaxBytes *= 1048576;
                elseif ($unit === 'k') $postMaxBytes *= 1024;
            }
        }
        if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
            http_response_code(413);
            die('<div style="font-family:sans-serif;text-align:center;padding:60px"><h2>&#x26A0;&#xFE0F; Ficheiro Demasiado Grande</h2><p>O tamanho do envio excede o limite máximo permitido pelo servidor (' . htmlspecialchars($postMaxRaw, ENT_QUOTES, 'UTF-8') . '). Por favor, reduza o tamanho do ficheiro e tente novamente.</p><a href="javascript:history.back()" style="display:inline-block;margin-top:20px;padding:10px 24px;background:#D97706;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">Voltar</a></div>');
        }
    }
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


