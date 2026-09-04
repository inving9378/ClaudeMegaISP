<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #923. `talento_puesto_document_templates.puesto` (string libre, creada por #870)
 * gana `puesto_id` con FK al catálogo `talento_puestos` — es justo lo que da el renombrado sin
 * huérfanos. Verificado ANTES de escribir esta migración: la tabla está VACÍA (0 filas) — pero
 * el guardrail de migraciones (item #1018) bloquea `dropColumn`/`change()` sin declarar
 * `contraccion_de:` madura en prod, así que aquí NO se toca `puesto` (patrón en dos tiempos que
 * el propio guardrail sugiere): se agrega `puesto_id` y la app pasa a escribir/leer por ahí
 * (dual-write, `puesto` queda como snapshot legible); retirar la columna `puesto` es una
 * contracción posterior, cuando esta versión ya lleve el mínimo de días aplicada en prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('talento_puesto_document_templates', 'puesto_id')) {
            Schema::table('talento_puesto_document_templates', function (Blueprint $table) {
                $table->unsignedBigInteger('puesto_id')->nullable()->after('puesto');
                $table->foreign('puesto_id')->references('id')->on('talento_puestos')->onDelete('cascade');
                $table->unique(['puesto_id', 'template_id'], 'tpdt_puesto_id_template_unique');
                $table->index('puesto_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('talento_puesto_document_templates', 'puesto_id')) {
            Schema::table('talento_puesto_document_templates', function (Blueprint $table) {
                $table->dropForeign(['puesto_id']);
                $table->dropUnique('tpdt_puesto_id_template_unique');
                $table->dropIndex(['puesto_id']);
                $table->dropColumn('puesto_id');
            });
        }
    }
};
