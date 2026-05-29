<?php

namespace Database\Seeders\Marketing;

use Illuminate\Database\Seeder;

class MarketingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MarketingChannelSeeder::class,
            MarketingLeadSourceSeeder::class,
            MarketingDefaultPipelineSeeder::class,
            MarketingContentTemplatesSeeder::class,
            MarketingDefaultSettingsSeeder::class,
            MarketingModuleSeeder::class,
            MarketingPermissionsSeeder::class,
            VideoEngineSettingsSeeder::class,
            PlanPromoTemplateSeeder::class,
        ]);
    }
}
