<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-06/FR-07 (#540/#541, decisión de Irving 2026-10-17): «sin cargos retroactivos: las reglas por
 * estado y de contrato aplican solo desde la fecha de despliegue (guardar esa fecha en
 * configuración)». Aditiva: billing_config.reglas_facturacion_desde = el momento en que corre esta
 * migración (el despliegue). Si ya tiene valor, no se cambia. No toca envio_pausado ni nada más.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('billing_config', 'reglas_facturacion_desde')) {
            Schema::table('billing_config', function (Blueprint $table) {
                $table->dateTime('reglas_facturacion_desde')->nullable();
            });
        }
        DB::table('billing_config')->whereNull('reglas_facturacion_desde')->update(['reglas_facturacion_desde' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('billing_config', 'reglas_facturacion_desde')) {
            Schema::table('billing_config', function (Blueprint $table) {
                $table->dropColumn('reglas_facturacion_desde');
            });
        }
    }
};
