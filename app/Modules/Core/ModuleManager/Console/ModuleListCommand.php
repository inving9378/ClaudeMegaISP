<?php

namespace App\Modules\Core\ModuleManager\Console;

use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list';
    protected $description = 'List every discovered MegaISP module and its registry state.';

    public function handle(ModuleManagerService $manager): int
    {
        $registryAvailable = Schema::hasTable('module_registry');
        $registered = $registryAvailable
            ? ModuleRegistry::query()->get()->keyBy('slug')
            : collect();

        $rows = [];
        foreach ($manager->manifests() as $m) {
            $row = $registered->get($m['slug']);
            $rows[] = [
                $m['slug'] ?? '?',
                $m['type'] ?? '?',
                $m['version'] ?? '?',
                $registryAvailable
                    ? ($row ? ($row->active ? 'active' : 'inactive') : 'unregistered')
                    : 'pending-migration',
            ];
        }

        $this->table(['Slug', 'Type', 'Version', 'State'], $rows);
        return self::SUCCESS;
    }
}
