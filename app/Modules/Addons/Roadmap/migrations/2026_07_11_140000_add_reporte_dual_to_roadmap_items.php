<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reporte dual técnico/coloquial por rama (#328, extiende #325). ADITIVA / reversible.
 * `reporte_tecnico`: para auditoría (términos de código, archivos, verificación).
 * `reporte_coloquial`: lenguaje llano, lo que lee el botón de voz por defecto.
 * Ambos nullable: las ramas viejas caen a `comentarios_claude` (fallback en el controller).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'reporte_tecnico')) {
                $table->text('reporte_tecnico')->nullable()->after('comentarios_claude');
            }
            if (! Schema::hasColumn('roadmap_items', 'reporte_coloquial')) {
                $table->text('reporte_coloquial')->nullable()->after('reporte_tecnico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'reporte_coloquial')) {
                $table->dropColumn('reporte_coloquial');
            }
            if (Schema::hasColumn('roadmap_items', 'reporte_tecnico')) {
                $table->dropColumn('reporte_tecnico');
            }
        });
    }
};
