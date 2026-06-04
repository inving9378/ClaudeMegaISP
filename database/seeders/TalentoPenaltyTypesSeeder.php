<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TalentoPenaltyTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // Safety
            ['name' => 'Sin casco de seguridad',          'category' => 'safety',      'penalty_kind' => 'event', 'amount' => 150.00],
            ['name' => 'Sin guantes de trabajo',           'category' => 'safety',      'penalty_kind' => 'event', 'amount' => 100.00],
            ['name' => 'Sin cono/señalamiento de precaución', 'category' => 'safety',   'penalty_kind' => 'event', 'amount' => 200.00],
            ['name' => 'Escalera mal colocada o insegura', 'category' => 'safety',      'penalty_kind' => 'event', 'amount' => 250.00],
            ['name' => 'Trabajo en altura sin arnés',      'category' => 'safety',      'penalty_kind' => 'event', 'amount' => 500.00],
            // Malpractice
            ['name' => 'Mal trato de material/equipo',     'category' => 'malpractice', 'penalty_kind' => 'event', 'amount' => 300.00],
            ['name' => 'Fusión con pérdida fuera de norma','category' => 'malpractice', 'penalty_kind' => 'event', 'amount' => 200.00],
            ['name' => 'Cierre de caja sin limpieza',      'category' => 'malpractice', 'penalty_kind' => 'event', 'amount' => 150.00],
            // Aesthetic
            ['name' => 'Cable mal tendido / sin sujección','category' => 'aesthetic',   'penalty_kind' => 'event', 'amount' => 100.00],
            ['name' => 'Sin etiquetado o etiqueta ilegible','category' => 'aesthetic',  'penalty_kind' => 'event', 'amount' =>  80.00],
            // Other / status
            ['name' => 'Llegada tarde reiterada (status)',  'category' => 'other',       'penalty_kind' => 'status', 'amount' => 100.00],
        ];

        foreach ($types as $t) {
            DB::table('talento_penalty_types')->updateOrInsert(
                ['name' => $t['name']],
                array_merge($t, ['active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
