-- ============================================================
-- SCHEMA COMPLETO — Incubadora Académica ISPSN
-- BD: imcubadora_ispsn
-- Execute no phpMyAdmin ou via MySQL CLI
-- ============================================================

USE `imcubadora_ispsn`;

-- ------------------------------------------------------------
-- TABELA: avaliacoes
-- Avaliações feitas pelos admins aos projectos submetidos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avaliacoes` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`      INT UNSIGNED    NOT NULL,
  `id_avaliador`    INT UNSIGNED    NOT NULL,
  `nota_inovacao`   TINYINT         NOT NULL DEFAULT 0 COMMENT '0-10',
  `nota_viabilidade` TINYINT        NOT NULL DEFAULT 0 COMMENT '0-10',
  `nota_impacto`    TINYINT         NOT NULL DEFAULT 0 COMMENT '0-10',
  `nota_equipa`     TINYINT         NOT NULL DEFAULT 0 COMMENT '0-10',
  `pontuacao_total` TINYINT         NOT NULL DEFAULT 0 COMMENT 'Calculado: média',
  `observacoes`     TEXT            NULL,
  `decisao`         ENUM('pendente','aprovado','rejeitado','em_revisao') NOT NULL DEFAULT 'pendente',
  `avaliado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_aval_projeto` (`id_projeto`),
  KEY `fk_aval_avaliador` (`id_avaliador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: mentores
-- Docentes ou profissionais externos registados como mentores
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mentores` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_usuario`      INT UNSIGNED    NOT NULL,
  `especialidade`   VARCHAR(150)    NOT NULL,
  `bio`             TEXT            NULL,
  `linkedin`        VARCHAR(255)    NULL,
  `disponivel`      TINYINT(1)      NOT NULL DEFAULT 1,
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_mentor_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: mentorias
-- Relação entre mentor e projecto (programa de mentoria)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mentorias` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`      INT UNSIGNED    NOT NULL,
  `id_mentor`       INT UNSIGNED    NOT NULL,
  `data_inicio`     DATE            NULL,
  `data_fim`        DATE            NULL,
  `estado`          ENUM('activa','concluida','cancelada') NOT NULL DEFAULT 'activa',
  `notas`           TEXT            NULL,
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ment_projeto` (`id_projeto`),
  KEY `fk_ment_mentor` (`id_mentor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: sessoes_mentoria
-- Registo de cada sessão individual de mentoria
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessoes_mentoria` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_mentoria`     INT UNSIGNED    NOT NULL,
  `data_sessao`     DATE            NOT NULL,
  `duracao_min`     INT             NOT NULL DEFAULT 60,
  `topicos`         TEXT            NULL,
  `proximos_passos` TEXT            NULL,
  `avaliacao_mentor` TINYINT        NULL COMMENT '1-5',
  `avaliacao_equipa` TINYINT        NULL COMMENT '1-5',
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_sess_mentoria` (`id_mentoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: financiamentos
-- Controlo financeiro por projecto
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `financiamentos` (
  `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`          INT UNSIGNED    NOT NULL,
  `fonte`               VARCHAR(200)    NOT NULL COMMENT 'Ex: ISPSN, BPC, Concurso X',
  `montante_aprovado`   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `montante_executado`  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `data_aprovacao`      DATE            NULL,
  `data_limite`         DATE            NULL,
  `estado`              ENUM('pendente','activo','concluido','cancelado') NOT NULL DEFAULT 'pendente',
  `descricao`           TEXT            NULL,
  `criado_em`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_fin_projeto` (`id_projeto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: despesas
-- Despesas registadas por financiamento
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `despesas` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_financiamento` INT UNSIGNED   NOT NULL,
  `descricao`       VARCHAR(255)    NOT NULL,
  `valor`           DECIMAL(12,2)   NOT NULL,
  `data_despesa`    DATE            NOT NULL,
  `categoria`       VARCHAR(100)    NULL,
  `justificativo`   VARCHAR(255)    NULL COMMENT 'Path para ficheiro PDF/imagem',
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_desp_financiamento` (`id_financiamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: kpis
-- Indicadores definidos por projecto
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kpis` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`      INT UNSIGNED    NOT NULL,
  `nome`            VARCHAR(150)    NOT NULL COMMENT 'Ex: Utilizadores activos, Receita mensal',
  `unidade`         VARCHAR(50)     NOT NULL DEFAULT 'unidades' COMMENT 'Ex: Kz, %, pessoas',
  `meta`            DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `periodicidade`   ENUM('mensal','trimestral','semestral','anual') NOT NULL DEFAULT 'trimestral',
  `activo`          TINYINT(1)      NOT NULL DEFAULT 1,
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_kpi_projeto` (`id_projeto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: registos_kpi
-- Valores históricos de cada KPI
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registos_kpi` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_kpi`          INT UNSIGNED    NOT NULL,
  `valor`           DECIMAL(14,2)   NOT NULL,
  `periodo`         VARCHAR(20)     NOT NULL COMMENT 'Ex: 2026-Q1, 2026-01',
  `observacoes`     TEXT            NULL,
  `registado_em`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_reg_kpi` (`id_kpi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: membros_projeto
-- Equipa de cada projecto (estudantes envolvidos)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `membros_projeto` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`      INT UNSIGNED    NOT NULL,
  `id_usuario`      INT UNSIGNED    NOT NULL,
  `papel`           VARCHAR(100)    NOT NULL DEFAULT 'Membro',
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_membro_projeto` (`id_projeto`, `id_usuario`),
  KEY `fk_memb_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: notificacoes
-- Notificações internas do sistema
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_usuario`      INT UNSIGNED    NOT NULL,
  `titulo`          VARCHAR(200)    NOT NULL,
  `mensagem`        TEXT            NOT NULL,
  `tipo`            ENUM('info','sucesso','aviso','erro') NOT NULL DEFAULT 'info',
  `lida`            TINYINT(1)      NOT NULL DEFAULT 0,
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notif_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- TABELA: tarefas
-- Plano de acção e tarefas atribuídas aos projectos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarefas` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`      INT UNSIGNED    NOT NULL,
  `id_criador`      INT UNSIGNED    NOT NULL COMMENT 'Mentor ou Admin que criou',
  `titulo`          VARCHAR(255)    NOT NULL,
  `descricao`       TEXT            NULL,
  `data_limite`     DATE            NULL,
  `prioridade`      ENUM('baixa','media','alta') NOT NULL DEFAULT 'media',
  `status`          ENUM('pendente','em_progresso','concluida','cancelada') NOT NULL DEFAULT 'pendente',
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_taref_projeto` (`id_projeto`),
  KEY `fk_taref_criador` (`id_criador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: reunioes
-- Agendamento de reuniões entre mentores e equipas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reunioes` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`      INT UNSIGNED    NOT NULL,
  `id_mentor`       INT UNSIGNED    NOT NULL,
  `titulo`          VARCHAR(255)    NOT NULL,
  `data_reuniao`    DATETIME        NOT NULL,
  `link_reuniao`    VARCHAR(255)    NULL,
  `local`           VARCHAR(255)    NULL,
  `status`          ENUM('agendada','realizada','cancelada','adiada') NOT NULL DEFAULT 'agendada',
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_reun_projeto` (`id_projeto`),
  KEY `fk_reun_mentor` (`id_mentor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: avaliacoes_mentor
-- Avaliações de progresso feitas pelos mentores
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avaliacoes_mentor` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`      INT UNSIGNED    NOT NULL,
  `id_mentor`       INT UNSIGNED    NOT NULL,
  `periodo`         VARCHAR(50)     NOT NULL COMMENT 'Ex: Abril 2026',
  `progresso_geral` TINYINT         NOT NULL DEFAULT 0 COMMENT '0-100',
  `feedback`        TEXT            NOT NULL,
  `pontos_fortes`   TEXT            NULL,
  `pontos_melhorar` TEXT            NULL,
  `recomendacoes`   TEXT            NULL,
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_avment_projeto` (`id_projeto`),
  KEY `fk_avment_mentor` (`id_mentor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- VERIFICAÇÃO: Listar todas as tabelas após criação
-- SELECT table_name FROM information_schema.tables
-- WHERE table_schema = 'imcubadora_ispsn';
-- ============================================================

