<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 5d (item roadmap #761). Doble puerta para el checklist de offboarding:
 * `.offboarding.ver` (consultar qué le falta revocar a un colaborador saliente)
 * y `.offboarding.gestionar` (marcar un acceso/activo como revocado). Se
 * declaran nuevos en vez de reusar `.inventario.accesos.view` porque ese
 * permiso es de LECTURA del inventario completo (todas las empresas que el
 * usuario puede ver); offboarding es una acción dirigida a UN colaborador,
 * con su propio botón de escritura — mismo criterio que ya separó
 * `.registro.manage` de `.concepto.manage`.
 * Aditiva/idempotente: crea los permisos y los asigna a los roles base
 * (super-administrator + DESARROLLADOR).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['documentacion-corporativa.offboarding.ver', 'documentacion-corporativa.offboarding.gestionar'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
            app(PermissionSyncService::class)->syncPermissionToBaseRoles($permiso);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierten permisos. down() vacío a propósito.
    }
};
