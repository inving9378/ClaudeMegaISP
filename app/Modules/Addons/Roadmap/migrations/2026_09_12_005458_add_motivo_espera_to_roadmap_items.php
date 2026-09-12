<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CIRC-03 Fase A — columna aditiva para registrar POR QUÉ un item requiere_irving está esperando,
 * sin tocar todavía scopeBandeja() ni la UI (eso es la Fase C, item aparte).
 *
 * Valores válidos (string libre, no ENUM de MySQL — mismo patrón que `frontera_valvula`, más fácil
 * de migrar/alterar después que un enum real):
 *   decision | credencial | hardware | sesion_presencial | autorizacion | frontera_produccion
 * NULL = no espera nada / no aplica (default, y el único valor de los items ya existentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'motivo_espera')) {
                $table->string('motivo_espera', 30)->nullable()->after('frontera_valvula_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'motivo_espera')) {
                $table->dropColumn('motivo_espera');
            }
        });
    }
};
