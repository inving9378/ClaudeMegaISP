<?php

use App\Services\Finance\Billing\InterruptorProformaAnticipada;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #581 (2026-10-22): el interruptor de la proforma anticipada pasa a billing_config y se maneja desde
 * Configuración › Finanzas › «Envíos automáticos». Aditiva: el interruptor, quién/cuándo lo cambió y
 * quién/cuándo cambió los días de anticipo (billing_config.envio_prefactura_dias, que ya existe).
 *
 * Valor inicial: BILLING_PROFORMA_ANTICIPADA del .env, solo esta vez. Como es el mismo valor que ya
 * se usaba, al aplicar la migración no cambia cuándo se generan las proformas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_config')) {
            return;
        }

        Schema::table('billing_config', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_config', 'proforma_anticipada')) {
                $table->boolean('proforma_anticipada')->nullable()->after('reactivacion_cambiada_at');
            }
            if (! Schema::hasColumn('billing_config', 'proforma_anticipada_cambiada_por')) {
                $table->unsignedBigInteger('proforma_anticipada_cambiada_por')->nullable()->after('proforma_anticipada');
            }
            if (! Schema::hasColumn('billing_config', 'proforma_anticipada_cambiada_at')) {
                $table->timestamp('proforma_anticipada_cambiada_at')->nullable()->after('proforma_anticipada_cambiada_por');
            }
            if (! Schema::hasColumn('billing_config', 'envio_prefactura_dias_cambiado_por')) {
                $table->unsignedBigInteger('envio_prefactura_dias_cambiado_por')->nullable()->after('proforma_anticipada_cambiada_at');
            }
            if (! Schema::hasColumn('billing_config', 'envio_prefactura_dias_cambiado_at')) {
                $table->timestamp('envio_prefactura_dias_cambiado_at')->nullable()->after('envio_prefactura_dias_cambiado_por');
            }
        });

        // Solo filas sin valor: si la migración se repite, no pisa lo que ya se eligió en la pantalla.
        DB::table('billing_config')->whereNull('proforma_anticipada')
            ->update(['proforma_anticipada' => InterruptorProformaAnticipada::valorInicial(config('billing.proforma_anticipada'))]);
    }

    public function down(): void
    {
        Schema::table('billing_config', function (Blueprint $table) {
            foreach (['envio_prefactura_dias_cambiado_at', 'envio_prefactura_dias_cambiado_por', 'proforma_anticipada_cambiada_at',
                'proforma_anticipada_cambiada_por', 'proforma_anticipada'] as $columna) {
                if (Schema::hasColumn('billing_config', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
