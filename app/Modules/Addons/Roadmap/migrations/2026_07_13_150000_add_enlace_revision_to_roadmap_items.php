<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #432 ADENDA B — `enlace_revision`: deep-link al lugar REAL de la UI donde se ve/prueba el cambio
 * (ej. /releases → Agregar item), NO /roadmap/item/N. "Ver" (Torre/Integración) lo usa; "Escuchar"
 * sigue leyendo `reporte_coloquial`. Aditiva + idempotente; down() la elimina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'enlace_revision')) {
                // Sin ->after(): el orden fisico de columnas es cosmetico en MySQL y
                // atarlo a `reporte_coloquial` hacia que esta migracion muriera con
                // 1054 en cualquier entorno que aun no tuviera esa columna.
                $table->string('enlace_revision')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'enlace_revision')) {
                $table->dropColumn('enlace_revision');
            }
        });
    }
};
