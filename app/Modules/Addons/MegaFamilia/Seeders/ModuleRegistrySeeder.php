<?php

namespace App\Modules\Addons\MegaFamilia\Seeders;

use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ModuleRegistrySeeder extends Seeder
{
    public function run(): void
    {
        ModuleRegistry::updateOrCreate(
            ['slug' => 'addon-megafamilia'],
            [
                'name' => 'MegaFamilia',
                'version' => '0.1.0',
                'type' => 'addon',
                'active' => true,
                'installed_at' => Carbon::now(),
            ]
        );
    }
}
