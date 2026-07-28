<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use DateTimeImmutable;
use Exception;
use RuntimeException;

/**
 * Cadastra o plano de 24 semanas e as respectivas study_plan_weeks.
 * Chave natural do plano: user_id + name. Das semanas: plan_id + week_number.
 *
 * Datas:
 * - study.planStartDate (padrão: próxima segunda-feira)
 * - study.planEndDate (opcional; se ausente, calcula 24 semanas a partir do início)
 *
 * Se ambas forem definidas, as 24 semanas são comprimidas no intervalo.
 */
class StudyPlanSeeder extends Seeder
{
    public const PLAN_NAME = 'Plano DATAPREV 2026 — 24 semanas';

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

        $exam = $this->db->table('study_exams')
            ->where('name', 'DATAPREV 2026')
            ->where('year', 2026)
            ->get()
            ->getRowArray();

        if ($exam === null) {
            throw new RuntimeException('Concurso "DATAPREV 2026" não encontrado. Execute o StudyExamSeeder antes.');
        }

        $userId    = (int) $user['id'];
        $examId    = (int) $exam['id'];
        $startDate = $this->resolveStartDate();
        $endDate   = $this->resolveEndDate($startDate);

        $plan = $this->db->table('study_plans')
            ->where('user_id', $userId)
            ->where('name', self::PLAN_NAME)
            ->get()
            ->getRowArray();

        $payload = [
            'exam_id'          => $examId,
            'start_date'       => $startDate->format('Y-m-d'),
            'end_date'         => $endDate->format('Y-m-d'),
            'daily_minutes'    => 60,
            'weekdays'         => json_encode([1, 2, 3, 4, 5]),
            'review_intervals' => json_encode([1, 7, 30]),
            'active'           => 1,
            'updated_at'       => $now,
        ];

        if ($plan === null) {
            $this->db->table('study_plans')->insert($payload + [
                'user_id'    => $userId,
                'name'       => self::PLAN_NAME,
                'created_at' => $now,
            ]);
            $planId = (int) $this->db->insertID();
        } else {
            $planId = (int) $plan['id'];
            $this->db->table('study_plans')->where('id', $planId)->update($payload);
        }

