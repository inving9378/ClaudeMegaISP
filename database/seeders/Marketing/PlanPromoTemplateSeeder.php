<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\VideoTemplate;
use Illuminate\Database\Seeder;

class PlanPromoTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            [
                'slug'         => 'plan_promo_9_16',
                'json_file'    => 'plan_promo_9_16.json',
                'aspect_ratios'=> ['9:16'],
                'estimated'    => 60,
            ],
            [
                'slug'         => 'plan_promo_1_1',
                'json_file'    => 'plan_promo_1_1.json',
                'aspect_ratios'=> ['1:1'],
                'estimated'    => 50,
            ],
            [
                'slug'         => 'plan_promo_16_9',
                'json_file'    => 'plan_promo_16_9.json',
                'aspect_ratios'=> ['16:9'],
                'estimated'    => 65,
            ],
        ];

        foreach ($defs as $def) {
            $jsonPath = storage_path("app/marketing/templates/{$def['json_file']}");
            $schema   = file_exists($jsonPath) ? json_decode(file_get_contents($jsonPath), true) : [];

            VideoTemplate::updateOrCreate(
                ['slug' => $def['slug'], 'company_id' => 1],
                [
                    'company_id'               => 1,
                    'name'                     => $schema['name']        ?? $def['slug'],
                    'description'              => $schema['description'] ?? null,
                    'category'                 => 'plan_promo',
                    'aspect_ratios'            => $def['aspect_ratios'],
                    'duration_seconds'         => $schema['duration_seconds'] ?? 30,
                    'schema'                   => $schema,
                    'preview_thumbnail'        => null,
                    'active'                   => true,
                    'engine_version'           => $schema['engine_version']  ?? '1.0',
                    'requires_voiceover'       => $schema['requires_voiceover'] ?? true,
                    'estimated_render_seconds' => $def['estimated'],
                    'default_variables'        => [
                        'plan_features' => 'Sin contrato,Instalación gratis,Soporte 24/7',
                        'cta_text'      => '¡Contrata ahora!',
                    ],
                ]
            );
        }
    }
}
