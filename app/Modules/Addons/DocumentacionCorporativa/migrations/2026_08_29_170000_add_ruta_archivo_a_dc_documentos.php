<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2a (item roadmap #767, backend recuperado del item #734). Guarda la
 * ruta relativa COMPLETA del archivo en disco (`ruta_archivo`) en el momento
 * de subirlo, en vez de reconstruirla en el momento de descargar a partir de
 * `apartado.clave` + `concepto.slug`: esos dos campos son editables
 * (`documentacion-corporativa.concepto.manage`), y reconstruir una ruta desde
 * un dato mutable rompería la descarga de versiones viejas el día que alguien
 * renombre el concepto o lo mueva de apartado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dc_documentos', 'ruta_archivo')) {
            Schema::table('dc_documentos', function (Blueprint $table) {
                $table->string('ruta_archivo')->nullable()->after('archivo_uuid');
            });
        }

        if (! Schema::hasColumn('dc_documento_versiones', 'ruta_archivo')) {
            Schema::table('dc_documento_versiones', function (Blueprint $table) {
                $table->string('ruta_archivo')->nullable()->after('archivo_uuid');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dc_documento_versiones', 'ruta_archivo')) {
            Schema::table('dc_documento_versiones', function (Blueprint $table) {
                $table->dropColumn('ruta_archivo');
            });
        }

        if (Schema::hasColumn('dc_documentos', 'ruta_archivo')) {
            Schema::table('dc_documentos', function (Blueprint $table) {
                $table->dropColumn('ruta_archivo');
            });
        }
    }
};
