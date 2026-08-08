<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de origen/revisión del OCR en el documento (item #580, Fase 7). Todo ADITIVO y con
 * defaults seguros: un documento capturado a mano queda `no_ejecutado` + `needs_review=false`,
 * exactamente como se comportaba antes de este item.
 *
 * `ocr_needs_review` es el "revisar manualmente" del criterio de aceptación: se prende cuando la
 * IA falló o cuando la fecha de vencimiento no se leyó con la confianza mínima. NUNCA bloquea el
 * guardado — solo avisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_documents', function (Blueprint $t) {
            if (! Schema::hasColumn('fleet_documents', 'ocr_status')) {
                $t->enum('ocr_status', ['no_ejecutado', 'ok', 'baja_confianza', 'fallido'])
                    ->default('no_ejecutado')->after('file_path');
            }
            if (! Schema::hasColumn('fleet_documents', 'ocr_needs_review')) {
                $t->boolean('ocr_needs_review')->default(false)->after('ocr_status');
            }
            if (! Schema::hasColumn('fleet_documents', 'ocr_fields')) {
                $t->json('ocr_fields')->nullable()->after('ocr_needs_review');
            }
            if (! Schema::hasColumn('fleet_documents', 'ocr_ran_at')) {
                $t->timestamp('ocr_ran_at')->nullable()->after('ocr_fields');
            }
            if (! Schema::hasColumn('fleet_documents', 'ocr_reviewed_at')) {
                $t->timestamp('ocr_reviewed_at')->nullable()->after('ocr_ran_at');
            }
            if (! Schema::hasColumn('fleet_documents', 'ocr_reviewed_by')) {
                $t->unsignedBigInteger('ocr_reviewed_by')->nullable()->after('ocr_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fleet_documents', function (Blueprint $t) {
            foreach (['ocr_status', 'ocr_needs_review', 'ocr_fields', 'ocr_ran_at', 'ocr_reviewed_at', 'ocr_reviewed_by'] as $col) {
                if (Schema::hasColumn('fleet_documents', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
