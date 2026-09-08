<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1a del puente Vendedores→Talento (item #9990609, sub-item de #9990605).
 *
 * Tabla NUEVA y aislada (decisión Irving #9990605/q2, opción 1): las comisiones espejo
 * NO se escriben en talento_ledger_entries porque LiquidationService::calculate() recoge
 * automáticamente cualquier concept ahí y lo sumaría al grossPay real -- violaría el
 * objetivo del item padre de "sin tocar el pago vivo" en esta fase. Es puramente inerte
 * hasta que la Fase 1b la use.
 *
 * Ledger append-only (sin updated_at/soft-deletes), igual que talento_ledger_entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_comisiones_espejo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('seller_id');
            // FK lógica a payment_by_rule.id -- SIN constraint dura a propósito, para no
            // acoplar el módulo Talento con Vendedores (regla de servicios compartidos únicos).
            $table->unsignedBigInteger('payment_by_rule_id');
            // FK lógica a payment_by_rule_commissions.id -- nullable (una comisión puede no
            // tener el detalle de línea, o el detalle pudo borrarse en Vendedores).
            $table->unsignedBigInteger('payment_by_rule_details_id')->nullable();
            $table->decimal('amount', 12, 2);
            // Ventana PayWeek ya resuelta (Sáb 18:00 -> Sáb 18:00 / legacy), la que usará 1b
            // para que LiquidationService la ubique en la semana de pago correcta.
            $table->date('period_start');
            $table->date('period_end');
            // Ventana ORIGINAL Domingo-Sábado de CalculateBalanceSellerService, conservada para
            // auditoría del riesgo de desalineación de calendarios (análisis #9990453 sección 4.1).
            $table->date('source_period_start');
            $table->date('source_period_end');
            $table->timestamp('created_at')->nullable();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores');
            $table->index('user_id');
            $table->index('seller_id');
            $table->index('payment_by_rule_id');
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_comisiones_espejo');
    }
};
