<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas de desempeño semanal por combinación EXACTA de roles (item:
 * "reglas de comisiones/penalizaciones estilo las de penalizaciones").
 *
 * Una regla define, para un conjunto de roles (ej. ["Mostrador"] o
 * ["Mostrador","Vendedor"] como combinación propia, distinta de cada rol
 * solo), el piso mínimo semanal de prospectos/ventas y qué pasa si no se
 * alcanza (penalización, del catálogo ya existente) o si se supera con
 * margen (bono).
 *
 * `talento_weekly_rule_evaluations` es el resultado real de correr la
 * regla contra un colaborador en una semana — auditoría + idempotencia
 * (UNIQUE colaborador+semana, mismo patrón que talento_liquidations).
 *
 * La penalización NUNCA se aplica sola: queda `penalty_pending` hasta que
 * un supervisor humano la confirma (reusa PenaltyService::apply(), mismo
 * guard de autorización y mismo ledger que ya existen — ver
 * TalentoReglaDesempenoController::confirmarPenalizacion()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_weekly_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            // Combinación exacta de roles Spatie que define el alcance de
            // esta regla (array normalizado/ordenado antes de guardar).
            $table->json('roles');
            $table->unsignedInteger('min_prospects')->nullable();
            $table->unsignedInteger('min_sales')->nullable();
            $table->foreignId('penalty_type_id')->nullable()
                ->constrained('talento_penalty_types')->nullOnDelete();
            $table->unsignedInteger('bonus_prospects_threshold')->nullable();
            $table->unsignedInteger('bonus_sales_threshold')->nullable();
            $table->decimal('bonus_amount', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('talento_weekly_rule_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('talento_colaboradores')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('talento_weekly_rules')->nullOnDelete();
            $table->date('week_start');
            $table->date('week_end');
            $table->unsignedInteger('prospects_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->enum('outcome', ['penalty_pending', 'penalty_applied', 'bonus', 'meets_quota', 'no_rule']);
            // Snapshot de lo que la regla sugiere, mientras nadie confirma.
            $table->unsignedBigInteger('penalty_type_id')->nullable();
            $table->decimal('penalty_amount', 10, 2)->nullable();
            // Solo se rellena cuando un supervisor confirma de verdad.
            $table->foreignId('penalty_id')->nullable()->constrained('talento_penalties')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('talento_colaboradores')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('bonus_amount_applied', 10, 2)->nullable();
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique(['colaborador_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_weekly_rule_evaluations');
        Schema::dropIfExists('talento_weekly_rules');
    }
};
