<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Cadastra os tópicos de estudo por disciplina (seção 17 do plano).
 * Chave natural: subject_id + slug.
 */
class StudyTopicSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $exam = $this->db->table('study_exams')
            ->where('name', 'DATAPREV 2026')
            ->where('year', 2026)
            ->get()
            ->getRowArray();

        if ($exam === null) {
            throw new RuntimeException('Concurso "DATAPREV 2026" não encontrado. Execute o StudyExamSeeder antes.');
        }

        $subjects = $this->db->table('study_subjects')
            ->where('exam_id', (int) $exam['id'])
            ->get()
            ->getResultArray();

        $subjectIdBySlug = [];
        foreach ($subjects as $subject) {
            $subjectIdBySlug[$subject['slug']] = (int) $subject['id'];
        }

        foreach ($this->topicsBySubjectSlug() as $subjectSlug => $topics) {
            if (! isset($subjectIdBySlug[$subjectSlug])) {
                throw new RuntimeException(
                    sprintf('Disciplina "%s" não encontrada. Execute o StudySubjectSeeder antes.', $subjectSlug)
                );
            }

            $subjectId = $subjectIdBySlug[$subjectSlug];
            $sortOrder = 0;

            foreach ($topics as $topicName) {
                $sortOrder++;
                $slug = $this->slugify($topicName);

                $existing = $this->db->table('study_topics')
                    ->where('subject_id', $subjectId)
                    ->where('slug', $slug)
                    ->get()
                    ->getRowArray();

                $mutable = [
                    'name'              => $topicName,
                    'estimated_minutes' => 60,
                    'difficulty'        => 2,
                    'sort_order'        => $sortOrder,
                    'active'            => 1,
                    'updated_at'        => $now,
                ];

                if ($existing === null) {
                    $this->db->table('study_topics')->insert($mutable + [
                        'subject_id'  => $subjectId,
                        'parent_id'   => null,
                        'slug'        => $slug,
                        'description' => null,
                        'created_at'  => $now,
                    ]);

                    continue;
                }

                $this->db->table('study_topics')->where('id', $existing['id'])->update($mutable);
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function topicsBySubjectSlug(): array
    {
        return [
            'desenvolvimento-de-sistemas' => [
                'Java',
                'Orientação a Objetos',
                'JavaEE',
                'JakartaEE',
                'JPA',
                'Hibernate',
                'JSF',
                'PrimeFaces',
                'Spring',
                'Spring Cloud',
                'Spring Boot',
                'JUnit',
                'Clean Code',
                'SonarQube',
                'Desenvolvimento Android',
                'Desenvolvimento iOS',
                'Low-code',
                'No-code',
                'RPA',
            ],
            'arquitetura-de-software' => [
                'REST',
                'JSON',
                'XML',
                'XSLT',
                'UDDI',
                'APIs',
                'Swagger',
                'Web Services',
                'Mensageria',
                'Interoperabilidade',
                'Arquitetura orientada a serviços',
                'Microsserviços',
                'API Gateway',
                'Arquitetura hexagonal',
                'Containers',
                'Transações distribuídas',
                'Servidor web',
                'Servidor de aplicações',
                'Internet',
                'Intranet',
                'Extranet',
                'Portais',
            ],
            'testes-de-software' => [
                'Testes unitários',
                'Testes de integração',
                'Testes automatizados',
                'Testes ágeis',
                'Testes de usabilidade',
                'Tipos de teste',
                'TDD',
                'Ciclo de vida de testes',
                'SAST',
                'DAST',
            ],
            'frontend-web-e-ux' => [
                'HTML',
                'CSS',
                'JavaScript',
                'Ajax',
                'Vue',
                'Angular',
                'React',
                'SPA',
                'PWA',
                'Padrões frontend',
                'UX',
                'Acessibilidade',
                'Usabilidade',
                'Arquitetura da informação',
                'CMS',
                'Workflow',
                'Portais corporativos',
            ],
            'banco-de-dados' => [
                'Modelagem conceitual',
                'Modelagem lógica',
                'Modelagem física',
                'Modelo relacional',
                'Normalização',
                'Integridade referencial',
                'Metadados',
                'SQL',
                'DDL',
                'DML',
                'SGBD',
                'NoSQL',
                'Banco em memória',
            ],
            'business-intelligence' => [
                'Modelo multidimensional',
                'Modelagem dimensional',
                'Data Warehouse',
                'Data Mining',
                'ETL',
                'ELT',
                'OLAP',
                'Data Lake',
                'Big Data',
                'Dados estruturados',
                'Dados não estruturados',
                'Integração e ingestão de dados',
                'Visualização de dados',
                'Sistemas de suporte à decisão',
            ],
            'seguranca-da-informacao' => [
                'Políticas de segurança',
                'Confidencialidade',
                'Integridade',
                'Disponibilidade',
                'Controle de acesso',
                'OAuth2',
                'SSO',
                'Riscos',
                'Ameaça',
                'Vulnerabilidade',
                'Impacto',
                'ISO 27001:2022',
                'ISO 27002:2022',
                'SDL',
                'OWASP Top 10',
                'HTTPS',
                'SSL',
                'TLS',
            ],
            'engenharia-de-requisitos' => [
                'Engenharia de requisitos',
                'Classificação de requisitos',
                'Elicitação de requisitos',
            ],
            'metodologias-ageis' => [
                'Abordagem ágil',
                'Scrum',
                'Kanban',
                'XP',
                'Lean',
                'Story Points',
            ],
            'gestao-e-governanca-de-ti' => [
                'Gerenciamento de projetos',
                'Projetos',
                'Programas',
                'Portfólio',
                'Abordagem tradicional',
                'Abordagem híbrida',
                'Pontos de Função',
                'ITIL 4',
                'COBIT 2019',
                'BPMN',
                'Gestão de riscos',
            ],
            'devops-e-git' => [
                'Git',
                'CI/CD',
                'Docker',
                'Pipelines',
                'Monitoramento',
            ],
            'inteligencia-artificial-dados-e-big-data' => [
                'Machine Learning',
                'Deep Learning',
                'Processamento de Linguagem Natural (NLP)',
                'Governança de dados',
            ],
            'lingua-portuguesa' => [
                'Interpretação de texto',
                'Gramática',
                'Redação oficial',
                'Coesão e coerência',
            ],
            'lingua-inglesa' => [
                'Leitura e interpretação',
                'Vocabulário técnico',
                'Gramática',
            ],
            'raciocinio-logico' => [
                'Lógica proposicional',
                'Sequências',
                'Problemas aritméticos',
                'Análise combinatória',
            ],
            'atualidades-e-inteligencia-artificial' => [
                'Fundamentos de IA',
                'LLMs e IA generativa',
                'Atualidades de tecnologia',
            ],
        ];
    }

    private function slugify(string $text): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];

        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = strtr($text, $map);
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }
}
