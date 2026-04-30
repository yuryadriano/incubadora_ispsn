<?php
// config/config.php

// Configurações de ligação à BD
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'imcubadora_ispsn');

$mysqli = mysqli_init();
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
$mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_errno) {
    die('Erro na ligação à base de dados (Timeout): ' . $mysqli->connect_error);
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
