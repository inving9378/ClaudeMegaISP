<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La etapa de reactivación de los envíos se maneja desde Configuración › Finanzas › «Envíos
 * automáticos» (2026-10-22), sin tocar el .env: cerrado → etapa baja (N por corrida y M por día)
 * → normal. Aditiva sobre billing_config; nula = como hasta hoy (manda BILLING_REACTIVACION_ETAPA).
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
