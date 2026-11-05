<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permiso propio para registrar pagos de comisión y cobrar deuda de un colaborador (UNI B3a).
 * Hasta hoy bastaba `talento.comisiones.view` porque la ruta comparte path con `.manage`.
 *
 * Lo reciben los roles y cuentas que hoy tienen `talento.comisiones.manage`, así quien hoy
 * lo hace legítimamente lo conserva. Solo agrega; idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $nuevo  = Permission::firstOrCreate(['name' => 'talento.comisiones.registrar_pago', 'guard_name' => 'web']);
        $manage = Permission::where('name', 'talento.comisiones.manage')->where('guard_name', 'web')->first();

        if ($manage) {
            foreach ($manage->roles as $role) {
                if (! $role->hasPermissionTo($nuevo)) {
                    $role->givePermissionTo($nuevo);
                    Log::info("Talento: talento.comisiones.registrar_pago dado al rol {$role->name}");
                }
            }

            $userIds = DB::table('model_has_permissions')
                ->where('permission_id', $manage->id)
                ->where('model_type', \App\Models\User::class)
                ->pluck('model_id');
            foreach (\App\Models\User::whereIn('id', $userIds)->get() as $user) {
                if (! $user->hasDirectPermission($nuevo)) {
                    $user->givePermissionTo($nuevo);
                    Log::info("Talento: talento.comisiones.registrar_pago dado a la cuenta {$user->login_user}");
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** No se revierte: quitarlo dejaría sin poder pagar a quien hoy puede. */
    public function down(): void
    {
    }
};
