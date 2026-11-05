<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

// Item #919 (UNI-T D5b): permiso de excepción para cerrar una orden desde administración sin sus evidencias
// (exige motivo). Aditivo e idempotente; no se asigna a ningún rol: se otorga a mano, a quien corresponda.
return new class extends Migration {
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.ordenes.cierre_excepcion', 'guard_name' => 'web']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
    }
};
