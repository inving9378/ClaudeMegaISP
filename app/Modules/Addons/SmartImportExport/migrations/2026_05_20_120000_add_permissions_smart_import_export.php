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
     * Permisos de Importación/Exportación inteligente. Reservado para
     * roles administrativos por su alcance sobre cualquier tabla del sistema.
     */
    private array $permissions = [
        'smart_import_view',
        'smart_import_execute',
        'smart_export_view',
        'smart_export_execute',
    ];

    public function up(): void
    {
        $rolesNombres = array_filter([
            ComunConstantsController::SUPER_ADMIN_ROLE ?? null,
            defined(ComunConstantsController::class . '::ADMIN_ROLE') ? ComunConstantsController::ADMIN_ROLE : null,
            defined(ComunConstantsController::class . '::DEVELOPER_ROLE') ? ComunConstantsController::DEVELOPER_ROLE : null,
        ]);

        $roles = collect($rolesNombres)
            ->map(fn ($n) => Role::where('name', $n)->first())
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
