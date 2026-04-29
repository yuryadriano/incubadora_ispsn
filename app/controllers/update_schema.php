<?php
require_once __DIR__ . '/../../config/config.php';

// Adicionar coluna galeria se não existir
$mysqli->query("ALTER TABLE publicacoes_website ADD COLUMN IF NOT EXISTS galeria TEXT DEFAULT NULL AFTER conteudo");

echo "Schema updated!";
