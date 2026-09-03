<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #923. `talento_puesto_document_templates.puesto` (string libre, creada por #870)
 * pasa a `puesto_id` con FK al catálogo `talento_puestos` — es justo lo que da el renombrado sin
 * huérfanos. Verificado ANTES de escribir esta migración: la tabla está VACÍA (0 filas, nadie
 * pudo asignar nada porque no había puestos) → migrar el esquema es barato, sin filas que perder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('talento_puesto_document_templates', 'puesto')) {
            $count = DB::table('talento_puesto_document_templates')->count();
            if ($count > 0) {
                throw new \RuntimeException(
                    "talento_puesto_document_templates tiene {$count} fila(s) — se esperaba 0. " .
                    'Abortando para no perder datos; requiere revisión manual antes de migrar el esquema.'
                );
            }

            Schema::table('talento_puesto_document_templates', function (Blueprint $table) {
                $table->dropUnique('tpdt_puesto_template_unique');
                $table->dropIndex(['puesto']);
                $table->dropColumn('puesto');
            });
        }

        if (!Schema::hasColumn('talento_puesto_document_templates', 'puesto_id')) {
            Schema::table('talento_puesto_document_templates', function (Blueprint $table) {
                $table->unsignedBigInteger('puesto_id')->after('id');
                $table->foreign('puesto_id')->references('id')->on('talento_puestos')->onDelete('cascade');
                $table->unique(['puesto_id', 'template_id'], 'tpdt_puesto_template_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('talento_puesto_document_templates', 'puesto_id')) {
            Schema::table('talento_puesto_document_templates', function (Blueprint $table) {
                $table->dropForeign(['puesto_id']);
                $table->dropUnique('tpdt_puesto_template_unique');
                $table->dropColumn('puesto_id');
            });
        }

        if (!Schema::hasColumn('talento_puesto_document_templates', 'puesto')) {
            Schema::table('talento_puesto_document_templates', function (Blueprint $table) {
                $table->string('puesto', 100)->after('id');
                $table->unique(['puesto', 'template_id'], 'tpdt_puesto_template_unique');
                $table->index('puesto');
            });
        }
    }
};
