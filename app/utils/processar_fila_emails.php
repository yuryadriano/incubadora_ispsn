<?php
// app/utils/processar_fila_emails.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Apenas CLI.');
}

/**
 * Script CLI para processar a fila de e-mails de forma assíncrona.
 * Pode ser executado em background via CLI ou por cronjob.
 */

// Garantir execução rápida e sem sessão HTTP
define('NO_SESSION', true);
set_time_limit(25);

require_once __DIR__ . '/QueueManager.php';

use App\Utils\QueueManager;

// Invoca o processamento
QueueManager::processar();
