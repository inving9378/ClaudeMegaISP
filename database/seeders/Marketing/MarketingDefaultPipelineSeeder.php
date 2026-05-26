<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\Pipeline;
use App\Models\Marketing\PipelineStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketingDefaultPipelineSeeder extends Seeder
{
    public function run(): void
    {
        $pipeline = Pipeline::firstOrCreate(
            ['company_id' => 1, 'slug' => 'ventas-estandar'],
            [
                'company_id' => 1,
                'name' => 'Ventas estándar',
                'slug' => 'ventas-estandar',
                'description' => 'Pipeline de ventas por defecto',
                'is_default' => true,
                'active' => true,
            ]
        );

        $stages = [
            ['slug' => 'nuevo',       'name' => 'Nuevo',       'color' => '#6C757D', 'order' => 1, 'default_score' => 10,  'is_won' => false, 'is_lost' => false],
            ['slug' => 'contactado',  'name' => 'Contactado',  'color' => '#17A2B8', 'order' => 2, 'default_score' => 25,  'is_won' => false, 'is_lost' => false],
            ['slug' => 'calificado',  'name' => 'Calificado',  'color' => '#FFC107', 'order' => 3, 'default_score' => 50,  'is_won' => false, 'is_lost' => false],
            ['slug' => 'negociacion', 'name' => 'Negociación', 'color' => '#FD7E14', 'order' => 4, 'default_score' => 75,  'is_won' => false, 'is_lost' => false],
            ['slug' => 'ganado',      'name' => 'Ganado',      'color' => '#28A745', 'order' => 5, 'default_score' => 100, 'is_won' => true,  'is_lost' => false],
            ['slug' => 'perdido',     'name' => 'Perdido',     'color' => '#DC3545', 'order' => 6, 'default_score' => 0,   'is_won' => false, 'is_lost' => true],
        ];

        foreach ($stages as $stage) {
            PipelineStage::firstOrCreate(
                ['pipeline_id' => $pipeline->id, 'slug' => $stage['slug']],
                array_merge($stage, ['pipeline_id' => $pipeline->id])
            );
        }
    }
}
