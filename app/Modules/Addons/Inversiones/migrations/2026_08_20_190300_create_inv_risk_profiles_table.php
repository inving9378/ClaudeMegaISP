<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->unique()->constrained('inv_portfolios')->cascadeOnDelete();
            $table->tinyInteger('nivel');
            $table->boolean('personalizado')->default(false);
            $table->decimal('pct_capital_por_operacion', 8, 4);
            $table->smallInteger('max_posiciones_simultaneas');
            $table->decimal('pct_max_por_activo', 8, 4);
            $table->decimal('pct_efectivo_minimo', 8, 4);
            $table->decimal('stop_loss_por_operacion', 8, 4);
            $table->decimal('take_profit_por_operacion', 8, 4)->nullable();
            $table->decimal('perdida_max_diaria', 8, 4);
            $table->decimal('perdida_max_semanal', 8, 4);
            $table->decimal('perdida_max_mensual', 8, 4);
            $table->decimal('ganancia_max_diaria', 8, 4)->nullable();
            $table->decimal('ganancia_max_semanal', 8, 4)->nullable();
            $table->decimal('ganancia_max_mensual', 8, 4)->nullable();
            $table->decimal('fraccion_kelly', 6, 4);
            $table->decimal('apalancamiento_max', 6, 4)->default(1.0000);
            $table->decimal('drawdown_max_portafolio', 8, 4);
            $table->json('universo_permitido');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_risk_profiles');
    }
};
