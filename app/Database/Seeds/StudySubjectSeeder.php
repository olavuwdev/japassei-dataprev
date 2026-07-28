<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Cadastra as 16 disciplinas do concurso DATAPREV 2026.
 * Chave natural: exam_id + slug.
 */
class StudySubjectSeeder extends Seeder
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

        $examId = (int) $exam['id'];

        // [name, slug, category, color, icon]
        $subjects = [
            ['Desenvolvimento de Sistemas',                'desenvolvimento-de-sistemas',              'specific', '#E74C3C', 'code'],
            ['Testes de Software',                         'testes-de-software',                       'specific', '#16A085', 'check-circle'],
            ['Arquitetura de Software',                    'arquitetura-de-software',                  'specific', '#8E44AD', 'layers'],
            ['DevOps e Git',                               'devops-e-git',                             'specific', '#2C3E50', 'git-branch'],
            ['Metodologias Ágeis',                         'metodologias-ageis',                       'specific', '#F39C12', 'refresh-cw'],
            ['Engenharia de Requisitos',                   'engenharia-de-requisitos',                 'specific', '#27AE60', 'clipboard-list'],
            ['Frontend Web e UX',                          'frontend-web-e-ux',                        'specific', '#E67E22', 'layout'],
            ['Segurança da Informação',                    'seguranca-da-informacao',                  'specific', '#C0392B', 'shield'],
            ['Banco de Dados',                             'banco-de-dados',                           'specific', '#2980B9', 'database'],
            ['Business Intelligence',                      'business-intelligence',                    'specific', '#1ABC9C', 'bar-chart'],
            ['Gestão e Governança de TI',                  'gestao-e-governanca-de-ti',                'specific', '#7F8C8D', 'briefcase'],
            ['Inteligência Artificial, Dados e Big Data',  'inteligencia-artificial-dados-e-big-data', 'specific', '#9B59B6', 'cpu'],
            ['Língua Portuguesa',                          'lingua-portuguesa',                        'general',  '#3498DB', 'book-open'],
            ['Língua Inglesa',                             'lingua-inglesa',                           'general',  '#34495E', 'globe'],
            ['Raciocínio Lógico',                          'raciocinio-logico',                        'general',  '#D35400', 'puzzle'],
            ['Atualidades e Inteligência Artificial',      'atualidades-e-inteligencia-artificial',    'general',  '#8D6E63', 'newspaper'],
        ];

        $sortOrder = 0;

        foreach ($subjects as [$name, $slug, $category, $color, $icon]) {
            $sortOrder++;

            $existing = $this->db->table('study_subjects')
                ->where('exam_id', $examId)
                ->where('slug', $slug)
                ->get()
                ->getRowArray();

            $mutable = [
                'name'       => $name,
                'category'   => $category,
                'priority'   => 3,
                'color'      => $color,
                'icon'       => $icon,
                'sort_order' => $sortOrder,
                'active'     => 1,
                'updated_at' => $now,
            ];

            if ($existing === null) {
                $this->db->table('study_subjects')->insert($mutable + [
                    'exam_id'     => $examId,
                    'parent_id'   => null,
                    'slug'        => $slug,
                    'description' => null,
                    'weight'      => null,
                    'created_at'  => $now,
                ]);

                continue;
            }

            $this->db->table('study_subjects')->where('id', $existing['id'])->update($mutable);
        }
    }
}
