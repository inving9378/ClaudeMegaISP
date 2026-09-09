<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990647 ("Completar documento"). Dos columnas aditivas nullable:
 * - `talento_document_templates.fillable_fields` (JSON): catálogo declarativo por plantilla de
 *   los campos que el formulario "Completar documento" debe mostrar — [{key,label,group,type}],
 *   group = 'doc' (dato propio de ESTE documento, se guarda en datos_extra) | 'estandar' (dato
 *   del colaborador, ya cubierto por empleado.* / empresa.* — reservado para cuando algún template
 *   lo necesite; ninguno lo usa todavía).
 * - `talento_employee_documents.datos_extra` (JSON): valores capturados de los campos group=doc
 *   de ESE documento puntual (p.ej. nombres/cargos de la Comisión Mixta, lugar y fecha). Se
 *   inyecta como `doc.*` en TemplateRenderService junto a empleado/empresa/fecha (ver
 *   EmployeeDocumentPackageService::buildData). Vacío por default: documentos existentes
 *   renderizan igual que hoy (retrocompatible).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_document_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('talento_document_templates', 'fillable_fields')) {
                $table->json('fillable_fields')->nullable()->after('requires_signature');
            }
        });

        Schema::table('talento_employee_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('talento_employee_documents', 'datos_extra')) {
                $table->json('datos_extra')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talento_document_templates', function (Blueprint $table) {
            if (Schema::hasColumn('talento_document_templates', 'fillable_fields')) {
                $table->dropColumn('fillable_fields');
            }
        });

        Schema::table('talento_employee_documents', function (Blueprint $table) {
            if (Schema::hasColumn('talento_employee_documents', 'datos_extra')) {
                $table->dropColumn('datos_extra');
            }
        });
    }
};
