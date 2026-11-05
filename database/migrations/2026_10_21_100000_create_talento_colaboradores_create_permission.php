<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permiso propio para dar de alta perfiles de colaborador. Hasta hoy crear y editar una
 * ficha usaban el mismo permiso (`talento.manage`); el Mostrador debe poder editar pero
 * no dar de alta (decisión 2026-10-21: "como un admin, sin crear ni eliminar usuarios").
 *
 * Lo reciben los roles y las cuentas que hoy tienen `talento.manage`, así nadie que hoy da
 * de alta pierde esa capacidad. Mostrador se salta a propósito aunque tuviera
 * `talento.manage`. Solo agrega; idempotente; roles por nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $crear  = Permission::firstOrCreate(['name' => 'talento.colaboradores.create', 'guard_name' => 'web']);
        $manage = Permission::where('name', 'talento.manage')->where('guard_name', 'web')->first();

        if ($manage) {
            foreach ($manage->roles as $role) {
                if ($role->name === 'Mostrador' || $role->hasPermissionTo($crear)) {
                    continue;
                }
                $role->givePermissionTo($crear);
                Log::info("Talento: talento.colaboradores.create dado al rol {$role->name}");
            }

            $userIds = DB::table('model_has_permissions')
                ->where('permission_id', $manage->id)
                ->where('model_type', \App\Models\User::class)
                ->pluck('model_id');
            foreach (\App\Models\User::whereIn('id', $userIds)->get() as $user) {
                if (! $user->hasDirectPermission($crear)) {
                    $user->givePermissionTo($crear);
                    Log::info("Talento: talento.colaboradores.create dado a la cuenta {$user->login_user}");
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * No se revierte: quitar el permiso dejaría sin poder dar de alta a quien hoy puede.
     */
    public function down(): void
    {
    }
};
