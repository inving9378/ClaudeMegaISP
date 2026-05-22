<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $allPermissions = [
        'whatsapp_view_conversations',
        'whatsapp_send_messages',
        'whatsapp_manage_instances',
        'whatsapp_view_logs',
        'whatsapp_use_ia',
    ];

    private array $sellerPermissions = [
        'whatsapp_view_conversations',
        'whatsapp_send_messages',
        'whatsapp_use_ia',
    ];

    public function up(): void
    {
        foreach ($this->allPermissions as $name) {
            try {
                Permission::create(['name' => $name, 'guard_name' => 'web']);
            } catch (PermissionAlreadyExists $e) {
                // ya existe
            }
        }

        $allRoleNames = [
            ComunConstantsController::SUPER_ADMIN_ROLE,
            'Super Administrador',
            'Administrador',
            ComunConstantsController::DEVELOPER_ROLE,
        ];

        foreach ($allRoleNames as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                continue;
            }
            foreach ($this->allPermissions as $permName) {
                $perm = Permission::where('name', $permName)->where('guard_name', 'web')->first();
                if ($perm) {
                    $role->givePermissionTo($perm);
                }
            }
            foreach (User::role($role->name)->get() as $user) {
                foreach ($this->allPermissions as $permName) {
                    $perm = Permission::where('name', $permName)->where('guard_name', 'web')->first();
                    if ($perm) {
                        $user->givePermissionTo($perm);
                    }
                }
            }
        }

        $vendedorRole = Role::where('name', ComunConstantsController::SELLER_ROLE)->first();
        if ($vendedorRole) {
            foreach ($this->sellerPermissions as $permName) {
                $perm = Permission::where('name', $permName)->where('guard_name', 'web')->first();
                if ($perm) {
                    $vendedorRole->givePermissionTo($perm);
                }
            }
            foreach (User::role($vendedorRole->name)->get() as $user) {
                foreach ($this->sellerPermissions as $permName) {
                    $perm = Permission::where('name', $permName)->where('guard_name', 'web')->first();
                    if ($perm) {
                        $user->givePermissionTo($perm);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->allPermissions as $name) {
            $perm = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($perm) {
                foreach (Role::all() as $role) {
                    $role->revokePermissionTo($perm);
                }
                foreach (User::permission($perm->name)->get() as $user) {
                    $user->revokePermissionTo($perm);
                }
                $perm->delete();
            }
        }
    }
};
