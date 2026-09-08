<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990618 — firma de documentos de empleado/vendedor (fase 1: backend).
 * Cada plantilla puede marcarse como "firma obligatoria" (opt-in, default false — no cambia
 * el comportamiento de ninguna plantilla existente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_document_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('talento_document_templates', 'requires_signature')) {
                $table->boolean('requires_signature')->default(false)->after('active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talento_document_templates', function (Blueprint $table) {
            if (Schema::hasColumn('talento_document_templates', 'requires_signature')) {
                $table->dropColumn('requires_signature');
            }
        });
    }
};
