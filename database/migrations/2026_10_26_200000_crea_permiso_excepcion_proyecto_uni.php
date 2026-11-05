<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

// Item #863 (UNI-T A1): permiso para saltar el candado de publicación del proyecto de unificación.
// Aditivo e idempotente; no se asigna a ningún rol (lo da un admin a quien corresponda).
return new class extends Migration {
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'releases.excepcion_proyecto_uni', 'guard_name' => 'web']);
    }

    public function down(): void
    {
    }
};
