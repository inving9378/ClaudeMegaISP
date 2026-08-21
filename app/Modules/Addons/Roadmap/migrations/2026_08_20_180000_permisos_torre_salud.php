<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Item #891 — Fase 7 de la Épica #874: permiso de los DOS botones del panel de salud del entorno
 * (reintentar trabajos fallidos, limpiar/recalentar cachés). El GET de los indicadores reusa el
 * permiso ya existente `roadmap_view` (mismo gate que `historialAcciones`, #885) — no hace falta
 * uno nuevo para solo lectura.
 *
 * `torre.salud.manage` ejecuta comandos reales (queue:retry, cache:clear, config:cache
 * condicionado) → solo `super-administrator` + `DESARROLLADOR`, SIN `.view` en el nombre para que
 * `syncPermissionToBaseRoles` no lo reparta al resto de roles.
 *
 * ADITIVA: `firstOrCreate` + `syncPermissionToBaseRoles`. NUNCA `syncPermissions`.
 */
return new class extends Migration
{
    private const PERMISO = 'torre.salud.manage';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::PERMISO, 'guard_name' => 'web'],
            ['description' => 'Ejecutar las acciones del panel de salud del entorno de la Torre (reintentar jobs, recalentar cachés)']
        );

        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISO);
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->delete();
    }
};
