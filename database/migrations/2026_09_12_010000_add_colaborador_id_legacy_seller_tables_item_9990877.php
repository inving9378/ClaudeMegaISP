<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 continuación de #9990877 (Identidad unificada) — puente aditivo `colaborador_id` en
 * tablas legado donde `seller_id` SÍ es `sellers.id` (a diferencia de #9990778, donde `seller_id`
 * resultó ser `users.id`). Aquí la resolución es doble join: `sellers.id` → `sellers.user_id` →
 * `talento_colaboradores.user_id`.
 *
 * Alcance de ESTA fase (q2 aprobada por Irving, 2026-09-11): solo las tablas de "impacto directo"
 * (contratos/activaciones/comisiones-lectura/pagos), activamente consumidas por controllers reales
 * (ComissionController, PaymentSellerController, SellerTransactionController, DiscountPayment,
 * CommissionRule). Quedan FUERA de esta fase (deferidas a #9990877 sub-item de "fase siguiente"):
 * - `history_sellers_rules`: histórico/auditoría por nombre, no impacto directo.
 * - `sales`: descontinuada (0 filas, 0 consumidores reales de código —
 *   docs/vendedores-sales-commissions-prospects-item-9990471-verificacion.md).
 *
 * `seller_id` NO se toca — coexiste con `colaborador_id` hasta una fase de corte futura.
 * Reversible: down() limpio.
 */
return new class extends Migration
{
    /** @var array<int,string> */
    private array $tablas = [
        'commissions',
        'commissions_rules_sellers',
        'discounts',
        'payment_by_rule',
        'payments_sellers',
        'transactions_sellers',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if (!Schema::hasColumn($tabla, 'colaborador_id')) {
                    $table->unsignedBigInteger('colaborador_id')->nullable()->after('seller_id');
                    $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
                    $table->index('colaborador_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if (Schema::hasColumn($tabla, 'colaborador_id')) {
                    $table->dropForeign(['colaborador_id']);
                    $table->dropColumn('colaborador_id');
                }
            });
        }
    }
};
