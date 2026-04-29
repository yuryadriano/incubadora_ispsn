<?php
require_once __DIR__ . '/../config/config.php';
$sql = "CREATE TABLE IF NOT EXISTS relatorios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_projeto INT UNSIGNED NOT NULL,
    id_autor INT UNSIGNED NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    ficheiro VARCHAR(255),
    tipo ENUM('mensal', 'bimestral', 'trimestral', 'feedback', 'outro') DEFAULT 'mensal',
    destino ENUM('admin', 'startup', 'todos') DEFAULT 'admin',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
)";
if ($mysqli->query($sql)) {
    echo "Table 'relatorios' created successfully.\n";
} else {
    echo "Error creating table: " . $mysqli->error . "\n";
}
