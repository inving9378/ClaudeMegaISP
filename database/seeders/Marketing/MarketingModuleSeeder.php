<?php

namespace Database\Seeders\Marketing;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketingModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('modules')->where('name', 'Marketing')->where('is_main', 1)->exists()) {
            return;
        }

        $moduleId = DB::table('modules')->insertGetId([
            'name'        => 'Marketing',
            'is_main'     => 1,
            'main'        => null,
            'description' => 'Módulo de Marketing Automation — captura de leads, CRM, campañas, conversaciones y generación de contenido con IA',
            'group'       => null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $submodules = [
            ['name' => 'Captura de leads',          'description' => 'Gestión y captura de prospectos/leads'],
            ['name' => 'Pipeline (CRM)',             'description' => 'Kanban y seguimiento de leads en el embudo de ventas'],
            ['name' => 'Campañas',                   'description' => 'Gestión de campañas Meta Ads, Google Ads y orgánicas'],
            ['name' => 'Conversaciones',             'description' => 'Bandeja de conversaciones con leads por canal'],
            ['name' => 'Contenido y publicaciones',  'description' => 'Generación de copy, video y programación de publicaciones'],
            ['name' => 'Configuración módulo',       'description' => 'Configuración general del módulo de Marketing'],
        ];

        foreach ($submodules as $sub) {
            DB::table('modules')->insert([
                'name'        => $sub['name'],
                'is_main'     => 0,
                'main'        => null,
                'description' => $sub['description'],
                'group'       => 'Marketing',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}
