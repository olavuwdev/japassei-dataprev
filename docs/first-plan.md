A documentação atual do CodeIgniter 4 mantém as migrations em `app/Database/Migrations`, utiliza nomes com timestamp e execução por `php spark migrate`. Os seeders podem ser executados com `php spark db:seed`. ([CodeIgniter][1])

Os links incluídos no prompt apontam para páginas oficiais do Cebraspe e do Instituto Quadrix referentes aos concursos da Dataprev de 2023, 2014, 2012, 2011 e 2010. ([Cebraspe][2])

## Prompt mestre para o agente

```text
Você atuará como arquiteto de software, desenvolvedor PHP sênior, especialista em CodeIgniter 4, banco de dados, UX educacional e sistemas de acompanhamento de estudos.

Sua missão é implementar, dentro de um projeto CodeIgniter 4 já existente, um módulo completo para acompanhamento dos estudos do concurso DATAPREV 2026 — Perfil 3: Desenvolvimento de Software.

==================================================
1. CONTEXTO OBRIGATÓRIO
==================================================

O projeto JÁ ESTÁ CRIADO e utiliza CODEIGNITER 4.

Não crie um novo projeto.
Não reinstale o CodeIgniter.
Não substitua a estrutura atual.
Não altere padrões existentes sem necessidade.
Não sobrescreva arquivos sem analisar o conteúdo atual.
Não presuma a versão do framework: consulte composer.json e composer.lock.
Não adicione um framework frontend novo caso o projeto já possua um padrão visual.

Antes de implementar:

1. Analise a estrutura do projeto.
2. Identifique:
   - versão do CodeIgniter 4;
   - versão do PHP;
   - banco de dados utilizado;
   - sistema de autenticação existente;
   - padrão de rotas;
   - layout principal;
   - biblioteca CSS;
   - biblioteca JavaScript;
   - convenções de Models, Controllers, Services e Views;
   - tabela e chave primária de usuários.
3. Reutilize obrigatoriamente os padrões encontrados.
4. Informe resumidamente os arquivos que serão criados ou modificados.
5. Depois disso, implemente os arquivos reais. Não entregue somente pseudocódigo.

Não faça alterações destrutivas sem necessidade.
Não remova funcionalidades existentes.
Não invente que comandos foram executados quando não puder executá-los.

==================================================
2. OBJETIVO DO MÓDULO
==================================================

Criar uma plataforma didática e motivacional para acompanhar um cronograma de estudos de 24 semanas para o concurso DATAPREV 2026.

O usuário estudará:

- 1 hora por dia;
- de segunda a sexta-feira;
- total de 5 horas semanais.

O módulo deverá possuir:

- dashboard diário;
- cronograma semanal;
- checklist;
- Kanban;
- timer de estudo;
- controle de sessões;
- ofensiva de estudos com ícone de fogo;
- revisões programadas;
- registro de questões;
- provas antigas da Dataprev;
- estatísticas;
- metas;
- desempenho por disciplina;
- histórico;
- gamificação leve;
- design responsivo;
- experiência parecida com aplicativo em dispositivos móveis.

A referência da ofensiva pode lembrar a experiência motivacional do Duolingo, mas não copie logotipos, ilustrações, textos, código, identidade visual ou elementos proprietários do Duolingo.

Crie uma identidade própria.

==================================================
3. ESTRUTURA DE NAVEGAÇÃO
==================================================

Criar um grupo de rotas protegido pela autenticação existente.

Sugestão de rotas:

/estudos
/estudos/hoje
/estudos/cronograma
/estudos/kanban
/estudos/revisoes
/estudos/questoes
/estudos/provas
/estudos/desempenho
/estudos/historico
/estudos/configuracoes

Utilize nomes de rotas e filtros compatíveis com o padrão atual do projeto.

Menu principal do módulo:

1. Visão geral
2. Hoje
3. Cronograma
4. Kanban
5. Revisões
6. Questões
7. Provas antigas
8. Desempenho
9. Histórico
10. Configurações

==================================================
4. DASHBOARD
==================================================

O dashboard deve apresentar:

- saudação;
- data atual;
- tarefa principal do dia;
- disciplina do dia;
- conteúdo que será estudado;
- tempo previsto;
- botão “Iniciar estudo”;
- progresso diário;
- progresso semanal;
- minutos estudados;
- questões respondidas;
- percentual de acertos;
- revisões pendentes;
- sequência atual da ofensiva;
- melhor ofensiva;
- situação da meta semanal;
- próximo conteúdo;
- gráfico de desempenho por disciplina;
- gráfico de evolução semanal;
- resumo das últimas sessões.

Card principal da ofensiva:

🔥 8 dias de ofensiva

Também exibir:

- recorde pessoal;
- última atividade válida;
- dias cumpridos na semana;
- representação visual de segunda a sexta;
- mensagem motivacional curta;
- estado de risco quando o usuário ainda não estudou no dia.

Exemplos de mensagens:

- “Sua ofensiva está segura.”
- “Falta uma sessão para manter sua sequência.”
- “Você concluiu sua meta de hoje.”
- “Novo recorde pessoal!”
- “Continue. A consistência está construindo sua aprovação.”

Não utilizar mensagens exageradas ou promessas de aprovação.

==================================================
5. REGRA DA OFENSIVA
==================================================

A ofensiva deverá considerar apenas os dias configurados como dias de estudo.

Configuração inicial:

- segunda;
- terça;
- quarta;
- quinta;
- sexta.

Sábado e domingo:

- não aumentam a ofensiva;
- não quebram a ofensiva;
- podem receber sessões extras.

Um dia será considerado concluído quando ocorrer pelo menos uma destas condições:

1. O usuário acumular 60 minutos de estudo no dia; ou
2. Concluir a tarefa principal do dia, registrar pelo menos 45 minutos e concluir todos os itens obrigatórios do checklist.

Quando um dia útil obrigatório não for concluído:

- quebrar a ofensiva;
- reiniciar a sequência no próximo dia válido concluído.

Armazenar:

- ofensiva atual;
- maior ofensiva;
- última data válida;
- quantidade total de dias cumpridos;
- data do recorde;
- histórico das alterações.

Toda regra de ofensiva deve ficar em um Service, e não diretamente no Controller.

Criar testes automatizados contemplando:

- primeiro dia estudado;
- dias consecutivos;
- final de semana;
- ausência na sexta e retorno na segunda;
- quebra de sequência;
- múltiplas sessões no mesmo dia;
- edição e exclusão de sessão;
- mudança de fuso ou horário;
- atualização do recorde.

==================================================
6. ROTINA DIÁRIA E TIMER
==================================================

Cada tarefa padrão de estudo deverá possuir o seguinte checklist:

[ ] Revisar conteúdo anterior — 10 minutos
[ ] Estudar teoria — 25 minutos
[ ] Resolver questões — 20 minutos
[ ] Registrar erros e observações — 5 minutos

Total: 60 minutos.

Criar um timer com:

- iniciar;
- pausar;
- continuar;
- concluir;
- cancelar;
- mostrar tempo decorrido;
- mostrar etapa atual;
- avançar etapa;
- salvar progresso;
- impedir perda acidental da sessão;
- confirmação antes de abandonar uma sessão ativa.

O timer deve funcionar mesmo que a página seja atualizada, utilizando persistência segura no backend e, complementarmente, armazenamento local quando necessário.

Ao finalizar uma sessão:

- registrar duração;
- atualizar tarefa;
- atualizar progresso diário;
- atualizar progresso semanal;
- recalcular ofensiva;
- gerar revisões;
- atualizar estatísticas;
- apresentar resumo da sessão.

==================================================
7. SISTEMA DE REVISÕES
==================================================

Ao concluir pela primeira vez um conteúdo teórico, gerar automaticamente revisões em:

- 1 dia;
- 7 dias;
- 30 dias.

Permitir configurar esses intervalos futuramente.

Cada revisão deve possuir:

- conteúdo;
- disciplina;
- data prevista;
- situação;
- nível de dificuldade;
- quantidade de questões;
- acertos;
- erros;
- observação;
- data de conclusão.

Situações:

- pendente;
- disponível;
- atrasada;
- concluída;
- ignorada;
- reagendada.

A página de revisões deve separar:

- Revisões de hoje
- Revisões atrasadas
- Próximas revisões
- Revisões concluídas

Ao concluir uma revisão, solicitar:

- quantidade de questões;
- quantidade de acertos;
- dificuldade percebida;
- observação opcional.

==================================================
8. KANBAN
==================================================

Criar um Kanban responsivo com drag and drop.

Colunas iniciais:

1. Backlog
2. Esta semana
3. Hoje
4. Em estudo
5. Revisão
6. Concluído

Cada card deve mostrar:

- título;
- disciplina;
- semana;
- data programada;
- tipo da atividade;
- duração prevista;
- progresso do checklist;
- prioridade;
- revisões pendentes;
- indicador de atraso.

Permitir:

- mover cards;
- reordenar cards;
- abrir detalhes;
- editar;
- concluir;
- reagendar;
- adicionar observação;
- visualizar checklist;
- iniciar timer;
- filtrar por disciplina;
- filtrar por semana;
- filtrar por tipo;
- filtrar por prioridade;
- filtrar por situação.

A movimentação deve ser persistida via requisição assíncrona.

Utilizar transação no banco para:

- alterar a coluna;
- atualizar posição;
- registrar histórico.

Não aceitar posição duplicada dentro da mesma coluna.

Caso o projeto não possua biblioteca para drag and drop, utilizar uma biblioteca leve e consolidada, como SortableJS, sem introduzir um framework frontend completo.

==================================================
9. CHECKLIST
==================================================

Cada tarefa pode possuir vários itens de checklist.

Permitir:

- marcar e desmarcar;
- criar item;
- editar item;
- excluir item;
- reordenar;
- mostrar percentual concluído;
- identificar itens obrigatórios;
- registrar horário de conclusão.

Ao concluir todos os itens obrigatórios:

- sugerir conclusão da tarefa;
- não concluir automaticamente sem confirmação, exceto quando isso estiver habilitado nas configurações.

==================================================
10. QUESTÕES
==================================================

Criar uma seção chamada “Questões”.

Ela deve possuir duas áreas:

A. Registro de desempenho

Permitir registrar:

- data;
- disciplina;
- conteúdo;
- fonte;
- prova;
- ano;
- quantidade total;
- acertos;
- erros;
- questões em branco;
- tempo utilizado;
- observações;
- assuntos em que houve erro.

Calcular automaticamente:

- percentual de acertos;
- percentual de erros;
- evolução;
- média por disciplina;
- média por conteúdo;
- média por semana.

Validar:

acertos + erros + em branco não pode ser maior que a quantidade total.

B. Materiais e provas antigas

Exibir cards contendo:

- ano;
- organizadora;
- descrição;
- tipo do material;
- botão para acessar;
- indicação de fonte oficial;
- opção de marcar como realizado;
- campo de resultado;
- data em que foi realizado.

Não copiar integralmente questões de terceiros para o banco.
Armazenar principalmente links para páginas ou documentos oficiais.
A aplicação deve abrir links externos em nova aba com os atributos de segurança adequados.

==================================================
11. PROVAS ANTIGAS — SEED INICIAL
==================================================

Criar seed inicial para os seguintes materiais:

1. DATAPREV 2023 — Cebraspe
Título: Página oficial do concurso Dataprev 2023
Tipo: Página oficial
URL:
https://www.cebraspe.org.br/concursos/dataprev_23
Oficial: Sim

2. DATAPREV 2014 — Quadrix
Título: Página oficial do concurso Dataprev 2014
Tipo: Página oficial
URL:
https://quadrix.org.br/informacoes/2081/
Oficial: Sim

3. DATAPREV 2014 — Quadrix
Título: Acervo oficial do concurso Dataprev 2014
Tipo: Provas e gabaritos
URL:
https://www2.quadrix.org.br/concursoDATAPREV2014.aspx
Oficial: Sim

4. DATAPREV 2014 — Quadrix
Título: Gabarito preliminar Dataprev 2014
Tipo: Gabarito
URL:
https://www.quadrix.org.br/resources/1/concursos/2014/DATAPREV2014/dataprev14_gabarito_preliminar.pdf
Oficial: Sim

5. DATAPREV 2012 — Quadrix
Título: Página oficial do concurso Dataprev 2012
Tipo: Página oficial
URL:
https://quadrix.org.br/informacoes/2152/
Oficial: Sim

6. DATAPREV 2011 — Quadrix
Título: Página oficial do concurso Dataprev 2011
Tipo: Página oficial
URL:
https://quadrix.org.br/informacoes/2178/
Oficial: Sim

7. DATAPREV 2011 — Quadrix
Título: Acervo oficial do concurso Dataprev 2011
Tipo: Provas e gabaritos
URL:
https://www2.quadrix.org.br/concursodataprev.aspx
Oficial: Sim

8. DATAPREV 2010 — Quadrix
Título: Página oficial do concurso Dataprev 2010
Tipo: Página oficial
URL:
https://quadrix.org.br/informacoes/2213/
Oficial: Sim

9. DATAPREV 2010 — Quadrix
Título: Acervo oficial do concurso Dataprev 2010
Tipo: Provas e gabaritos
URL:
https://www2.quadrix.org.br/dataprev.aspx
Oficial: Sim

O usuário deverá conseguir cadastrar, editar, desativar e excluir logicamente novos materiais.

Links podem mudar ou deixar de funcionar. Portanto:

- não deixar URLs espalhadas nas Views;
- armazenar tudo no banco;
- permitir manutenção pelo sistema;
- exibir mensagem amigável caso um material esteja indisponível.

==================================================
12. DESEMPENHO
==================================================

Criar indicadores de:

- total de horas;
- total de sessões;
- média diária;
- média semanal;
- questões respondidas;
- percentual geral de acertos;
- disciplina com maior desempenho;
- disciplina com menor desempenho;
- conteúdos mais estudados;
- conteúdos com mais erros;
- revisões concluídas;
- revisões atrasadas;
- cumprimento do cronograma;
- ofensiva atual;
- melhor ofensiva.

Filtros:

- período;
- disciplina;
- semana;
- tipo de atividade;
- conteúdo.

Gráficos:

- minutos por semana;
- acertos por disciplina;
- evolução de acertos;
- distribuição de tempo;
- tarefas concluídas;
- planejado versus realizado.

Reutilize a biblioteca de gráficos existente no projeto.
Caso não exista, use Chart.js de maneira modular.

==================================================
13. MIGRATIONS
==================================================

Criar migrations reais usando CodeIgniter 4 Database Forge.

Local:

app/Database/Migrations

Usar:

- nomes com timestamp;
- métodos up() e down();
- primary keys;
- foreign keys;
- índices;
- campos created_at;
- campos updated_at;
- deleted_at onde houver exclusão lógica;
- tipos compatíveis com o banco utilizado;
- nomes em snake_case;
- engine e charset compatíveis com o projeto.

Prefixar as tabelas do módulo com:

study_

Antes de criar relacionamento com usuários, identificar a tabela de usuários existente.

Não criar uma segunda tabela de usuários.

Se não existir autenticação ou tabela de usuários, informar isso antes de decidir a implementação.

Criar as seguintes tabelas:

--------------------------------------------------
13.1 study_exams
--------------------------------------------------

- id
- name
- year
- profile
- organizer
- exam_date nullable
- daily_minutes default 60
- active
- created_at
- updated_at
- deleted_at

--------------------------------------------------
13.2 study_subjects
--------------------------------------------------

- id
- exam_id
- parent_id nullable
- name
- slug
- category: general ou specific
- description nullable
- priority default 3
- weight nullable
- color nullable
- icon nullable
- sort_order
- active
- created_at
- updated_at
- deleted_at

Índices:

- exam_id
- parent_id
- category
- active

Criar índice único por exam_id + slug, respeitando compatibilidade do banco.

--------------------------------------------------
13.3 study_topics
--------------------------------------------------

- id
- subject_id
- parent_id nullable
- name
- slug
- description nullable
- estimated_minutes default 60
- difficulty default 2
- sort_order
- active
- created_at
- updated_at
- deleted_at

Índices:

- subject_id
- parent_id
- active

--------------------------------------------------
13.4 study_plans
--------------------------------------------------

- id
- user_id
- exam_id
- name
- start_date
- end_date nullable
- daily_minutes default 60
- weekdays em JSON ou TEXT, conforme compatibilidade
- review_intervals em JSON ou TEXT
- active
- created_at
- updated_at
- deleted_at

--------------------------------------------------
13.5 study_plan_weeks
--------------------------------------------------

- id
- plan_id
- week_number
- title
- objective nullable
- start_date
- end_date
- status
- created_at
- updated_at

Criar índice único por plan_id + week_number.

--------------------------------------------------
13.6 study_kanban_columns
--------------------------------------------------

- id
- code
- title
- color nullable
- position
- wip_limit nullable
- is_completed_column
- active
- created_at
- updated_at

Code inicial:

- backlog
- this_week
- today
- in_progress
- review
- done

--------------------------------------------------
13.7 study_tasks
--------------------------------------------------

- id
- user_id
- plan_id
- plan_week_id nullable
- subject_id
- topic_id nullable
- kanban_column_id
- title
- description nullable
- task_type
- scheduled_date nullable
- estimated_minutes default 60
- actual_minutes default 0
- priority default 3
- position default 0
- status
- is_required
- completed_at nullable
- created_at
- updated_at
- deleted_at

Tipos iniciais:

- theory
- questions
- review
- practice
- mock_exam

Índices:

- user_id
- scheduled_date
- subject_id
- topic_id
- kanban_column_id
- status
- plan_week_id
- kanban_column_id + position

--------------------------------------------------
13.8 study_task_checklists
--------------------------------------------------

- id
- task_id
- title
- estimated_minutes default 0
- position
- is_required
- is_completed
- completed_at nullable
- created_at
- updated_at

--------------------------------------------------
13.9 study_sessions
--------------------------------------------------

- id
- user_id
- task_id nullable
- subject_id
- topic_id nullable
- session_type
- started_at
- ended_at nullable
- duration_seconds default 0
- planned_minutes default 60
- status
- notes nullable
- created_at
- updated_at
- deleted_at

Status:

- running
- paused
- completed
- cancelled

Garantir que um usuário não possua duas sessões simultâneas em execução.

--------------------------------------------------
13.10 study_daily_progress
--------------------------------------------------

- id
- user_id
- progress_date
- planned_minutes default 60
- studied_minutes default 0
- tasks_planned default 0
- tasks_completed default 0
- questions_total default 0
- questions_correct default 0
- reviews_completed default 0
- goal_met
- xp_earned default 0
- created_at
- updated_at

Criar índice único por user_id + progress_date.

--------------------------------------------------
13.11 study_streaks
--------------------------------------------------

- id
- user_id
- current_streak default 0
- best_streak default 0
- total_qualified_days default 0
- last_qualified_date nullable
- record_date nullable
- created_at
- updated_at

Criar índice único por user_id.

--------------------------------------------------
13.12 study_streak_history
--------------------------------------------------

- id
- user_id
- reference_date
- previous_streak
- new_streak
- event_type
- description nullable
- created_at

Event types:

- started
- increased
- maintained
- broken
- recalculated
- record

--------------------------------------------------
13.13 study_reviews
--------------------------------------------------

- id
- user_id
- origin_task_id nullable
- subject_id
- topic_id
- review_number
- interval_days
- due_date
- status
- difficulty nullable
- questions_total default 0
- questions_correct default 0
- notes nullable
- completed_at nullable
- created_at
- updated_at
- deleted_at

--------------------------------------------------
13.14 study_exam_resources
--------------------------------------------------

- id
- exam_id
- year
- organizer
- title
- description nullable
- resource_type
- url
- is_official
- is_active
- sort_order
- created_at
- updated_at
- deleted_at

--------------------------------------------------
13.15 study_resource_attempts
--------------------------------------------------

- id
- user_id
- resource_id
- attempted_at
- questions_total default 0
- questions_correct default 0
- questions_wrong default 0
- questions_blank default 0
- duration_minutes default 0
- score_percentage
- notes nullable
- created_at
- updated_at
- deleted_at

--------------------------------------------------
13.16 study_question_attempts
--------------------------------------------------

- id
- user_id
- subject_id
- topic_id nullable
- resource_id nullable
- attempt_date
- source nullable
- questions_total
- questions_correct
- questions_wrong
- questions_blank
- duration_minutes default 0
- score_percentage
- error_notes nullable
- created_at
- updated_at
- deleted_at

--------------------------------------------------
13.17 study_task_status_history
--------------------------------------------------

- id
- task_id
- user_id
- from_column_id nullable
- to_column_id
- from_status nullable
- to_status
- created_at

--------------------------------------------------
13.18 study_user_settings
--------------------------------------------------

- id
- user_id
- daily_goal_minutes default 60
- timezone default America/Fortaleza
- study_weekdays
- review_intervals
- auto_complete_tasks
- notifications_enabled
- created_at
- updated_at

Criar índice único por user_id.

Avalie se alguma tabela pode ser removida por já existir algo equivalente no projeto. Não duplique estruturas existentes.

==================================================
14. MODELS, ENTITIES E SERVICES
==================================================

Criar Models compatíveis com o padrão do projeto.

Configurar corretamente:

- table;
- primaryKey;
- allowedFields;
- useTimestamps;
- useSoftDeletes;
- returnType;
- validationRules;
- validationMessages.

Criar Services para regras de negócio:

- StudyPlanService
- StudyTaskService
- StudySessionService
- StudyStreakService
- StudyReviewService
- StudyProgressService
- StudyStatisticsService
- StudyKanbanService

Controllers não devem concentrar regras complexas.

Utilizar transações para operações que atualizam várias tabelas.

==================================================
15. SEEDERS
==================================================

Criar os seeders em:

app/Database/Seeds

Estrutura sugerida:

- StudyExamSeeder
- StudyKanbanColumnSeeder
- StudySubjectSeeder
- StudyTopicSeeder
- StudyPlanSeeder
- StudyTaskSeeder
- StudyExamResourceSeeder
- DataprevStudySeeder

O DataprevStudySeeder deve chamar os demais na ordem correta.

Os seeders devem ser idempotentes.

Antes de inserir:

- buscar por chave natural;
- atualizar ou ignorar registros já existentes;
- nunca gerar duplicações a cada execução.

Não utilizar IDs fixos para relacionamentos quando for possível consultar o registro previamente.

==================================================
16. DISCIPLINAS DO SEED
==================================================

Conhecimentos específicos:

1. Desenvolvimento de Sistemas
2. Testes de Software
3. Arquitetura de Software
4. DevOps e Git
5. Metodologias Ágeis
6. Engenharia de Requisitos
7. Frontend Web e UX
8. Segurança da Informação
9. Banco de Dados
10. Business Intelligence
11. Gestão e Governança de TI
12. Inteligência Artificial, Dados e Big Data

Conhecimentos gerais do planejamento inicial:

1. Língua Portuguesa
2. Língua Inglesa
3. Raciocínio Lógico
4. Atualidades e Inteligência Artificial

As disciplinas e conteúdos devem permanecer editáveis.

==================================================
17. TÓPICOS DO SEED
==================================================

Criar tópicos contemplando pelo menos:

Desenvolvimento:

- Java
- Orientação a Objetos
- JavaEE
- JakartaEE
- JPA
- Hibernate
- JSF
- PrimeFaces
- Spring
- Spring Cloud
- Spring Boot
- JUnit
- Clean Code
- SonarQube
- desenvolvimento Android
- desenvolvimento iOS
- low-code
- no-code
- RPA

Arquitetura:

- REST
- JSON
- XML
- XSLT
- UDDI
- APIs
- Swagger
- Web Services
- mensageria
- interoperabilidade
- arquitetura orientada a serviços
- microsserviços
- API Gateway
- arquitetura hexagonal
- containers
- transações distribuídas
- servidor web
- servidor de aplicações
- Internet
- intranet
- extranet
- portais

Testes:

- testes unitários
- testes de integração
- testes automatizados
- testes ágeis
- testes de usabilidade
- tipos de teste
- TDD
- ciclo de vida de testes
- SAST
- DAST

Frontend e UX:

- HTML
- CSS
- JavaScript
- Ajax
- Vue
- Angular
- React
- SPA
- PWA
- padrões frontend
- UX
- acessibilidade
- usabilidade
- arquitetura da informação
- CMS
- workflow
- portais corporativos

Banco e BI:

- modelagem conceitual
- modelagem lógica
- modelagem física
- modelo relacional
- modelo multidimensional
- normalização
- integridade referencial
- metadados
- SQL
- DDL
- DML
- SGBD
- NoSQL
- banco em memória
- modelagem dimensional
- Data Warehouse
- Data Mining
- ETL
- ELT
- OLAP
- Data Lake
- Big Data
- dados estruturados
- dados não estruturados
- integração e ingestão de dados
- visualização de dados
- sistemas de suporte à decisão

Segurança:

- políticas de segurança
- confidencialidade
- integridade
- disponibilidade
- controle de acesso
- OAuth2
- SSO
- riscos
- ameaça
- vulnerabilidade
- impacto
- ISO 27001:2022
- ISO 27002:2022
- SDL
- OWASP Top 10
- HTTPS
- SSL
- TLS

Gestão:

- gerenciamento de projetos
- projetos
- programas
- portfólio
- abordagem tradicional
- abordagem híbrida
- abordagem ágil
- Scrum
- Kanban
- XP
- Lean
- Story Points
- Pontos de Função
- ITIL 4
- COBIT 2019
- BPMN
- gestão de riscos
- engenharia de requisitos
- classificação de requisitos
- elicitação de requisitos

==================================================
18. CRONOGRAMA INICIAL DE 24 SEMANAS
==================================================

Criar um plano inicial de 24 semanas.

Cada registro abaixo representa uma tarefa diária de 60 minutos.

SEMANA 1
Segunda: Java básico — sintaxe, classes e objetos
Terça: SQL e modelagem de dados
Quarta: Fundamentos de segurança da informação
Quinta: Git e Scrum
Sexta: Língua Portuguesa

SEMANA 2
Segunda: Orientação a objetos em Java
Terça: Normalização e integridade referencial
Quarta: ISO 27001 e ISO 27002 — introdução
Quinta: HTML, CSS e UX
Sexta: Raciocínio Lógico

SEMANA 3
Segunda: Collections, exceções e generics
Terça: DDL e DML
Quarta: OAuth2, SSO e controle de acesso
Quinta: REST e JSON
Sexta: Língua Inglesa

SEMANA 4
Segunda: Java, APIs e recursos modernos da linguagem
Terça: Banco de dados NoSQL
Quarta: OWASP Top 10 — introdução
Quinta: Swagger e documentação de APIs
Sexta: Revisão e mini simulado

SEMANA 5
Segunda: JPA
Terça: SQL intermediário e avançado
Quarta: Gestão de riscos de segurança
Quinta: Git avançado e estratégias de branch
Sexta: Língua Portuguesa

SEMANA 6
Segunda: Hibernate
Terça: ETL e ELT
Quarta: ISO 27001
Quinta: Testes unitários
Sexta: Língua Inglesa

SEMANA 7
Segunda: Spring Core
Terça: Data Warehouse
Quarta: ISO 27002
Quinta: JUnit
Sexta: Raciocínio Lógico

SEMANA 8
Segunda: Spring Boot
Terça: OLAP e modelagem dimensional
Quarta: SAST e DAST
Quinta: Clean Code e SonarQube
Sexta: Revisão e mini simulado

SEMANA 9
Segunda: Microsserviços
Terça: Banco de dados em memória
Quarta: HTTPS, SSL e TLS
Quinta: Containers
Sexta: Atualidades e Inteligência Artificial

SEMANA 10
Segunda: Arquitetura hexagonal
Terça: Data Lake
Quarta: Segurança de aplicações
Quinta: Docker e conceitos de containers
Sexta: Língua Portuguesa

SEMANA 11
Segunda: Mensageria
Terça: Big Data
Quarta: Security Development Lifecycle
Quinta: Orquestração de containers — conceitos
Sexta: Língua Inglesa

SEMANA 12
Segunda: API Gateway e orquestração de serviços
Terça: Integração e ingestão de dados
Quarta: OWASP Top 10 — revisão aprofundada
Quinta: DevOps
Sexta: Revisão e mini simulado

SEMANA 13
Segunda: React
Terça: Engenharia de requisitos
Quarta: BPMN
Quinta: UX e planejamento de interação
Sexta: Raciocínio Lógico

SEMANA 14
Segunda: Angular
Terça: Story Points
Quarta: Workflow
Quinta: Acessibilidade e usabilidade
Sexta: Atualidades e Inteligência Artificial

SEMANA 15
Segunda: Vue
Terça: Pontos de Função
Quarta: Sistemas de gestão de conteúdo
Quinta: SPA e PWA
Sexta: Língua Portuguesa

SEMANA 16
Segunda: Ajax e JavaScript
Terça: Modelagem dimensional
Quarta: Portais corporativos
Quinta: Revisão de frontend
Sexta: Língua Inglesa

SEMANA 17
Segunda: Scrum
Terça: Fundamentos de Business Intelligence
Quarta: COBIT 2019
Quinta: Kanban
Sexta: Revisão e mini simulado

SEMANA 18
Segunda: XP
Terça: Arquitetura de ETL
Quarta: ITIL 4
Quinta: Lean
Sexta: Língua Portuguesa

SEMANA 19
Segunda: Projetos tradicionais, híbridos e ágeis
Terça: Data Mining
Quarta: Gestão de riscos
Quinta: BPMN — exercícios
Sexta: Língua Inglesa

SEMANA 20
Segunda: Revisão de metodologias ágeis
Terça: Arquitetura de Business Intelligence
Quarta: Revisão geral de segurança
Quinta: Revisão de DevOps e Git
Sexta: Raciocínio Lógico

SEMANA 21
Segunda: Revisão de Java
Terça: Revisão de banco de dados
Quarta: Revisão de segurança
Quinta: Revisão de arquitetura
Sexta: Mini simulado

SEMANA 22
Segunda: Questões de Java
Terça: Questões de banco de dados
Quarta: Questões de segurança
Quinta: Questões de conhecimentos gerais
Sexta: Mini simulado

SEMANA 23
Segunda: Revisão dos erros de desenvolvimento
Terça: Revisão dos erros de banco e BI
Quarta: Revisão dos erros de segurança
Quinta: Revisão dos erros de gestão
Sexta: Mini simulado

SEMANA 24
Segunda: Simulado — parte 1
Terça: Correção do simulado
Quarta: Revisão dos assuntos com menor desempenho
Quinta: Revisão final dos principais erros
Sexta: Revisão leve e planejamento do próximo ciclo

Para cada tarefa do cronograma, gerar automaticamente os quatro itens de checklist da rotina diária.

A data inicial do plano deverá ser configurável.

Ao executar o seeder:

- encontrar a próxima segunda-feira a partir da data configurada; ou
- utilizar uma data informada por variável de ambiente;
- distribuir as tarefas somente de segunda a sexta;
- não criar tarefas duplicadas.

Variável sugerida:

study.planStartDate = 2026-08-03

Caso ela não exista, utilizar a próxima segunda-feira como início.

==================================================
19. EXPERIÊNCIA DIDÁTICA
==================================================

A interface deve orientar o usuário sem deixá-lo perdido.

Usar:

- textos claros;
- títulos objetivos;
- feedback após ações;
- estados vazios;
- tooltips quando necessário;
- barras de progresso;
- cores consistentes;
- indicadores de prioridade;
- mensagens de sucesso;
- confirmação em ações destrutivas;
- skeleton ou loading em requisições;
- layout mobile first.

Na página “Hoje”, mostrar somente o essencial:

1. O que estudar
2. Por quanto tempo
3. Checklist
4. Timer
5. Botão para iniciar
6. Revisões pendentes
7. Ofensiva

Evitar excesso de informações nessa tela.

==================================================
20. GAMIFICAÇÃO
==================================================

Implementar gamificação leve:

- ofensiva;
- recorde;
- XP diário;
- XP semanal;
- níveis;
- pequenas conquistas;
- feedback ao concluir tarefa;
- animação discreta de fogo;
- celebração visual ao bater recorde.

Regra inicial de XP:

- 1 XP por minuto estudado, limitado a 60 XP por tarefa;
- 10 XP por meta diária concluída;
- 5 XP por revisão concluída;
- 5 XP adicionais quando o aproveitamento em questões for igual ou superior a 80%.

Não permitir manipulação de XP diretamente pelo frontend.

O backend deve calcular e validar os valores.

Conquistas iniciais:

- Primeira sessão
- Primeira semana completa
- 5 dias de ofensiva
- 10 dias de ofensiva
- 30 dias de ofensiva
- 100 questões respondidas
- 500 questões respondidas
- 10 horas estudadas
- 50 horas estudadas
- 80% de acertos em um mini simulado

Caso a implementação de conquistas exija tabelas adicionais, criar:

- study_badges
- study_user_badges

==================================================
21. SEGURANÇA
==================================================

Aplicar:

- proteção CSRF;
- validação no backend;
- autorização por usuário;
- escaping nas Views;
- Query Builder ou Models;
- proteção contra mass assignment;
- validação de URLs;
- prevenção de IDOR;
- controle de acesso a tarefas e sessões;
- transações;
- logs de erros sem dados sensíveis.

Um usuário não pode acessar, editar ou excluir dados de outro usuário alterando IDs na URL ou requisição.

Links externos devem utilizar:

target="_blank"
rel="noopener noreferrer"

==================================================
22. TESTES
==================================================

Criar testes automatizados para, no mínimo:

- cálculo da ofensiva;
- geração de revisões;
- criação do cronograma;
- distribuição de tarefas em dias úteis;
- cálculo de percentual de acertos;
- validação de totais de questões;
- movimentação no Kanban;
- alteração de posição;
- conclusão de checklist;
- encerramento de sessão;
- autorização por usuário;
- idempotência dos seeders.

Utilizar a estrutura de testes já existente no projeto.

Não configurar banco de testes de forma destrutiva.

==================================================
23. COMANDOS
==================================================

Ao final, informar os comandos necessários, adaptados ao projeto.

Exemplos esperados:

php spark migrate
php spark db:seed DataprevStudySeeder
php spark migrate:status
php spark routes
php spark test

Não executar migrate:refresh em banco com dados sem autorização explícita.

==================================================
24. CRITÉRIOS DE ACEITE
==================================================

A implementação somente será considerada concluída quando:

[ ] O projeto existente continuar funcionando.
[ ] As migrations executarem sem erro.
[ ] Todos os métodos down() funcionarem.
[ ] Os seeders puderem ser executados mais de uma vez sem duplicação.
[ ] O concurso DATAPREV 2026 estiver cadastrado.
[ ] As disciplinas estiverem cadastradas.
[ ] Os principais tópicos do edital estiverem cadastrados.
[ ] As 24 semanas estiverem cadastradas.
[ ] As tarefas de segunda a sexta estiverem distribuídas.
[ ] Cada tarefa possuir checklist.
[ ] O dashboard funcionar.
[ ] O timer funcionar.
[ ] A ofensiva funcionar.
[ ] O Kanban persistir movimentações.
[ ] As revisões de 1, 7 e 30 dias forem geradas.
[ ] O registro de questões funcionar.
[ ] As provas antigas forem exibidas.
[ ] Os links externos abrirem de forma segura.
[ ] As estatísticas forem calculadas no backend.
[ ] O sistema estiver responsivo.
[ ] As ações possuírem autorização por usuário.
[ ] Os testes principais estiverem passando.
[ ] Não houver erros no log do CodeIgniter.

==================================================
25. ORDEM DE EXECUÇÃO
==================================================

Implemente nesta ordem:

FASE 1 — Diagnóstico
- analisar o projeto;
- identificar padrões;
- listar arquivos afetados.

FASE 2 — Banco de dados
- criar migrations;
- revisar relacionamentos;
- criar índices;
- validar métodos up e down.

FASE 3 — Dados iniciais
- criar seeders;
- cadastrar concurso;
- cadastrar disciplinas;
- cadastrar tópicos;
- cadastrar colunas do Kanban;
- cadastrar provas antigas;
- cadastrar cronograma de 24 semanas.

FASE 4 — Backend
- Models;
- Entities, caso sejam padrão do projeto;
- Services;
- validações;
- Controllers;
- rotas;
- autorização.

FASE 5 — Interface
- layout;
- dashboard;
- página Hoje;
- cronograma;
- Kanban;
- revisões;
- questões;
- provas antigas;
- desempenho.

FASE 6 — Gamificação
- ofensiva;
- XP;
- recordes;
- conquistas;
- feedback visual.

FASE 7 — Qualidade
- testes;
- validações;
- responsividade;
- acessibilidade;
- revisão de segurança;
- verificação de logs.

==================================================
26. FORMATO DA ENTREGA DO AGENTE
==================================================

Durante a implementação, apresente:

1. Diagnóstico do projeto.
2. Plano de implementação.
3. Lista de migrations.
4. Lista de tabelas e relacionamentos.
5. Arquivos criados.
6. Arquivos modificados.
7. Código implementado.
8. Comandos utilizados.
9. Resultado dos testes.
10. Pendências reais, caso existam.
11. Checklist final dos critérios de aceite.

Não entregue somente explicações.
Não entregue somente exemplos.
Não deixe TODOs nos pontos essenciais.
Não simule resultados.
Não declare que algo funciona sem verificar.
Preserve a arquitetura atual do projeto.
```

[1]: https://www.codeigniter.com/user_guide/dbmgmt/migration.html?utm_source=chatgpt.com "Database Migrations — CodeIgniter 4.7.2 documentation"
[2]: https://www.cebraspe.org.br/concursos/dataprev_23 "Cebraspe | O melhor em avaliação de pessoas"
