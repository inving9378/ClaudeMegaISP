<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reforma de permisos B3.1 — columna `context` en permissions.
 *
 * Fuente única para separar los permisos del Panel administrador (MegaISP) de los
 * del Portal colaborador (Talento/app) en la pantalla de rol a dos columnas.
 * Additive: default 'panel' = comportamiento actual (todo cuenta como panel hasta
 * que la clasificación curada B3.2 marque los 'portal').
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('permissions', 'context')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('context', 20)->default('panel')->after('guard_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('permissions', 'context')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropColumn('context');
            });
        }
    }
};
