-- ============================================================
-- SCHEMA COMPLETO GERADO — Incubadora Académica ISPSN
-- ============================================================

USE `imcubadora_ispsn`;

-- ------------------------------------------------------------
-- TABELA: usuarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_estudante` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `senha_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `perfil` enum('superadmin','admin','funcionario','mentor','utilizador') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'utilizador',
  `tipo_utilizador` enum('estudante','docente','outro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'estudante',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: projetos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('startup_tecnologica','negocio_tradicional','impacto_social','outro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'startup_tecnologica',
  `descricao` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `problema` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solucao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area_tematica` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'tecnologia',
  `estado` enum('submetido','em_avaliacao','aprovado','rejeitado','incubado','fundo_investimento','concluido') COLLATE utf8mb4_unicode_ci DEFAULT 'submetido',
  `fase` enum('ideacao','validacao','mvp','tracao','mercado') COLLATE utf8mb4_unicode_ci DEFAULT 'ideacao',
  `motivo_rejeicao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_responsavel` int(11) NOT NULL,
  `criado_por` int(11) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `feedback_ia` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destaque_publico` tinyint(1) DEFAULT 0,
  `pontos` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `id_responsavel` (`id_responsavel`),
  KEY `criado_por` (`criado_por`),
  CONSTRAINT `projetos_ibfk_1` FOREIGN KEY (`id_responsavel`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `projetos_ibfk_2` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: config_website
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `config_website` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grupo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: publicacoes_website
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `publicacoes_website` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resumo` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conteudo` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `galeria` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagem` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('rascunho','publicado') COLLATE utf8mb4_unicode_ci DEFAULT 'rascunho',
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  `visualizacoes` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: galeria_website
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `galeria_website` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagem` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Geral',
  `ordem` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: processos_candidatura
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `processos_candidatura` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('em_preparacao','aberto','fechado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'em_preparacao',
  `data_abertura` datetime DEFAULT NULL,
  `data_fecho` datetime DEFAULT NULL,
  `vagas` int(10) unsigned DEFAULT 20,
  `criado_por` int(10) unsigned NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: candidaturas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `candidaturas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_processo` int(10) unsigned NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_estudante` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `curso` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ano_estudo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo_ideia` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao_ideia` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `problema` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solucao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area_tematica` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'tecnologia',
  `estado` enum('pendente','em_analise','selecionado','rejeitado','convite_enviado','registado') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `observacoes_admin` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_convite` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `convite_enviado_em` datetime DEFAULT NULL,
  `avaliado_em` datetime DEFAULT NULL,
  `avaliado_por` int(10) unsigned DEFAULT NULL,
  `ip_submissao` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: convites
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `convites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `perfil` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'utilizador',
  `id_projeto` int(10) unsigned DEFAULT NULL,
  `criado_por` int(10) unsigned NOT NULL,
  `aceite` tinyint(1) DEFAULT 0,
  `criado_em` datetime DEFAULT current_timestamp(),
  `data_expiracao` datetime DEFAULT NULL,
  `id_candidatura` int(10) unsigned DEFAULT NULL,
  `telefone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_estudante` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usos_maximos` tinyint(4) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: mensagens
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mensagens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `lida` tinyint(1) DEFAULT 0,
  `criado_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: ficheiros_projeto
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ficheiros_projeto` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Documento',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_usuario_up` int(10) unsigned NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_fich_projeto` (`id_projeto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: acompanhamentos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `acompanhamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_projeto` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `tipo_registo` enum('comentario','correcao','reuniao','entrega') COLLATE utf8mb4_unicode_ci DEFAULT 'comentario',
  `descricao` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `anexo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_projeto` (`id_projeto`),
  KEY `id_utilizador` (`id_utilizador`),
  CONSTRAINT `acompanhamentos_ibfk_1` FOREIGN KEY (`id_projeto`) REFERENCES `projetos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `acompanhamentos_ibfk_2` FOREIGN KEY (`id_utilizador`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: artigos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `artigos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resumo` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ficheiro_pdf` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area_cientifica` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_autor` int(11) NOT NULL,
  `status` enum('rascunho','submetido','aprovado','rejeitado') COLLATE utf8mb4_unicode_ci DEFAULT 'submetido',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_autor` (`id_autor`),
  CONSTRAINT `artigos_ibfk_1` FOREIGN KEY (`id_autor`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: avaliacoes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avaliacoes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `id_avaliador` int(10) unsigned NOT NULL,
  `nota_inovacao` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0-10',
  `nota_viabilidade` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0-10',
  `nota_impacto` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0-10',
  `nota_equipa` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0-10',
  `pontuacao_total` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Calculado: m??dia',
  `observacoes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decisao` enum('pendente','aprovado','rejeitado','em_revisao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `avaliado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `nota_sustentabilidade` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0-10',
  `nota_escalabilidade` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0-10',
  `nota_mercado` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0-10',
  `nota_proposta` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0-10',
  PRIMARY KEY (`id`),
  KEY `fk_aval_projeto` (`id_projeto`),
  KEY `fk_aval_avaliador` (`id_avaliador`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: avaliacoes_mentor
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avaliacoes_mentor` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `id_mentor` int(10) unsigned NOT NULL,
  `periodo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex: Abril 2026',
  `progresso_geral` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0-100',
  `feedback` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `pontos_fortes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pontos_melhorar` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recomendacoes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_avmentor_projeto` (`id_projeto`),
  KEY `fk_avmentor_mentor` (`id_mentor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: comentarios_projetos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comentarios_projetos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_projeto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `comentario` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fase` enum('em_analise','em_andamento','concluido') COLLATE utf8mb4_unicode_ci NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_projeto` (`id_projeto`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `comentarios_projetos_ibfk_1` FOREIGN KEY (`id_projeto`) REFERENCES `projetos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comentarios_projetos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: despesas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `despesas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_financiamento` int(10) unsigned NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `data_despesa` date NOT NULL,
  `categoria` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `justificativo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path para ficheiro PDF/imagem',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_desp_financiamento` (`id_financiamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: emprestimos_equipamento
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `emprestimos_equipamento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_equipamento` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `data_saida` datetime DEFAULT current_timestamp(),
  `data_devolucao_prevista` datetime DEFAULT NULL,
  `data_devolucao_real` datetime DEFAULT NULL,
  `status` enum('ativo','devolvido','atrasado') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `observacoes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_equipamento` (`id_equipamento`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `emprestimos_equipamento_ibfk_1` FOREIGN KEY (`id_equipamento`) REFERENCES `equipamentos` (`id`),
  CONSTRAINT `emprestimos_equipamento_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: equipamentos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `equipamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_patrimonio` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('disponivel','em_uso','manutencao','danificado') COLLATE utf8mb4_unicode_ci DEFAULT 'disponivel',
  `criado_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_patrimonio` (`codigo_patrimonio`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: espacos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `espacos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('mesa','sala_reuniao','laboratorio','auditorio') COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacidade` int(11) DEFAULT 1,
  `status` enum('disponivel','manutencao','indisponivel') COLLATE utf8mb4_unicode_ci DEFAULT 'disponivel',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: financiamentos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `financiamentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `fonte` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex: ISPSN, BPC, Concurso X',
  `montante_aprovado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `montante_executado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `data_aprovacao` date DEFAULT NULL,
  `data_limite` date DEFAULT NULL,
  `estado` enum('pendente','activo','concluido','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_fin_projeto` (`id_projeto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: historico_estados
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `historico_estados` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `estado_anterior` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado_novo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `motivo` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_hist_projeto` (`id_projeto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: kpis
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kpis` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex: Utilizadores activos, Receita mensal',
  `unidade` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unidades' COMMENT 'Ex: Kz, %, pessoas',
  `meta` decimal(14,2) NOT NULL DEFAULT 0.00,
  `periodicidade` enum('mensal','trimestral','semestral','anual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trimestral',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_kpi_projeto` (`id_projeto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: logs_acesso
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs_acesso` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_login` datetime DEFAULT current_timestamp(),
  `sucesso` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `logs_acesso_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: membros_projeto
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `membros_projeto` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `papel` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Membro',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_membro_projeto` (`id_projeto`,`id_usuario`),
  KEY `fk_memb_usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: mentores
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mentores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `especialidade` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linkedin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disponivel` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_mentor_usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: mentorias
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mentorias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `id_mentor` int(10) unsigned NOT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `estado` enum('activa','concluida','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activa',
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ment_projeto` (`id_projeto`),
  KEY `fk_ment_mentor` (`id_mentor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: metas_padrao
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `metas_padrao` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fase` enum('ideacao','validacao','mvp','tracao','mercado') COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero` tinyint(4) NOT NULL COMMENT 'Ordem dentro da fase',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidencia_tipo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ficheiro',
  `evidencia_desc` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `peso_percentual` decimal(5,2) NOT NULL,
  `prazo_dias` int(11) NOT NULL DEFAULT 7,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fase_numero` (`fase`,`numero`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: metas_projeto
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `metas_projeto` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `id_meta_padrao` int(10) unsigned NOT NULL,
  `estado` enum('inactiva','activa','em_avaliacao','concluida','reprovada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inactiva',
  `activada_por` int(10) unsigned DEFAULT NULL,
  `activada_em` datetime DEFAULT NULL,
  `data_limite` date DEFAULT NULL,
  `evidencia_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evidencia_link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evidencia_texto` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evidencia_em` datetime DEFAULT NULL,
  `validada_por` int(10) unsigned DEFAULT NULL,
  `validada_em` datetime DEFAULT NULL,
  `feedback_mentor` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nota_mentor` tinyint(4) DEFAULT NULL,
  `concluida_em` datetime DEFAULT NULL,
  `pontos_ganhos` int(11) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projeto_meta` (`id_projeto`,`id_meta_padrao`),
  KEY `fk_mp_projeto` (`id_projeto`),
  KEY `fk_mp_meta` (`id_meta_padrao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: notificacoes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('info','sucesso','aviso','erro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `lida` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notif_usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: registos_kpi
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registos_kpi` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_kpi` int(10) unsigned NOT NULL,
  `valor` decimal(14,2) NOT NULL,
  `periodo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex: 2026-Q1, 2026-01',
  `observacoes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_reg_kpi` (`id_kpi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: relatorios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `relatorios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `id_autor` int(10) unsigned NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ficheiro` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` enum('mensal','bimestral','trimestral','feedback','outro') COLLATE utf8mb4_unicode_ci DEFAULT 'mensal',
  `destino` enum('admin','startup','todos') COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: reservas_espaco
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reservas_espaco` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_espaco` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `data_reserva` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fim` time NOT NULL,
  `objetivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pendente','confirmada','cancelada','concluida') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `check_in_at` datetime DEFAULT NULL,
  `criado_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: reunioes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reunioes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `id_mentor` int(10) unsigned NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_reuniao` datetime NOT NULL,
  `link_reuniao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `local` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('agendada','realizada','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'agendada',
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: sessoes_mentoria
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessoes_mentoria` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_mentoria` int(10) unsigned NOT NULL,
  `data_sessao` date NOT NULL,
  `duracao_min` int(11) NOT NULL DEFAULT 60,
  `topicos` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proximos_passos` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avaliacao_mentor` tinyint(4) DEFAULT NULL COMMENT '1-5',
  `avaliacao_equipa` tinyint(4) DEFAULT NULL COMMENT '1-5',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sess_mentoria` (`id_mentoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: tarefas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarefas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `id_criador` int(10) unsigned NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_limite` date DEFAULT NULL,
  `prioridade` enum('baixa','media','alta') COLLATE utf8mb4_unicode_ci DEFAULT 'media',
  `status` enum('pendente','concluida') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `criado_em` datetime DEFAULT current_timestamp(),
  `evidencia_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evidencia_nota` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validada_mentor` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: termos_incubacao
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `termos_incubacao` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_projeto` int(10) unsigned NOT NULL,
  `id_avaliacao` int(10) unsigned NOT NULL,
  `id_mentor` int(10) unsigned DEFAULT NULL,
  `codigo_termo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dados_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`dados_json`)),
  `metas_iniciais` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metas_iniciais`)),
  `path_pdf` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('gerado','pendente_assinatura','assinado','revogado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gerado',
  `assinado_por` int(10) unsigned DEFAULT NULL,
  `assinatura_hash` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assinado_em` datetime DEFAULT NULL,
  `motivo_revogacao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_termo` (`codigo_termo`),
  KEY `fk_termo_projeto` (`id_projeto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: visitantes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `visitantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento_identidade` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empresa_motivo` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_entrada` datetime DEFAULT current_timestamp(),
  `data_saida` datetime DEFAULT NULL,
  `id_usuario_visitado` int(11) DEFAULT NULL,
  `status` enum('presente','saiu') COLLATE utf8mb4_unicode_ci DEFAULT 'presente',
  PRIMARY KEY (`id`),
  KEY `id_usuario_visitado` (`id_usuario_visitado`),
  CONSTRAINT `visitantes_ibfk_1` FOREIGN KEY (`id_usuario_visitado`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

