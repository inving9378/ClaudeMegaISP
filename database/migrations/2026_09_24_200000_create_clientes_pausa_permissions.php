<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos de la pausa de facturación programada (Etapa 3, 2026-09-24, decisión de Irving):
 * clientes.pausa.crear / .cancelar / .reanudar — gatean los 3 endpoints de acción de
 * ClientBillingPauseController. Roles operativos = los mismos que hoy tienen
 * `client_edit_client` (medido en BD), que es el permiso que ya gatea la pantalla
 * Cliente > Facturación donde vive el botón "Pausar facturación".
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'clientes.pausa.crear' => 'Solicitar/programar una pausa de facturación para un cliente',
        'clientes.pausa.cancelar' => 'Cancelar una pausa de facturación antes de que inicie',
        'clientes.pausa.reanudar' => 'Reanudar anticipadamente una pausa de facturación en curso',
    ];

    private const ROLES_OPERATIVOS = [
        'Super Administrador',
        'Administrador',
        'Mostrador',
        'Vendedor',
        'ADMINISTRADOR_COMPLETO',
        'SUPERVISOR_MOSTRADOR',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );

            app(PermissionSyncService::class)->syncPermissionToBaseRoles($name);

            foreach (self::ROLES_OPERATIVOS as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role && !$role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::PERMISSIONS))->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
