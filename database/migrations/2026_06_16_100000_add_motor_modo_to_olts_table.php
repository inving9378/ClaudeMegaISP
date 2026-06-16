<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B4-W2-3: Campo motor_modo en la tabla olts.
 *
 * 'smartolt' (default) — operaciones write delegadas a la API SmartOLT.
 * 'propio'             — operaciones write van por HuaweiDriver (SSH/Telnet directo).
 *
 * En W2 este campo es puramente informativo (badge en UI).
 * La transición funcional smartolt → propio ocurre en W3, OLT por OLT,
 * solo después de validación supervisada con Irving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->enum('motor_modo', ['smartolt', 'propio'])
                  ->default('smartolt')
                  ->after('driver')
                  ->comment('Motor de escritura activo para esta OLT (informativo en W2)');
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->dropColumn('motor_modo');
        });
    }
};
