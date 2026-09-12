<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice faltante de `motivo_espera` (item #9990926, sub-item de #9990910).
 *
 * La columna `motivo_espera` ya existe (migración
 * `2026_09_12_005458_add_motivo_espera_to_roadmap_items.php`, mergeada por #9990904/#9990914)
 * pero se creó SIN índice — el spec original ("columna + index") pedía ambos, mismo patrón que
 * `frontera_valvula` (que sí tiene su índice). Se agrega aquí como migración aditiva aparte en
 * vez de editar una migración ya corrida.
 *
 * Valores válidos (string libre, no ENUM de MySQL — mismo patrón que `frontera_valvula`, más
 * fácil de ampliar después que un enum real):
 *   decision | credencial | hardware | sesion_presencial | autorizacion | frontera_produccion
 * NULL = no espera nada / no aplica (default, y el único valor de los items ya existentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'motivo_espera') && ! $this->tieneIndice()) {
                $table->index('motivo_espera');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if ($this->tieneIndice()) {
                $table->dropIndex(['motivo_espera']);
            }
        });
    }

    private function tieneIndice(): bool
    {
        $rows = \Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM roadmap_items WHERE Column_name = 'motivo_espera'"
        );

        return count($rows) > 0;
    }
};
