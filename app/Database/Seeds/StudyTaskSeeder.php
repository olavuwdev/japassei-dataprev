<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use DateTimeImmutable;
use RuntimeException;

/**
 * Cadastra as 120 tarefas do cronograma de 24 semanas (seção 18 do plano),
 * cada uma com o checklist padrão de 4 itens (seção 6).
 *
 * Chave natural da tarefa: user_id + plan_id + scheduled_date + title.
 * Chave natural do item de checklist: task_id + title.
 */
class StudyTaskSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $user = $this->db->table('users')
            ->where('email', 'estudante@japassei.local')
            ->get()
            ->getRowArray();

        if ($user === null) {
            throw new RuntimeException('Usuário padrão não encontrado. Execute o UserSeeder antes.');
        }

        $userId = (int) $user['id'];

        $plan = $this->db->table('study_plans')
            ->where('user_id', $userId)
            ->where('name', StudyPlanSeeder::PLAN_NAME)
            ->get()
            ->getRowArray();

        if ($plan === null) {
            throw new RuntimeException('Plano de estudos não encontrado. Execute o StudyPlanSeeder antes.');
        }

        $planId    = (int) $plan['id'];
        $planStart = new DateTimeImmutable($plan['start_date']);
        $planEnd   = new DateTimeImmutable($plan['end_date'] ?? $plan['start_date']);

        $weekIdByNumber = [];
        foreach ($this->db->table('study_plan_weeks')->where('plan_id', $planId)->get()->getResultArray() as $week) {
            $weekIdByNumber[(int) $week['week_number']] = (int) $week['id'];
        }

        $subjectIdBySlug = [];
        foreach ($this->db->table('study_subjects')->where('exam_id', (int) $plan['exam_id'])->get()->getResultArray() as $subject) {
            $subjectIdBySlug[$subject['slug']] = (int) $subject['id'];
        }

        $columnIdByCode = [];
        foreach ($this->db->table('study_kanban_columns')->get()->getResultArray() as $column) {
            $columnIdByCode[$column['code']] = (int) $column['id'];
        }

        foreach (['backlog', 'this_week', 'today'] as $requiredColumn) {
            if (! isset($columnIdByCode[$requiredColumn])) {
                throw new RuntimeException('Colunas do Kanban não encontradas. Execute o StudyKanbanColumnSeeder antes.');
            }
        }

        $studyDays = $this->weekdayRange($planStart, $planEnd);
        if ($studyDays === []) {
            throw new RuntimeException('O intervalo do plano não possui dias úteis (segunda a sexta).');
        }

        // Lista plana na ordem do cronograma (120 itens).
        $flat = [];
        foreach ($this->schedule() as $weekNumber => $days) {
            foreach ($days as $dayOfWeek => [$title, $subjectSlug]) {
                $flat[] = [
                    'week'    => $weekNumber,
                    'title'   => $title,
                    'subject' => $subjectSlug,
                ];
            }
        }

        $totalTasks = count($flat);
        $totalDays  = count($studyDays);

        $today      = new DateTimeImmutable('today');
        $weekMonday = $today->modify('-' . (((int) $today->format('N')) - 1) . ' days');
        $weekSunday = $weekMonday->modify('+6 days');

        $positionByColumn = [
            'backlog'   => 0,
            'this_week' => 0,
            'today'     => 0,
        ];

        foreach ($flat as $index => $item) {
            if (! isset($weekIdByNumber[$item['week']])) {
                throw new RuntimeException(sprintf('Semana %d não encontrada. Execute o StudyPlanSeeder antes.', $item['week']));
            }

            if (! isset($subjectIdBySlug[$item['subject']])) {
                throw new RuntimeException(
                    sprintf('Disciplina "%s" não encontrada. Execute o StudySubjectSeeder antes.', $item['subject'])
                );
            }

            // Distribui as 120 tarefas pelos dias úteis do intervalo (pode haver >1/dia).
            $dayIndex      = (int) floor($index * $totalDays / $totalTasks);
            $dayIndex      = min($dayIndex, $totalDays - 1);
            $scheduledYmd  = $studyDays[$dayIndex];
            $scheduledDate = new DateTimeImmutable($scheduledYmd);
            $weekId        = $weekIdByNumber[$item['week']];

            $existing = $this->db->table('study_tasks')
                ->where('user_id', $userId)
                ->where('plan_id', $planId)
                ->where('plan_week_id', $weekId)
                ->where('title', $item['title'])
                ->get()
                ->getRowArray();

            $columnCode = $this->resolveColumnCode($scheduledDate, $today, $weekSunday);
            $positionByColumn[$columnCode]++;

            $payload = [
                'subject_id'        => $subjectIdBySlug[$item['subject']],
                'kanban_column_id'  => $columnIdByCode[$columnCode],
                'task_type'         => $this->resolveTaskType($item['title']),
                'scheduled_date'    => $scheduledYmd,
                'estimated_minutes' => 60,
                'priority'          => 3,
                'position'          => $positionByColumn[$columnCode],
                'is_required'       => 1,
                'updated_at'        => $now,
            ];

            if ($existing !== null) {
                // Atualiza datas/colunas; preserva progresso (status, minutos, completed_at).
                $this->db->table('study_tasks')->where('id', $existing['id'])->update($payload);
                $this->seedChecklist((int) $existing['id'], $now);

                continue;
            }

            $this->db->table('study_tasks')->insert($payload + [
                'user_id'        => $userId,
                'plan_id'        => $planId,
                'plan_week_id'   => $weekId,
                'topic_id'       => null,
                'title'          => $item['title'],
                'description'    => null,
                'actual_minutes' => 0,
                'status'         => 'pending',
                'completed_at'   => null,
                'created_at'     => $now,
            ]);

            $this->seedChecklist((int) $this->db->insertID(), $now);
        }
    }

    /**
     * Dias úteis (segunda a sexta) inclusivos entre duas datas.
     *
     * @return list<string> Y-m-d
     */
    private function weekdayRange(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $days = [];
        $cursor = $start;

        while ($cursor <= $end) {
            if ((int) $cursor->format('N') <= 5) {
                $days[] = $cursor->format('Y-m-d');
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $days;
    }

    private function resolveColumnCode(
        DateTimeImmutable $scheduledDate,
        DateTimeImmutable $today,
        DateTimeImmutable $weekSunday
    ): string {
        if ($scheduledDate == $today) {
            return 'today';
        }

        if ($scheduledDate > $today && $scheduledDate <= $weekSunday) {
            return 'this_week';
        }

        // Datas futuras fora da semana corrente e datas passadas não concluídas.
        return 'backlog';
    }

    private function resolveTaskType(string $title): string
    {
        if (mb_stripos($title, 'simulado') !== false) {
            return 'mock_exam';
        }

        if (mb_stripos($title, 'questões') !== false) {
            return 'questions';
        }

        if (mb_stripos($title, 'revisão') !== false || mb_stripos($title, 'correção') !== false) {
            return 'review';
        }

        return 'theory';
    }

    private function seedChecklist(int $taskId, string $now): void
    {
        // [title, estimated_minutes, position]
        $items = [
            ['Revisar conteúdo anterior',        10, 1],
            ['Estudar teoria',                   25, 2],
            ['Resolver questões',                20, 3],
            ['Registrar erros e observações',     5, 4],
        ];

        foreach ($items as [$title, $minutes, $position]) {
            $existing = $this->db->table('study_task_checklists')
                ->where('task_id', $taskId)
                ->where('title', $title)
                ->get()
                ->getRowArray();

            if ($existing !== null) {
                continue;
            }

            $this->db->table('study_task_checklists')->insert([
                'task_id'           => $taskId,
                'title'             => $title,
                'estimated_minutes' => $minutes,
                'position'          => $position,
                'is_required'       => 1,
                'is_completed'      => 0,
                'completed_at'      => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }
    }

    /**
     * Cronograma da seção 18: semana => dia da semana (1=segunda ... 5=sexta)
     * => [título da tarefa, slug da disciplina].
     *
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    private function schedule(): array
    {
        return [
            1 => [
                1 => ['Java básico — sintaxe, classes e objetos', 'desenvolvimento-de-sistemas'],
                2 => ['SQL e modelagem de dados', 'banco-de-dados'],
                3 => ['Fundamentos de segurança da informação', 'seguranca-da-informacao'],
                4 => ['Git e Scrum', 'devops-e-git'],
                5 => ['Língua Portuguesa', 'lingua-portuguesa'],
            ],
            2 => [
                1 => ['Orientação a objetos em Java', 'desenvolvimento-de-sistemas'],
                2 => ['Normalização e integridade referencial', 'banco-de-dados'],
                3 => ['ISO 27001 e ISO 27002 — introdução', 'seguranca-da-informacao'],
                4 => ['HTML, CSS e UX', 'frontend-web-e-ux'],
                5 => ['Raciocínio Lógico', 'raciocinio-logico'],
            ],
            3 => [
                1 => ['Collections, exceções e generics', 'desenvolvimento-de-sistemas'],
                2 => ['DDL e DML', 'banco-de-dados'],
                3 => ['OAuth2, SSO e controle de acesso', 'seguranca-da-informacao'],
                4 => ['REST e JSON', 'arquitetura-de-software'],
                5 => ['Língua Inglesa', 'lingua-inglesa'],
            ],
            4 => [
                1 => ['Java, APIs e recursos modernos da linguagem', 'desenvolvimento-de-sistemas'],
                2 => ['Banco de dados NoSQL', 'banco-de-dados'],
                3 => ['OWASP Top 10 — introdução', 'seguranca-da-informacao'],
                4 => ['Swagger e documentação de APIs', 'arquitetura-de-software'],
                5 => ['Revisão e mini simulado', 'desenvolvimento-de-sistemas'],
            ],
            5 => [
                1 => ['JPA', 'desenvolvimento-de-sistemas'],
                2 => ['SQL intermediário e avançado', 'banco-de-dados'],
                3 => ['Gestão de riscos de segurança', 'seguranca-da-informacao'],
                4 => ['Git avançado e estratégias de branch', 'devops-e-git'],
                5 => ['Língua Portuguesa', 'lingua-portuguesa'],
            ],
            6 => [
                1 => ['Hibernate', 'desenvolvimento-de-sistemas'],
                2 => ['ETL e ELT', 'business-intelligence'],
                3 => ['ISO 27001', 'seguranca-da-informacao'],
                4 => ['Testes unitários', 'testes-de-software'],
                5 => ['Língua Inglesa', 'lingua-inglesa'],
            ],
            7 => [
                1 => ['Spring Core', 'desenvolvimento-de-sistemas'],
                2 => ['Data Warehouse', 'business-intelligence'],
                3 => ['ISO 27002', 'seguranca-da-informacao'],
                4 => ['JUnit', 'testes-de-software'],
                5 => ['Raciocínio Lógico', 'raciocinio-logico'],
            ],
            8 => [
                1 => ['Spring Boot', 'desenvolvimento-de-sistemas'],
                2 => ['OLAP e modelagem dimensional', 'business-intelligence'],
                3 => ['SAST e DAST', 'seguranca-da-informacao'],
                4 => ['Clean Code e SonarQube', 'desenvolvimento-de-sistemas'],
                5 => ['Revisão e mini simulado', 'desenvolvimento-de-sistemas'],
            ],
            9 => [
                1 => ['Microsserviços', 'arquitetura-de-software'],
                2 => ['Banco de dados em memória', 'banco-de-dados'],
                3 => ['HTTPS, SSL e TLS', 'seguranca-da-informacao'],
                4 => ['Containers', 'arquitetura-de-software'],
                5 => ['Atualidades e Inteligência Artificial', 'atualidades-e-inteligencia-artificial'],
            ],
            10 => [
                1 => ['Arquitetura hexagonal', 'arquitetura-de-software'],
                2 => ['Data Lake', 'business-intelligence'],
                3 => ['Segurança de aplicações', 'seguranca-da-informacao'],
                4 => ['Docker e conceitos de containers', 'devops-e-git'],
                5 => ['Língua Portuguesa', 'lingua-portuguesa'],
            ],
            11 => [
                1 => ['Mensageria', 'arquitetura-de-software'],
                2 => ['Big Data', 'business-intelligence'],
                3 => ['Security Development Lifecycle', 'seguranca-da-informacao'],
                4 => ['Orquestração de containers — conceitos', 'devops-e-git'],
                5 => ['Língua Inglesa', 'lingua-inglesa'],
            ],
            12 => [
                1 => ['API Gateway e orquestração de serviços', 'arquitetura-de-software'],
                2 => ['Integração e ingestão de dados', 'business-intelligence'],
                3 => ['OWASP Top 10 — revisão aprofundada', 'seguranca-da-informacao'],
                4 => ['DevOps', 'devops-e-git'],
                5 => ['Revisão e mini simulado', 'arquitetura-de-software'],
            ],
            13 => [
                1 => ['React', 'frontend-web-e-ux'],
                2 => ['Engenharia de requisitos', 'engenharia-de-requisitos'],
                3 => ['BPMN', 'gestao-e-governanca-de-ti'],
                4 => ['UX e planejamento de interação', 'frontend-web-e-ux'],
                5 => ['Raciocínio Lógico', 'raciocinio-logico'],
            ],
            14 => [
                1 => ['Angular', 'frontend-web-e-ux'],
                2 => ['Story Points', 'metodologias-ageis'],
                3 => ['Workflow', 'frontend-web-e-ux'],
                4 => ['Acessibilidade e usabilidade', 'frontend-web-e-ux'],
                5 => ['Atualidades e Inteligência Artificial', 'atualidades-e-inteligencia-artificial'],
            ],
            15 => [
                1 => ['Vue', 'frontend-web-e-ux'],
                2 => ['Pontos de Função', 'gestao-e-governanca-de-ti'],
                3 => ['Sistemas de gestão de conteúdo', 'frontend-web-e-ux'],
                4 => ['SPA e PWA', 'frontend-web-e-ux'],
                5 => ['Língua Portuguesa', 'lingua-portuguesa'],
            ],
            16 => [
                1 => ['Ajax e JavaScript', 'frontend-web-e-ux'],
                2 => ['Modelagem dimensional', 'business-intelligence'],
                3 => ['Portais corporativos', 'frontend-web-e-ux'],
                4 => ['Revisão de frontend', 'frontend-web-e-ux'],
                5 => ['Língua Inglesa', 'lingua-inglesa'],
            ],
            17 => [
                1 => ['Scrum', 'metodologias-ageis'],
                2 => ['Fundamentos de Business Intelligence', 'business-intelligence'],
                3 => ['COBIT 2019', 'gestao-e-governanca-de-ti'],
                4 => ['Kanban', 'metodologias-ageis'],
                5 => ['Revisão e mini simulado', 'metodologias-ageis'],
            ],
            18 => [
                1 => ['XP', 'metodologias-ageis'],
                2 => ['Arquitetura de ETL', 'business-intelligence'],
                3 => ['ITIL 4', 'gestao-e-governanca-de-ti'],
                4 => ['Lean', 'metodologias-ageis'],
                5 => ['Língua Portuguesa', 'lingua-portuguesa'],
            ],
            19 => [
                1 => ['Projetos tradicionais, híbridos e ágeis', 'gestao-e-governanca-de-ti'],
                2 => ['Data Mining', 'business-intelligence'],
                3 => ['Gestão de riscos', 'gestao-e-governanca-de-ti'],
                4 => ['BPMN — exercícios', 'gestao-e-governanca-de-ti'],
                5 => ['Língua Inglesa', 'lingua-inglesa'],
            ],
            20 => [
                1 => ['Revisão de metodologias ágeis', 'metodologias-ageis'],
                2 => ['Arquitetura de Business Intelligence', 'business-intelligence'],
                3 => ['Revisão geral de segurança', 'seguranca-da-informacao'],
                4 => ['Revisão de DevOps e Git', 'devops-e-git'],
                5 => ['Raciocínio Lógico', 'raciocinio-logico'],
            ],
            21 => [
                1 => ['Revisão de Java', 'desenvolvimento-de-sistemas'],
                2 => ['Revisão de banco de dados', 'banco-de-dados'],
                3 => ['Revisão de segurança', 'seguranca-da-informacao'],
                4 => ['Revisão de arquitetura', 'arquitetura-de-software'],
                5 => ['Mini simulado', 'desenvolvimento-de-sistemas'],
            ],
            22 => [
                1 => ['Questões de Java', 'desenvolvimento-de-sistemas'],
                2 => ['Questões de banco de dados', 'banco-de-dados'],
                3 => ['Questões de segurança', 'seguranca-da-informacao'],
                4 => ['Questões de conhecimentos gerais', 'lingua-portuguesa'],
                5 => ['Mini simulado', 'desenvolvimento-de-sistemas'],
            ],
            23 => [
                1 => ['Revisão dos erros de desenvolvimento', 'desenvolvimento-de-sistemas'],
                2 => ['Revisão dos erros de banco e BI', 'banco-de-dados'],
                3 => ['Revisão dos erros de segurança', 'seguranca-da-informacao'],
                4 => ['Revisão dos erros de gestão', 'gestao-e-governanca-de-ti'],
                5 => ['Mini simulado', 'desenvolvimento-de-sistemas'],
            ],
            24 => [
                1 => ['Simulado — parte 1', 'desenvolvimento-de-sistemas'],
                2 => ['Correção do simulado', 'desenvolvimento-de-sistemas'],
                3 => ['Revisão dos assuntos com menor desempenho', 'desenvolvimento-de-sistemas'],
                4 => ['Revisão final dos principais erros', 'desenvolvimento-de-sistemas'],
                5 => ['Revisão leve e planejamento do próximo ciclo', 'gestao-e-governanca-de-ti'],
            ],
        ];
    }
}
