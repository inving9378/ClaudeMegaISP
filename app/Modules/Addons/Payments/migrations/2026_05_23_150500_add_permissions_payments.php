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
     * Permisos del módulo de Pagos. Reservados para roles administrativos
     * porque tocan credenciales de proveedores externos y dinero real.
     * El webhook entrante NO requiere permiso (es público, valida por firma).
     */
    private array $permissions = [
        'payments_view_providers',     // listar proveedores configurados (sin ver credenciales)
        'payments_manage_providers',   // crear/editar/desactivar proveedores + ver/editar credenciales
        'payments_assign_clabe',       // generar CLABE virtual para un cliente
        'payments_view_receipts',      // descargar comprobantes/payloads del webhook
        'payments_view_webhooks_log',  // ver bitácora de eventos (incluye fallidos)
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
