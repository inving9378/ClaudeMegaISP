<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 del dashboard por rol (2026-10-13) — hasta hoy
 * talento_weekly_rules solo podía expresar cuotas de VENDEDOR
 * (min_prospects/min_sales/bonus_*_threshold son vocabulario de ventas,
 * y TalentoWeeklyRuleEvaluationService las llenaba SIEMPRE desde
 * CalculateBalanceSellerService::getProspects()/getSales(), sin
 * excepción de rol). Un técnico o un mostrador evaluados contra esa
 * fuente siempre darían 0/0 — la regla nunca podía reflejar su trabajo
 * real.
 *
 * metric_type decide qué cuenta el servicio como "unidades" para esa
 * regla: 'ventas' (default, comportamiento idéntico al de siempre),
 * 'tecnico' (OTs completadas/validadas en la semana) o 'mostrador'
 * (pagos capturados en la semana). Se REUSAN min_sales/
 * bonus_sales_threshold como el piso/techo de "unidades" cuando
 * metric_type != 'ventas' — min_prospects/bonus_prospects_threshold
 * simplemente no aplican ahí (se dejan null). Evita duplicar columnas
 * para el mismo concepto piso/techo/bono que ya existen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_weekly_rules', function (Blueprint $table) {
            $table->enum('metric_type', ['ventas', 'tecnico', 'mostrador'])
                ->default('ventas')
                ->after('roles');
        });
    }

    public function down(): void
    {
        Schema::table('talento_weekly_rules', function (Blueprint $table) {
            $table->dropColumn('metric_type');
        });
    }
};
