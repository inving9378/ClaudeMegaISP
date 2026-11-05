<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * UNI-T D4c (Proyecto-UNI #917): permiso de la excepción administrativa a «nadie valida lo propio».
 * Aditiva y sin asignar a ningún rol: Irving lo reparte después (los super-administrator lo pasan por Gate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.excepcion_administrativa', 'guard_name' => 'web']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Aditiva a propósito.
    }
};
