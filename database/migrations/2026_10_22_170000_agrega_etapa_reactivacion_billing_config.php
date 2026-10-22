<?php

use App\Services\Finance\Billing\ReactivacionEnvios;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La etapa de reactivación de los envíos pasa a billing_config (2026-10-22) y se maneja con el
 * interruptor de Configuración › Finanzas › «Envíos automáticos», junto con la pausa general.
 * Aditiva: nivel (cerrado | prueba | normal), topes y quién/cuándo cambió el ritmo.
 *
 * Valor inicial: BILLING_REACTIVACION_ETAPA del .env, solo esta vez (ReactivacionEnvios::nivelInicial).
 * Si el resultado es «cerrado» se deja la pausa puesta: no existe «encendido pero cerrado» (en ese
 * estado no salía nada de todos modos, así que no cambia lo que se envía).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_config')) {
            return;
        }

        Schema::table('billing_config', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_config', 'reactivacion_nivel')) {
                $table->string('reactivacion_nivel', 10)->nullable()->after('envio_lote_hasta');
            }
            if (! Schema::hasColumn('billing_config', 'reactivacion_limite_corrida')) {
                $table->unsignedSmallInteger('reactivacion_limite_corrida')->nullable()->after('reactivacion_nivel');
            }
            if (! Schema::hasColumn('billing_config', 'reactivacion_limite_diario')) {
                $table->unsignedInteger('reactivacion_limite_diario')->nullable()->after('reactivacion_limite_corrida');
            }
            if (! Schema::hasColumn('billing_config', 'reactivacion_cambiada_por')) {
                $table->unsignedBigInteger('reactivacion_cambiada_por')->nullable()->after('reactivacion_limite_diario');
            }
            if (! Schema::hasColumn('billing_config', 'reactivacion_cambiada_at')) {
                $table->timestamp('reactivacion_cambiada_at')->nullable()->after('reactivacion_cambiada_por');
            }
        });

        $nivel = ReactivacionEnvios::nivelInicial(config('billing.reactivacion.etapa'));
        $limites = $nivel === ReactivacionEnvios::NIVEL_PRUEBA
            ? ['reactivacion_limite_corrida' => ReactivacionEnvios::PRUEBA_POR_CORRIDA, 'reactivacion_limite_diario' => ReactivacionEnvios::PRUEBA_POR_DIA]
            : [];
        // Solo filas sin nivel: si la migración se repite, no pisa lo que ya se eligió.
        DB::table('billing_config')->whereNull('reactivacion_nivel')->update(['reactivacion_nivel' => $nivel] + $limites);
        DB::table('billing_config')->where('reactivacion_nivel', ReactivacionEnvios::NIVEL_CERRADO)->where('envio_pausado', false)
            ->update(['envio_pausado' => true]);
    }

    public function down(): void
    {
        Schema::table('billing_config', function (Blueprint $table) {
            foreach (['reactivacion_cambiada_at', 'reactivacion_cambiada_por', 'reactivacion_limite_diario', 'reactivacion_limite_corrida', 'reactivacion_nivel'] as $columna) {
                if (Schema::hasColumn('billing_config', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
