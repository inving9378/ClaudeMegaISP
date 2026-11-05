<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * B3d (Proyecto-UNI #870): separa registrar de autorizar un préstamo. Aditiva: crea los permisos y
 * los da a los roles que hoy tienen 'talento.loans.manage' (conservan la acción). La excepción
 * (autorizar lo propio, con motivo) solo la recibe super-administrator. Sin efecto hasta que se
 * encienda UNIFICACION_PRESTAMOS_AUTORIZAR_B3D.
 */
return new class extends Migration
{
    public function up(): void
    {
        $autorizar = Permission::firstOrCreate(['name' => 'talento.prestamos.autorizar', 'guard_name' => 'web']);
        $propio    = Permission::firstOrCreate(['name' => 'talento.prestamos.autorizar_propio', 'guard_name' => 'web']);

        $manage = Permission::where('name', 'talento.loans.manage')->where('guard_name', 'web')->first();
        if ($manage) {
            $ids = DB::table('role_has_permissions')->where('permission_id', $manage->id)->pluck('role_id');
            Role::whereIn('id', $ids)->get()->each(fn ($r) => $r->givePermissionTo($autorizar));
        }
        Role::where('name', 'super-administrator')->get()->each(fn ($r) => $r->givePermissionTo($propio));

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Aditiva a propósito: no se borran permisos ya asignados.
    }
};
