<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990806 (sub-item de #9990792). Distingue version "mayor" (cambio de contenido
 * sustantivo -> reabre acuses ya firmados, ver AcuseReopeningService) de "menor" (typo/formato
 * -> no reabre nada). Decision q1 de Irving (opcion recomendada): reapertura SELECTIVA por tipo
 * de cambio, marcado por quien publica la version. Default 'mayor' = comportamiento seguro
 * (reabre) cuando nadie especifica lo contrario -- coincide con la descripcion original del
 * item ("toda version nueva de un acuse reabre"); quien sepa que es un cambio cosmetico pasa
 * 'menor' explicito a TemplateVersionService::createVersion(). Aditiva, sin UI de publicacion
 * todavia (no existe -- ver comentarios_claude del item): el parametro queda listo para cuando
 * se construya esa pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('talento_document_template_versions', 'tipo_cambio')) {
            Schema::table('talento_document_template_versions', function (Blueprint $table) {
                $table->enum('tipo_cambio', ['mayor', 'menor'])->default('mayor')->after('change_note');
            });
        }
    }

    public function down(): void
    {
        Schema::table('talento_document_template_versions', function (Blueprint $table) {
            if (Schema::hasColumn('talento_document_template_versions', 'tipo_cambio')) {
                $table->dropColumn('tipo_cambio');
            }
        });
    }
};
