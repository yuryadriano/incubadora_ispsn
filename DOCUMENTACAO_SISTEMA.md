# 🚀 Documentação do Sistema: Incubadora Académica ISPSN

Esta documentação descreve as funcionalidades, arquitetura e fluxos operacionais da plataforma de gestão da Incubadora Académica ISPSN.

---

## 1. 🔑 Autenticação e Segurança
O sistema utiliza um motor de autenticação robusto baseado em sessões PHP e permissões por papel (Role-Based Access Control - RBAC).

- **Login Inteligente**: Reconhece automaticamente o perfil do utilizador (Aluno, Mentor, Rececionista, Admin ou SuperAdmin) e redireciona para o dashboard específico.
- **Registo Validado**: Permite a criação de contas para estudantes com validação de email institucional.
- **Recuperação de Password**: Sistema de segurança para redefinição de acessos.
- **Níveis de Acesso**: Proteção de rotas através da função `obrigarPerfil()`, garantindo que apenas utilizadores autorizados acedam a áreas sensíveis.

---

## 2. 🏛️ Painel Administrativo (Centro de Comando)
O dashboard administrativo foi desenhado para ser o cérebro da incubadora, focado em métricas e decisões rápidas.

- **KPIs Analíticos**:
  - **Total de Startups**: Contagem global de projetos.
  - **Funil de Inovação**: Visualização gráfica (Doughnut Chart) do estado dos projetos (Submetido, Em Avaliação, Aprovado, Incubado, Fundo de Investimento, Concluído).
  - **Indicadores de Impacto**: Faturação agregada, empregos criados e usuários ativos das startups.
  - **Crescimento Mensal**: Badges comparativos (%) que indicam a evolução da incubadora face ao mês anterior.
- **Feed de Atividade em Tempo Real**: Timeline técnica que regista todas as ações importantes do ecossistema.
- **Gestão de Mentorias**: Atribuição técnica de mentores a projetos aprovados.
- **Ranking de Excelência**: Top 5 das startups com melhor performance baseado em avaliações qualitativas.

---

## 3. 👨‍🏫 Módulo do Mentor (Aceleração)
Focado na gestão de portfólio e no acompanhamento tático do crescimento das startups.

- **Portfólio de Startups**:
  - **Health Check**: Indicadores de saúde (Verde/Amarelo/Vermelho) baseados na execução de tarefas.
  - **Maturidade**: Barra de progresso visual do estágio de cada projeto.
  - **Taxa de Burn-down**: Percentagem técnica de conclusão de objetivos atribuídos.
- **Linha do Tempo de Mentoria (Growth Pulse)**:
  - Registo visual de sessões realizadas com cálculo automático de horas de consultoria.
  - **Radar de Tópicos**: Nuvem de tags extraída via inteligência de dados que identifica os temas mais discutidos.
- **Agenda de Reuniões**:
  - Agendamento com geração automática de links **Jitsi Meet** para reuniões virtuais.
  - Notificações automáticas para os alunos.
- **Gestão Documental**: Troca de relatórios técnicos e revisão de documentos submetidos pelas startups.

---

## 4. 🎓 Módulo do Estudante / Empreendedor
Interface simplificada focada na execução e cumprimento de marcos de crescimento.

- **Gestão do Projeto**: Submissão de ideias e acompanhamento do estado no funil de aprovação.
- **Plano de Ação**: Receção e conclusão de tarefas atribuídas pelos mentores.
- **Arquivo de Documentos**: Submissão de Planos de Negócio, Pitch Decks e relatórios mensais.
- **Reserva de Espaços**: Sistema de marcação de salas de reunião e coworking na infraestrutura física do ISPSN.

---

## 5. 🛎️ Módulo de Receção e Espaços
Ponte entre a gestão digital e a infraestrutura física.

- **Monitor de Ocupação**: Visualização em tempo real das salas ocupadas e reservas pendentes.
- **Gestão de Visitantes**: Registo e controlo de acesso de convidados externos.
- **Aprovação de Reservas**: Fluxo de validação para garantir a utilização correta dos recursos da incubadora.

---

## 6. 🛠️ Diferenciais Técnicos e Inovações
- **Integração Jitsi Meet**: Automação de salas de videoconferência nativa.
- **Análise Gemini AI**: Integração com Inteligência Artificial para análise preliminar de viabilidade de projetos.
- **Dashboards Dinâmicos**: Utilização de Chart.js para visualização de dados complexos.
- **Design Premium**: Interface moderna com glassmorphism, micro-animações e foco em alta densidade de informação.
- **Infraestrutura Escalável**: Base de dados MySQL normalizada, preparada para grandes volumes de projetos e interações.

---
*Documento gerado em: <?= date('d/m/Y') ?> | Versão 2.0 (Modernizada)*
