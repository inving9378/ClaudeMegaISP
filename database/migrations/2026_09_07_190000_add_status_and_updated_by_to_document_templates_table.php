<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 de #9990572: `document_templates` no distinguía borrador/publicada ni quién editó
 * por última vez (solo tenía `created_by`, string — ver migración archivada
 * 2024_07_23_150303_create_document_templates_table.php). `updated_by` se crea con el MISMO
 * tipo que `created_by` (string), no un id/FK.
 *
 * Backfill: las plantillas que ya existen hoy generan contratos reales en producción vía
 * DocumentTemplateService, así que se marcan 'publicada' (tratarlas como borrador las
 * escondería del avance de la Fase 2). Las que se creen después de esta migración caen en
 * el default 'borrador' hasta que alguien las publique desde el form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->enum('status', ['borrador', 'publicada'])->default('borrador')->after('type');
            $table->string('updated_by')->nullable()->after('created_by');
        });

        DB::table('document_templates')
            ->whereNull('deleted_at')
            ->update(['status' => 'publicada']);
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn(['status', 'updated_by']);
        });
    }
};
