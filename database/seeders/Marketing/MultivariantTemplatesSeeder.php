<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\VideoTemplate;
use Illuminate\Database\Seeder;

class MultivariantTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGamerTemplate();
    }

    protected function seedGamerTemplate(): void
    {
        $schemaPath = storage_path('app/marketing/templates/multivariant_plan_promo_gamer_9_16.json');
        $schema     = json_decode(file_get_contents($schemaPath), true);

        VideoTemplate::updateOrCreate(
            ['slug' => 'multivariant-plan-promo-gamer'],
            [
                'company_id'              => 1,
                'name'                    => 'Plan Promo - Gamer',
                'category'                => 'multivariant_plan_promo',
                'description'             => 'Video multivariante para nicho Gamer. B-roll dinámico + texto cinético.',
                'active'                  => true,
                'schema'                  => $schema,
                'aspect_ratios'           => ['9:16', '1:1', '16:9'],
                'duration_seconds'        => 15,
                'requires_voiceover'      => true,
                'estimated_render_seconds'=> 120,
            ]
        );
    }
}
