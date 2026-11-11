<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Item #1200 Fase C: permiso de la pantalla «Plantillas de orden» (tipos y plantillas de planta
 * externa). Aditiva (givePermissionTo, nunca sync): solo super-administrator y DESARROLLADOR; los
 * demás roles no lo reciben.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'talento.work_order_templates.manage', 'guard_name' => 'web']);
        Role::whereIn('name', ['super-administrator', 'DESARROLLADOR'])->get()->each(fn ($r) => $r->givePermissionTo($perm));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $perm = Permission::where('name', 'talento.work_order_templates.manage')->where('guard_name', 'web')->first();
        if ($perm) {
            Role::whereIn('name', ['super-administrator', 'DESARROLLADOR'])->get()->each(fn ($r) => $r->revokePermissionTo($perm));
            $perm->delete();
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
