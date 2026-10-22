<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración › Finanzas › «Envíos automáticos» (2026-10-22). Aditiva sobre billing_config:
 * - quién y cuándo cambió la pausa general (envio_pausado), para mostrarlo en la pantalla;
 * - el ritmo de «enviar en lotes por hora» al reactivar: cuántos por hora y hasta qué instante
 *   cuenta como acumulado (lo creado antes de reactivar). Sin ritmo = como siempre.
 * No cambia el valor de envio_pausado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_config')) {
            return;
        }

        Schema::table('billing_config', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_config', 'envio_pausado_cambiado_por')) {
                $table->unsignedBigInteger('envio_pausado_cambiado_por')->nullable()->after('envio_pausado');
            }
            if (! Schema::hasColumn('billing_config', 'envio_pausado_cambiado_at')) {
                $table->timestamp('envio_pausado_cambiado_at')->nullable()->after('envio_pausado_cambiado_por');
            }
            if (! Schema::hasColumn('billing_config', 'envio_lote_por_hora')) {
                $table->unsignedSmallInteger('envio_lote_por_hora')->nullable()->after('envio_pausado_cambiado_at');
            }
            if (! Schema::hasColumn('billing_config', 'envio_lote_hasta')) {
                $table->timestamp('envio_lote_hasta')->nullable()->after('envio_lote_por_hora');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_config', function (Blueprint $table) {
            foreach (['envio_lote_hasta', 'envio_lote_por_hora', 'envio_pausado_cambiado_at', 'envio_pausado_cambiado_por'] as $columna) {
                if (Schema::hasColumn('billing_config', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
