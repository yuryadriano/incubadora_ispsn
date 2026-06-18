<?php
require_once __DIR__ . '/../../config/config.php';

// Adicionar coluna galeria se não existir
$mysqli->query("ALTER TABLE publicacoes_website ADD COLUMN IF NOT EXISTS galeria TEXT DEFAULT NULL AFTER conteudo");

// Adicionar colunas de evidência na tabela de tarefas
$mysqli->query("ALTER TABLE tarefas ADD COLUMN IF NOT EXISTS evidencia_path VARCHAR(255) DEFAULT NULL");
$mysqli->query("ALTER TABLE tarefas ADD COLUMN IF NOT EXISTS evidencia_nota TEXT DEFAULT NULL");
$mysqli->query("ALTER TABLE tarefas ADD COLUMN IF NOT EXISTS validada_mentor TINYINT(1) DEFAULT 0");

echo "Schema updated!";
