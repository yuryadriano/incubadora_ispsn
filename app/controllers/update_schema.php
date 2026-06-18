<?php
require_once __DIR__ . '/../../config/config.php';

// Adicionar coluna galeria se não existir
$mysqli->query("ALTER TABLE publicacoes_website ADD COLUMN IF NOT EXISTS galeria TEXT DEFAULT NULL AFTER conteudo");

// Adicionar colunas de evidência na tabela de tarefas
$mysqli->query("ALTER TABLE tarefas ADD COLUMN IF NOT EXISTS evidencia_path VARCHAR(255) DEFAULT NULL");
$mysqli->query("ALTER TABLE tarefas ADD COLUMN IF NOT EXISTS evidencia_nota TEXT DEFAULT NULL");
$mysqli->query("ALTER TABLE tarefas ADD COLUMN IF NOT EXISTS validada_mentor TINYINT(1) DEFAULT 0");

// Criar tabelas adicionais de reservas, espaços, equipamentos, visitantes e empréstimos
$mysqli->query("CREATE TABLE IF NOT EXISTS `espacos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `tipo` VARCHAR(50) NOT NULL DEFAULT 'mesa',
  `capacidade` INT NOT NULL DEFAULT 1,
  `status` VARCHAR(50) NOT NULL DEFAULT 'disponivel',
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$mysqli->query("CREATE TABLE IF NOT EXISTS `reservas_espaco` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_espaco` INT UNSIGNED NOT NULL,
  `id_usuario` INT UNSIGNED NOT NULL,
  `data_reserva` DATE NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fim` TIME NOT NULL,
  `objetivo` TEXT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pendente',
  `check_in_at` DATETIME NULL DEFAULT NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `fk_res_espaco` (`id_espaco`),
  KEY `fk_res_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$mysqli->query("CREATE TABLE IF NOT EXISTS `equipamentos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `codigo_patrimonio` VARCHAR(100) NOT NULL UNIQUE,
  `estado` VARCHAR(50) NOT NULL DEFAULT 'disponivel',
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$mysqli->query("CREATE TABLE IF NOT EXISTS `visitantes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `documento_identidade` VARCHAR(100) NULL,
  `empresa_motivo` VARCHAR(255) NULL,
  `id_usuario_visitado` INT UNSIGNED NULL,
  `data_entrada` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `data_saida` DATETIME NULL DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'presente',
  KEY `fk_visit_usuario` (`id_usuario_visitado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$mysqli->query("CREATE TABLE IF NOT EXISTS `emprestimos_equipamento` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_equipamento` INT UNSIGNED NOT NULL,
  `id_usuario` INT UNSIGNED NOT NULL,
  `data_emprestimo` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `data_devolucao_prevista` DATE NULL,
  `data_devolucao_real` DATETIME NULL DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'ativo',
  KEY `fk_emp_equip` (`id_equipamento`),
  KEY `fk_emp_user` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Schema updated!";
