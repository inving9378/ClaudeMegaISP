<?php

namespace App\Modules\Addons\Demo;

use App\Modules\Contracts\ModuleDefinition as BaseDefinition;
use Illuminate\Support\Facades\DB;

class ModuleDefinition extends BaseDefinition
{
    public function moduleDir(): string
    {
        return __DIR__;
    }

    public function install(): void
    {
        // Seeder inicial: tres items de ejemplo
        DB::table('demo_items')->insert([
            ['name' => 'Item Alpha', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Item Beta',  'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Item Gamma', 'active' => false,'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        // Nada extra: las migraciones delta corren automáticamente
    }

    public function uninstall(bool $keepData = false): void
    {
        // Nada extra: las migraciones con rollback borran la tabla
    }
}
