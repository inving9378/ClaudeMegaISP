<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TalentoAcademySeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            ['title' => 'Seguridad en campo (NOM-001)',      'department' => 'técnicos', 'order' => 1],
            ['title' => 'Fusión óptica — técnica y normas',  'department' => 'técnicos', 'order' => 2],
            ['title' => 'Calidad de caja ODB',               'department' => 'técnicos', 'order' => 3],
            ['title' => 'Atención al cliente y servicio',    'department' => 'general',  'order' => 4],
            ['title' => 'Uso del sistema MegaISP',           'department' => 'general',  'order' => 5],
        ];

        foreach ($courses as $c) {
            DB::table('talento_courses')->updateOrInsert(
                ['title' => $c['title']],
                array_merge($c, [
                    'active'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Vincular material de referencia al curso de fusión (lectura de talento_construction_standards)
        $fusionCourse = DB::table('talento_courses')->where('title', 'Fusión óptica — técnica y normas')->first();
        $fusionStd    = DB::table('talento_construction_standards')->where('type', 'fusion_loss')->first();
        if ($fusionCourse && $fusionStd) {
            DB::table('talento_course_materials')->updateOrInsert(
                ['course_id' => $fusionCourse->id, 'reference_standard_id' => $fusionStd->id],
                [
                    'type'                   => 'reference',
                    'title'                  => 'Estándar de pérdida de fusión',
                    'reference_standard_id'  => $fusionStd->id,
                    'order'                  => 10,
                    'created_by'             => null,
                    'created_at'             => now(),
                ]
            );
        }

        // Vincular material de referencia al curso de seguridad (lectura de talento_penalty_types)
        $secCourse  = DB::table('talento_courses')->where('title', 'Seguridad en campo (NOM-001)')->first();
        $noCasco    = DB::table('talento_penalty_types')->where('name', 'Sin casco de seguridad')->first();
        if ($secCourse && $noCasco) {
            DB::table('talento_course_materials')->updateOrInsert(
                ['course_id' => $secCourse->id, 'reference_penalty_type_id' => $noCasco->id],
                [
                    'type'                        => 'reference',
                    'title'                       => 'Penalización: sin casco',
                    'reference_penalty_type_id'   => $noCasco->id,
                    'order'                       => 10,
                    'created_by'                  => null,
                    'created_at'                  => now(),
                ]
            );
        }
    }
}
