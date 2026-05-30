<?php

namespace App\Modules\Addons\Vendedores;

use App\Modules\Contracts\ModuleDefinition as BaseDefinition;
use Illuminate\Support\Facades\DB;

class ModuleDefinition extends BaseDefinition
{
    public function moduleDir(): string
    {
        return __DIR__;
    }

    /**
     * Siembra catálogos base solo si las tablas están vacías.
     * Las migraciones legacy que crean las tablas ya corrieron antes de esta llamada.
     */
    public function install(): void
    {
        if (DB::table('seller_status')->count() === 0) {
            DB::table('seller_status')->insert([
                ['name' => 'Activo',    'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Inactivo',  'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Suspendido','created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (DB::table('seller_types')->count() === 0) {
            DB::table('seller_types')->insert([
                ['name' => 'Interno',  'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Externo',  'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    /**
     * Las migraciones delta corren automáticamente desde migrations/.
     * No hay lógica extra de datos al actualizar.
     */
    public function upgrade(string $fromVersion, string $toVersion): void {}

    /**
     * Módulo legacy: las tablas de Vendedores viven en migraciones globales con
     * datos de producción. El uninstall NUNCA dropea tablas; solo retira permisos,
     * menú, config e IA (lo hace ModuleLifecycleService). Ver MIGRATION.md.
     */
    public function uninstall(bool $keepData = false): void {}
}
