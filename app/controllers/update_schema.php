<?php
require_once __DIR__ . '/../../config/config.php';

if (!function_exists('adicionarColunaSeNaoExistir')) {
    function adicionarColunaSeNaoExistir($mysqli, $tabela, $coluna, $definicao) {
        $res = $mysqli->query("SHOW COLUMNS FROM `$tabela` LIKE '$coluna'");
        if ($res && $res->num_rows === 0) {
            $mysqli->query("ALTER TABLE `$tabela` ADD COLUMN `$coluna` $definicao");
        }
    }
}

if (!function_exists('adicionarIndiceSeNaoExistir')) {
    function adicionarIndiceSeNaoExistir($mysqli, $tabela, $colunasIndice, $nomeIndice) {
        $res = $mysqli->query("SHOW INDEX FROM `$tabela` WHERE Key_name = '$nomeIndice'");
        if ($res && $res->num_rows === 0) {
            $mysqli->query("ALTER TABLE `$tabela` ADD INDEX `$nomeIndice` ($colunasIndice)");
        }
    }
}

// Adicionar coluna galeria se não existir
adicionarColunaSeNaoExistir($mysqli, 'publicacoes_website', 'galeria', 'TEXT DEFAULT NULL AFTER conteudo');

// Adicionar colunas de evidência na tabela de tarefas
adicionarColunaSeNaoExistir($mysqli, 'tarefas', 'evidencia_path', 'VARCHAR(255) DEFAULT NULL');
adicionarColunaSeNaoExistir($mysqli, 'tarefas', 'evidencia_nota', 'TEXT DEFAULT NULL');
adicionarColunaSeNaoExistir($mysqli, 'tarefas', 'validada_mentor', 'TINYINT(1) DEFAULT 0');

// Adicionar coluna pitch_path nas tabelas de candidaturas e projetos
adicionarColunaSeNaoExistir($mysqli, 'candidaturas', 'pitch_path', 'VARCHAR(255) DEFAULT NULL');
adicionarColunaSeNaoExistir($mysqli, 'projetos', 'pitch_path', 'VARCHAR(255) DEFAULT NULL');

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

// ============================================================
// NOVAS TABELAS v2.0 — Sistema de Metas, Termos e Histórico
// ============================================================

// Novos critérios de avaliação (8 dimensões)
adicionarColunaSeNaoExistir($mysqli, 'avaliacoes', 'nota_sustentabilidade', "TINYINT NOT NULL DEFAULT 0 COMMENT '0-10'");
adicionarColunaSeNaoExistir($mysqli, 'avaliacoes', 'nota_escalabilidade', "TINYINT NOT NULL DEFAULT 0 COMMENT '0-10'");
adicionarColunaSeNaoExistir($mysqli, 'avaliacoes', 'nota_mercado', "TINYINT NOT NULL DEFAULT 0 COMMENT '0-10'");
adicionarColunaSeNaoExistir($mysqli, 'avaliacoes', 'nota_proposta', "TINYINT NOT NULL DEFAULT 0 COMMENT '0-10'");

