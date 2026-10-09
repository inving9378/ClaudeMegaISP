<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesión 2026-10-09 (decisión de Irving) — algunos clientes son WiFi (servicio
 * inalámbrico, sin ONU/OLT) y otros Fibra; cada tipo tiene su propio formato de
 * nomenclatura real. Columna aditiva, nullable (sin clasificar por default — no
 * se adivina para los ~2,119 clientes que ya tenían box_nomenclator poblado
 * antes de esta sesión).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('client_additional_information', 'tipo_nomenclatura')) {
            Schema::table('client_additional_information', function (Blueprint $table) {
                $table->enum('tipo_nomenclatura', ['wifi', 'fibra'])->nullable()->after('box_nomenclator');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_additional_information', 'tipo_nomenclatura')) {
            Schema::table('client_additional_information', function (Blueprint $table) {
                $table->dropColumn('tipo_nomenclatura');
            });
        }
    }
};