        $this->seedWeeks($planId, $startDate, $endDate, $now);
    }

    private function resolveStartDate(): DateTimeImmutable
    {
        $configured = env('study.planStartDate');

        if (is_string($configured) && trim($configured, " \t\n\r\0\x0B'\"") !== '') {
            try {
                return new DateTimeImmutable(trim($configured, " \t\n\r\0\x0B'\""));
            } catch (Exception $e) {
                // cai no fallback
            }
        }

        $today     = new DateTimeImmutable('today');
        $dayOfWeek = (int) $today->format('N');

        return $dayOfWeek === 1 ? $today : $today->modify('+' . (8 - $dayOfWeek) . ' days');
    }

    private function resolveEndDate(DateTimeImmutable $startDate): DateTimeImmutable
    {
        $configured = env('study.planEndDate');

        if (is_string($configured) && trim($configured, " \t\n\r\0\x0B'\"") !== '') {
            try {
                $end = new DateTimeImmutable(trim($configured, " \t\n\r\0\x0B'\""));
                if ($end >= $startDate) {
                    return $end;
                }
            } catch (Exception $e) {
                // cai no fallback
            }
        }

        // Última sexta-feira da semana 24 (início numa segunda).
        return $startDate->modify('+23 weeks +4 days');
    }

    private function seedWeeks(
        int $planId,
        DateTimeImmutable $planStart,
        DateTimeImmutable $planEnd,
        string $now
    ): void {
        $spanDays = max(1, (int) $planStart->diff($planEnd)->days);

        foreach ($this->weekObjectives() as $weekNumber => $objective) {
            $offsetStart = (int) floor(($weekNumber - 1) * $spanDays / 24);
            $offsetEnd   = max($offsetStart, (int) floor($weekNumber * $spanDays / 24) - 1);

            $weekStart = $planStart->modify('+' . $offsetStart . ' days');
            $weekEnd   = $planStart->modify('+' . $offsetEnd . ' days');

            if ($weekEnd > $planEnd) {
                $weekEnd = $planEnd;
            }

            $existing = $this->db->table('study_plan_weeks')
                ->where('plan_id', $planId)
                ->where('week_number', $weekNumber)
                ->get()
                ->getRowArray();

            $mutable = [
                'title'      => 'Semana ' . $weekNumber,
                'objective'  => $objective,
                'start_date' => $weekStart->format('Y-m-d'),
                'end_date'   => $weekEnd->format('Y-m-d'),
                'updated_at' => $now,
            ];

            if ($existing === null) {
                $this->db->table('study_plan_weeks')->insert($mutable + [
                    'plan_id'     => $planId,
                    'week_number' => $weekNumber,
                    'status'      => 'pending',
                    'created_at'  => $now,
                ]);

                continue;
            }

            $this->db->table('study_plan_weeks')->where('id', $existing['id'])->update($mutable);
        }
    }

    /**
     * @return array<int, string>
     */
    private function weekObjectives(): array
    {
        return [
            1  => 'Java básico, SQL e modelagem de dados, fundamentos de segurança, Git e Scrum, Língua Portuguesa.',
            2  => 'Orientação a objetos, normalização e integridade, ISO 27001/27002, HTML/CSS/UX, Raciocínio Lógico.',
            3  => 'Collections e generics, DDL e DML, OAuth2/SSO e controle de acesso, REST e JSON, Língua Inglesa.',
            4  => 'Java moderno e APIs, NoSQL, OWASP Top 10, Swagger e documentação, revisão e mini simulado.',
            5  => 'JPA, SQL intermediário e avançado, gestão de riscos de segurança, Git avançado, Língua Portuguesa.',
            6  => 'Hibernate, ETL e ELT, ISO 27001, testes unitários, Língua Inglesa.',
            7  => 'Spring Core, Data Warehouse, ISO 27002, JUnit, Raciocínio Lógico.',
            8  => 'Spring Boot, OLAP e modelagem dimensional, SAST e DAST, Clean Code e SonarQube, revisão e mini simulado.',
            9  => 'Microsserviços, banco em memória, HTTPS/SSL/TLS, containers, Atualidades e IA.',
            10 => 'Arquitetura hexagonal, Data Lake, segurança de aplicações, Docker, Língua Portuguesa.',
            11 => 'Mensageria, Big Data, Security Development Lifecycle, orquestração de containers, Língua Inglesa.',
            12 => 'API Gateway, integração e ingestão de dados, OWASP revisão aprofundada, DevOps, revisão e mini simulado.',
            13 => 'React, engenharia de requisitos, BPMN, UX e planejamento de interação, Raciocínio Lógico.',
            14 => 'Angular, Story Points, workflow, acessibilidade e usabilidade, Atualidades e IA.',
            15 => 'Vue, Pontos de Função, sistemas de gestão de conteúdo, SPA e PWA, Língua Portuguesa.',
            16 => 'Ajax e JavaScript, modelagem dimensional, portais corporativos, revisão de frontend, Língua Inglesa.',
            17 => 'Scrum, fundamentos de Business Intelligence, COBIT 2019, Kanban, revisão e mini simulado.',
            18 => 'XP, arquitetura de ETL, ITIL 4, Lean, Língua Portuguesa.',
            19 => 'Projetos tradicionais/híbridos/ágeis, Data Mining, gestão de riscos, BPMN exercícios, Língua Inglesa.',
            20 => 'Revisões de metodologias ágeis, arquitetura de BI, segurança geral, DevOps e Git, Raciocínio Lógico.',
            21 => 'Revisões de Java, banco de dados, segurança e arquitetura; mini simulado.',
            22 => 'Questões de Java, banco de dados, segurança e conhecimentos gerais; mini simulado.',
            23 => 'Revisão dos erros de desenvolvimento, banco e BI, segurança e gestão; mini simulado.',
            24 => 'Simulado final, correção, revisão dos pontos fracos e planejamento do próximo ciclo.',
        ];
    }
}
