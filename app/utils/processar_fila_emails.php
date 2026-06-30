<?php
// app/utils/processar_fila_emails.php

/**
 * Script CLI para processar a fila de e-mails de forma assíncrona.
 * Pode ser executado em background via CLI ou por cronjob.
 */

// Garantir que a execução não pare por limite de tempo
set_time_limit(180);

require_once __DIR__ . '/QueueManager.php';

use App\Utils\QueueManager;

// Invoca o processamento
QueueManager::processar();
