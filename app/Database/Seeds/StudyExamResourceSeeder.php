<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Cadastra os materiais de provas antigas da Dataprev (seção 11 do plano).
 * Chave natural: url.
 */
class StudyExamResourceSeeder extends Seeder
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

        // [year, organizer, title, resource_type, url]
        $resources = [
            [2023, 'Cebraspe', 'Página oficial do concurso Dataprev 2023', 'official_page',  'https://www.cebraspe.org.br/concursos/dataprev_23'],
            [2014, 'Quadrix',  'Página oficial do concurso Dataprev 2014', 'official_page',  'https://quadrix.org.br/informacoes/2081/'],
            [2014, 'Quadrix',  'Acervo oficial do concurso Dataprev 2014', 'exams_answers',  'https://www2.quadrix.org.br/concursoDATAPREV2014.aspx'],
            [2014, 'Quadrix',  'Gabarito preliminar Dataprev 2014',        'answer_key',     'https://www.quadrix.org.br/resources/1/concursos/2014/DATAPREV2014/dataprev14_gabarito_preliminar.pdf'],
            [2012, 'Quadrix',  'Página oficial do concurso Dataprev 2012', 'official_page',  'https://quadrix.org.br/informacoes/2152/'],
            [2011, 'Quadrix',  'Página oficial do concurso Dataprev 2011', 'official_page',  'https://quadrix.org.br/informacoes/2178/'],
            [2011, 'Quadrix',  'Acervo oficial do concurso Dataprev 2011', 'exams_answers',  'https://www2.quadrix.org.br/concursodataprev.aspx'],
            [2010, 'Quadrix',  'Página oficial do concurso Dataprev 2010', 'official_page',  'https://quadrix.org.br/informacoes/2213/'],
            [2010, 'Quadrix',  'Acervo oficial do concurso Dataprev 2010', 'exams_answers',  'https://www2.quadrix.org.br/dataprev.aspx'],
        ];

        $sortOrder = 0;

        foreach ($resources as [$year, $organizer, $title, $resourceType, $url]) {
            $sortOrder++;

            $existing = $this->db->table('study_exam_resources')
                ->where('url', $url)
                ->get()
                ->getRowArray();

            $mutable = [
                'exam_id'       => $examId,
                'year'          => $year,
                'organizer'     => $organizer,
                'title'         => $title,
                'resource_type' => $resourceType,
                'is_official'   => 1,
                'is_active'     => 1,
                'sort_order'    => $sortOrder,
                'updated_at'    => $now,
            ];

            if ($existing === null) {
                $this->db->table('study_exam_resources')->insert($mutable + [
                    'url'         => $url,
                    'description' => null,
                    'created_at'  => $now,
                ]);

                continue;
            }

            $this->db->table('study_exam_resources')->where('id', $existing['id'])->update($mutable);
        }
    }
}
