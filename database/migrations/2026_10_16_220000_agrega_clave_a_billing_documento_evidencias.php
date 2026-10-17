<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAC-09 (item #516): llave de idempotencia de un envío. El sha256 del PDF no sirve para eso
 * (dompdf incrusta la fecha de creación: cada generación da bytes distintos), así que el canal
 * guarda aquí una huella del documento lógico (factura, documento y su estado). Aditiva, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_documento_evidencias') && ! Schema::hasColumn('billing_documento_evidencias', 'clave')) {
            Schema::table('billing_documento_evidencias', function (Blueprint $table) {
                $table->string('clave', 64)->nullable()->after('canal')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('billing_documento_evidencias', 'clave')) {
            Schema::table('billing_documento_evidencias', function (Blueprint $table) {
                $table->dropIndex(['clave']);
                $table->dropColumn('clave');
            });
        }
    }
};
