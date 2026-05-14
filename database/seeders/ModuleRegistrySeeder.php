<?php

namespace Database\Seeders;

use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ModuleRegistrySeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('module_registry')) {
            $this->command?->warn('module_registry table missing — run `php artisan migrate` first.');
            return;
        }

        $now = Carbon::now();

        foreach (ModuleManagerService::instance()->manifests() as $manifest) {
            $slug = $manifest['slug'] ?? null;
            if (! $slug) {
                continue;
            }

            ModuleRegistry::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'         => $manifest['name'] ?? $slug,
                    'version'      => $manifest['version'] ?? '0.1.0',
                    'type'         => $manifest['type'] ?? 'addon',
                    'active'       => (bool) ($manifest['active'] ?? true),
                    'installed_at' => $now,
                ]
            );
        }
    }
}
