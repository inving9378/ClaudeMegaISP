<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * UNI-T J4 F2a (Proyecto-UNI #901): permiso propio para confirmar el pago de una liquidación cerrada.
 * Aditiva (givePermissionTo, nunca sync): solo super-administrator y DESARROLLADOR. Sin efecto hasta
 * encender unificacion.interruptores.liquidacion_estados_j4.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'talento.liquidation.pagar', 'guard_name' => 'web']);
        Role::whereIn('name', ['super-administrator', 'DESARROLLADOR'])->get()->each(fn ($r) => $r->givePermissionTo($perm));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Aditiva a propósito: no se borran permisos ya asignados.
    }
};
