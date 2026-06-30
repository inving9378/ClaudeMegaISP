<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Permisos de la cola de conciliación (motor de cobro nativo).
     *   reconciliation_view    → ver el banner y la cola de tickets de conciliación
     *   reconciliation_resolve → resolver/descartar un ticket de conciliación
     *
     * Reservados a roles administrativos (tocan dinero real). Mismo patrón
     * idempotente que add_permissions_payments. La tabla `permissions` solo
     * tiene name/guard_name (no hay columna description).
     */
    private array $permissions = [
        'reconciliation_view',
        'reconciliation_resolve',
    ];

    public function up(): void
    {
        $rolesNombres = array_filter([
            ComunConstantsController::SUPER_ADMIN_ROLE ?? null,
            defined(ComunConstantsController::class . '::DEVELOPER_ROLE') ? ComunConstantsController::DEVELOPER_ROLE : null,
        ]);

        $roles = collect($rolesNombres)
            ->map(fn ($n) => Role::where('name', $n)->where('guard_name', 'web')->first())
            ->filter()
            ->values();

        foreach ($this->permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();

            if (!$permission) {
                try {
                    $permission = Permission::create([
                        'name'       => $permissionName,
                        'guard_name' => 'web',
                    ]);
                } catch (PermissionAlreadyExists $e) {
                    $permission = Permission::where('name', $permissionName)
                        ->where('guard_name', 'web')
                        ->first();
                }
            }

            foreach ($roles as $role) {
                $role->givePermissionTo($permission);
                foreach (User::role($role->name)->get() as $user) {
                    $user->givePermissionTo($permission);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();
            if ($permission) {
                $permission->delete();
            }
        }
    }
};
