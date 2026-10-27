-- ==============================================================================
-- MIGRAÇÃO IDEMPOTENTE: Módulo de Convocatórias e Validação Presencial
-- Incubadora Académica ISPSN
-- ==============================================================================

-- 1. EXPANDIR ENUM DE ESTADOS DA TABELA candidaturas (NOVOS VALORES NO FIM)
ALTER TABLE `candidaturas` 
MODIFY COLUMN `estado` ENUM(
    'pendente',
    'em_analise',
    'selecionado',
    'rejeitado',
    'convite_enviado',
    'registado',
    'convocado',
    'validado_presencial',
    'ausente',
    'recusou',
    'reprovado_reuniao'
) COLLATE utf8mb4_unicode_ci DEFAULT 'pendente';

-- 2. TABELA DE CONVOCATÓRIAS / REUNIÕES
CREATE TABLE IF NOT EXISTS `convocatorias` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABELA DE HISTÓRICO DE TRANSIÇÕES (AUDITORIA FORENSE)
CREATE TABLE IF NOT EXISTS `candidatura_historico` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- ROLLBACK SCRIPT:
-- ==============================================================================
-- DROP TABLE IF EXISTS `candidatura_historico`;
-- DROP TABLE IF EXISTS `convocatorias`;
-- ALTER TABLE `candidaturas` 
-- MODIFY COLUMN `estado` ENUM('pendente','em_analise','selecionado','rejeitado','convite_enviado','registado') 
-- COLLATE utf8mb4_unicode_ci DEFAULT 'pendente';
