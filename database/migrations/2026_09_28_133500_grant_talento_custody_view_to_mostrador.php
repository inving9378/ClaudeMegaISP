<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * David (28-sep): "¿y el supervisor y mostrador no lo ven?" — al verificar
 * la pestaña Custodia con Playwright, Mostrador quedaba REDIRIGIDA en
 * silencio a Dashboard al entrar a /talento/custodia (denegación silenciosa
 * de navegación completa — CheckRoutePermission). Causa: `talento.custody.view`
 * (la única que gatea `/talento/custodia` en config/route_permission.php,
 * tanto a nivel middleware como en TalentoCustodiaController::index()) NO
 * incluía a Mostrador — solo TECNICO/TECNICO_PLANTA/TECNICO_INSTALADOR y
 * los roles admin con bypass. `talento.employees.view` (que Mostrador sí
 * tiene, migración 2026_09_28_100000) solo cubre el endpoint de datos
 * (`/talento/api/colaboradores/{id}/custodia`), no la ruta de la página.
 *
 * Mismo patrón de las migraciones previas de esta rama para Mostrador.
 * Idempotente, down() no revoca.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('name', 'Mostrador')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }

        $permiso = Permission::firstOrCreate(
            ['name' => 'talento.custody.view', 'guard_name' => 'web'],
            ['description' => 'Ver la custodia de material de los colaboradores']
        );

        if (! $role->hasPermissionTo($permiso)) {
            $role->givePermissionTo($permiso);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op a propósito.
    }
};
