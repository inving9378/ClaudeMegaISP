<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990792 — Expediente digital del colaborador (q4: catálogo de "documento
 * obligatorio por puesto + vigencia"). La relación N:M plantilla↔puesto YA existe
 * (`talento_puesto_document_templates`, items #870/#923) — en vez de crear una tabla
 * `talento_document_requirements` paralela (lo que proponía la opción recomendada de q4 sin ver
 * esta tabla), se agregan aquí las dos columnas que le faltaban al catálogo existente. Decisión
 * registrada en el log del item (#9990792, reporte tipo decision).
 * `obligatorio` default true = el comportamiento de hoy (toda fila en esta tabla ya es requerida
 * implícitamente); `vigencia_meses` nullable = sin vencimiento salvo que se declare uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_puesto_document_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('talento_puesto_document_templates', 'obligatorio')) {
                $table->boolean('obligatorio')->default(true)->after('template_id');
            }
            if (!Schema::hasColumn('talento_puesto_document_templates', 'vigencia_meses')) {
                $table->unsignedSmallInteger('vigencia_meses')->nullable()->after('obligatorio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talento_puesto_document_templates', function (Blueprint $table) {
            if (Schema::hasColumn('talento_puesto_document_templates', 'vigencia_meses')) {
                $table->dropColumn('vigencia_meses');
            }
            if (Schema::hasColumn('talento_puesto_document_templates', 'obligatorio')) {
                $table->dropColumn('obligatorio');
            }
        });
    }
};