// Metas padrão do sistema (template global)
$mysqli->query("CREATE TABLE IF NOT EXISTS `metas_padrao` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `fase`            ENUM('ideacao','validacao','mvp','tracao','mercado') NOT NULL,
  `numero`          TINYINT         NOT NULL COMMENT 'Ordem dentro da fase',
  `titulo`          VARCHAR(255)    NOT NULL,
  `descricao`       TEXT            NOT NULL,
  `evidencia_tipo`  VARCHAR(100)    NOT NULL DEFAULT 'ficheiro',
  `evidencia_desc`  VARCHAR(255)    NOT NULL,
  `peso_percentual` DECIMAL(5,2)    NOT NULL,
  `prazo_dias`      INT             NOT NULL DEFAULT 7,
  `activo`          TINYINT(1)      NOT NULL DEFAULT 1,
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fase_numero` (`fase`, `numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Instância de metas por projecto (activadas pelo SuperAdmin)
$mysqli->query("CREATE TABLE IF NOT EXISTS `metas_projeto` (
  `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`        INT UNSIGNED    NOT NULL,
  `id_meta_padrao`    INT UNSIGNED    NOT NULL,
  `estado`            ENUM('inactiva','activa','em_avaliacao','concluida','reprovada') NOT NULL DEFAULT 'inactiva',
  `activada_por`      INT UNSIGNED    NULL,
  `activada_em`       DATETIME        NULL,
  `data_limite`       DATE            NULL,
  `evidencia_path`    VARCHAR(255)    NULL,
  `evidencia_link`    VARCHAR(500)    NULL,
  `evidencia_texto`   TEXT            NULL,
  `evidencia_em`      DATETIME        NULL,
  `validada_por`      INT UNSIGNED    NULL,
  `validada_em`       DATETIME        NULL,
  `feedback_mentor`   TEXT            NULL,
  `nota_mentor`       TINYINT         NULL,
  `concluida_em`      DATETIME        NULL,
  `pontos_ganhos`     INT             NOT NULL DEFAULT 0,
  `criado_em`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projeto_meta` (`id_projeto`, `id_meta_padrao`),
  KEY `fk_mp_projeto` (`id_projeto`),
  KEY `fk_mp_meta` (`id_meta_padrao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Termos de incubação com assinatura digital
$mysqli->query("CREATE TABLE IF NOT EXISTS `termos_incubacao` (
  `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`        INT UNSIGNED    NOT NULL,
  `id_avaliacao`      INT UNSIGNED    NOT NULL,
  `id_mentor`         INT UNSIGNED    NULL,
  `codigo_termo`      VARCHAR(30)     NOT NULL UNIQUE,
  `dados_json`        JSON            NOT NULL,
  `metas_iniciais`    JSON            NULL,
  `path_pdf`          VARCHAR(255)    NULL,
  `estado`            ENUM('gerado','pendente_assinatura','assinado','revogado') NOT NULL DEFAULT 'gerado',
  `assinado_por`      INT UNSIGNED    NULL,
  `assinatura_hash`   VARCHAR(128)    NULL,
  `assinado_em`       DATETIME        NULL,
  `motivo_revogacao`  TEXT            NULL,
  `criado_em`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_termo_projeto` (`id_projeto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Histórico de transições de estado
$mysqli->query("CREATE TABLE IF NOT EXISTS `historico_estados` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`      INT UNSIGNED    NOT NULL,
  `estado_anterior` VARCHAR(50)     NOT NULL,
  `estado_novo`     VARCHAR(50)     NOT NULL,
  `id_usuario`      INT UNSIGNED    NOT NULL,
  `motivo`          TEXT            NULL,
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_hist_projeto` (`id_projeto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Mensagens de chat de mentoria direta
$mysqli->query("CREATE TABLE IF NOT EXISTS `mensagens` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_projeto`      INT UNSIGNED    NOT NULL,
  `id_usuario`      INT UNSIGNED    NOT NULL,
  `mensagem`        TEXT            NOT NULL,
  `lida`            TINYINT(1)      NOT NULL DEFAULT 0,
  `criado_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_msg_projeto` (`id_projeto`),
  KEY `fk_msg_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ============================================================
// SEED: Metas Padrão (inserir apenas se a tabela está vazia)
// ============================================================
$check = $mysqli->query("SELECT COUNT(*) n FROM metas_padrao");
$count = $check ? (int)$check->fetch_assoc()['n'] : 0;

if ($count === 0) {
    $metas = [
        // === FASE: IDEAÇÃO ===
        ['ideacao', 1, 'Business Model Canvas', 'Preencher completamente o Business Model Canvas com todos os 9 blocos: segmentos de clientes, proposta de valor, canais, relacionamento, fontes de receita, recursos-chave, actividades-chave, parcerias e estrutura de custos.', 'ficheiro', 'PDF ou imagem do Canvas preenchido', 15.00, 7],
        ['ideacao', 2, 'Proposta de Valor', 'Definir claramente qual problema resolve, para quem, e porque a sua solução é melhor que as alternativas existentes.', 'ficheiro', 'Documento com proposta de valor detalhada', 10.00, 5],
        ['ideacao', 3, 'Identificação de Clientes', 'Listar no mínimo 10 potenciais clientes reais com nome, contacto e perfil.', 'ficheiro', 'Lista com contactos e perfis dos clientes', 10.00, 7],
        ['ideacao', 4, 'Entrevistas de Validação', 'Realizar pelo menos 5 entrevistas presenciais ou virtuais com potenciais clientes para validar o problema e a solução proposta.', 'ficheiro', 'Relatório com perguntas e respostas das entrevistas', 15.00, 10],
        ['ideacao', 5, 'Análise de Concorrência', 'Identificar e analisar no mínimo 3 concorrentes directos ou indirectos, comparando funcionalidades, preços e pontos fortes/fracos.', 'ficheiro', 'Tabela comparativa documentada', 10.00, 7],
        ['ideacao', 6, 'Modelo de Receita', 'Definir como o projecto vai gerar dinheiro: modelo de monetização, preços estimados, projecção de receita para 6 meses.', 'ficheiro', 'Documento com modelo e projecção de receita', 15.00, 7],
        ['ideacao', 7, 'Pitch de 3 Minutos', 'Preparar e apresentar um pitch verbal de 3 minutos ao mentor, demonstrando capacidade de comunicar a ideia de forma clara e convincente.', 'texto', 'Vídeo do pitch ou confirmação escrita do mentor', 10.00, 5],
        ['ideacao', 8, 'Plano de Acção 30 Dias', 'Elaborar um cronograma detalhado com as actividades dos próximos 30 dias, incluindo responsáveis e marcos de entrega.', 'ficheiro', 'Cronograma/roadmap em PDF ou Excel', 15.00, 5],

        // === FASE: VALIDAÇÃO ===
        ['validacao', 1, 'Protótipo/MVP Funcional', 'Desenvolver um protótipo funcional mínimo que demonstre a proposta de valor principal do projecto, mesmo que básico.', 'link', 'Link do protótipo, screenshots ou vídeo demo', 20.00, 14],
        ['validacao', 2, 'Teste com Utilizadores', 'Realizar testes com pelo menos 10 utilizadores reais e documentar o feedback recebido em detalhe.', 'ficheiro', 'Relatório de feedback dos testes com utilizadores', 15.00, 10],
        ['validacao', 3, 'Métricas Iniciais', 'Definir as métricas-chave do negócio (KPIs) e apresentar as primeiras medições reais.', 'ficheiro', 'Dashboard ou relatório de KPIs', 10.00, 7],
        ['validacao', 4, 'Presença Digital', 'Criar uma landing page ou presença digital que apresente o projecto ao público.', 'link', 'URL da página criada', 10.00, 7],
        ['validacao', 5, 'Iteração com Feedback', 'Implementar melhorias no protótipo com base no feedback dos utilizadores e documentar as alterações realizadas.', 'ficheiro', 'Changelog/relatório de alterações implementadas', 15.00, 10],
        ['validacao', 6, 'Plano Financeiro 6 Meses', 'Elaborar um plano financeiro preliminar com projecções de receitas, custos e fluxo de caixa para 6 meses.', 'ficheiro', 'Spreadsheet com projecções financeiras', 15.00, 10],
        ['validacao', 7, 'Parceiro Estratégico', 'Identificar e contactar pelo menos 1 potencial parceiro estratégico para o negócio.', 'ficheiro', 'Contacto estabelecido ou carta de intenção', 10.00, 14],
        ['validacao', 8, 'Pitch Deck Profissional', 'Criar um pitch deck profissional com 10 slides cobrindo: problema, solução, mercado, modelo de negócio, equipa, tracção e necessidades.', 'ficheiro', 'PDF do pitch deck completo', 5.00, 7],

        // === FASE: MVP / TRACÇÃO ===
        ['mvp', 1, '50+ Utilizadores Activos', 'Atingir um mínimo de 50 utilizadores activos no MVP, demonstrando capacidade de aquisição de clientes.', 'ficheiro', 'Screenshots de analytics/métricas de utilizadores', 20.00, 21],
        ['mvp', 2, 'Primeira Receita ou LOI', 'Gerar a primeira receita real ou obter uma Letter of Intent de um cliente/parceiro.', 'ficheiro', 'Comprovativo de pagamento ou LOI assinada', 20.00, 21],
        ['mvp', 3, 'Métricas de Crescimento', 'Documentar métricas de crescimento semanais/mensais com gráficos de evolução.', 'ficheiro', 'Relatório com gráficos de evolução', 15.00, 14],
        ['mvp', 4, 'Equipa Expandida', 'Expandir a equipa para no mínimo 3 membros com papéis complementares definidos.', 'texto', 'Perfis dos membros registados no sistema', 10.00, 14],
        ['mvp', 5, 'Estratégia de Marketing', 'Definir e iniciar a execução de uma estratégia de marketing com pelo menos 2 canais de aquisição.', 'ficheiro', 'Plano de marketing + evidências das primeiras acções', 15.00, 14],
        ['mvp', 6, 'Apresentação a Investidor', 'Realizar pelo menos uma apresentação formal a um potencial investidor ou parceiro estratégico.', 'ficheiro', 'Feedback recebido ou acta da reunião', 20.00, 21],

        // === FASE: MERCADO ===
        ['mercado', 1, 'Plano de Negócios Final', 'Completar e finalizar o plano de negócios completo com todas as secções.', 'ficheiro', 'PDF do plano de negócios completo', 20.00, 14],
        ['mercado', 2, 'Formalização Legal', 'Obter o NIF empresarial, Alvará ou outro documento de formalização legal da empresa.', 'ficheiro', 'Documento legal digitalizado (NIF/Alvará)', 20.00, 21],
        ['mercado', 3, 'Contrato ou Parceria Formal', 'Assinar pelo menos um contrato comercial ou acordo de parceria formal.', 'ficheiro', 'Contrato ou acordo assinado digitalizado', 20.00, 21],
        ['mercado', 4, 'Relatório Final de Impacto', 'Elaborar um relatório final documentando o impacto do projecto: métricas atingidas, lições aprendidas e próximos passos.', 'ficheiro', 'Relatório completo de impacto e resultados', 20.00, 14],
        ['mercado', 5, 'Apresentação de Graduação', 'Realizar a apresentação final de graduação perante a banca da incubadora.', 'texto', 'Confirmação da apresentação realizada', 20.00, 7],
    ];

    $stmtMeta = $mysqli->prepare("INSERT INTO metas_padrao (fase, numero, titulo, descricao, evidencia_tipo, evidencia_desc, peso_percentual, prazo_dias) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($metas as $m) {
        $stmtMeta->bind_param('sissssdi', $m[0], $m[1], $m[2], $m[3], $m[4], $m[5], $m[6], $m[7]);
        $stmtMeta->execute();
    }
    $stmtMeta->close();
    echo "Metas padrão inseridas! ";
}

// Criação de índices para melhoria de performance
adicionarIndiceSeNaoExistir($mysqli, 'tarefas', 'id_projeto, status, data_limite', 'idx_tarefas_projeto_status_prazo');
adicionarIndiceSeNaoExistir($mysqli, 'tarefas', 'id_projeto', 'idx_tarefas_projeto');
adicionarIndiceSeNaoExistir($mysqli, 'reunioes', 'id_mentor', 'idx_reunioes_mentor');
adicionarIndiceSeNaoExistir($mysqli, 'reunioes', 'id_projeto', 'idx_reunioes_projeto');
adicionarIndiceSeNaoExistir($mysqli, 'mensagens', 'id_projeto', 'idx_mensagens_projeto');

// Adicionar tipo_candidato na tabela candidaturas
adicionarColunaSeNaoExistir($mysqli, 'candidaturas', 'tipo_candidato', "ENUM('estudante', 'pre_licenciado') DEFAULT 'estudante'");

// Atualizar tipo_utilizador na tabela usuarios para aceitar pre_licenciado
$mysqli->query("ALTER TABLE `usuarios` MODIFY COLUMN `tipo_utilizador` ENUM('estudante', 'docente', 'outro', 'pre_licenciado', 'mentor', 'funcionario') NOT NULL DEFAULT 'estudante'");

echo "Schema updated v2.0!";


