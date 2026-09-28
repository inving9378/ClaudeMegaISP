<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * David (28-sep): "admin, desarrollador y mostrador si debe salirle la
 * lista completa" (el listado de /talento) — los supervisores (relación
 * supervisor_id, no un rol) ven en cambio solo a su equipo.
 *
 * Hallazgo real al verificar con Playwright: `talento.employees.view` se
 * usa por TEXTO en config/route_permission.php (~15 rutas) pero NUNCA
 * existió como fila real en `permissions` — nadie podía tenerlo de verdad
 * nunca (admin/DESARROLLADOR pasan por el bypass de
 * CheckRoutePermission::isAdmin()/isDevelopment(), nunca necesitaron que
 * la fila existiera). `Role::hasPermissionTo()` con un nombre inexistente
 * lanza PermissionDoesNotExist — por eso la migración anterior no
 * lanzaba error pero tampoco otorgaba nada. Se crea la fila (firstOrCreate,
 * mismo guard 'web' que el resto del sistema) antes de otorgarla.
 *
 * Mismo patrón de las migraciones previas de esta rama para Mostrador
 * (2026_09_28_083000 y 2026_09_28_090000). Idempotente, down() no revoca.
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
            ['name' => 'talento.employees.view', 'guard_name' => 'web'],
            ['description' => 'Ver el roster completo de colaboradores de Talento']
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
