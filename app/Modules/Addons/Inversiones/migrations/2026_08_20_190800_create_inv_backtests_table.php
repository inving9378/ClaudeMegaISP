<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_backtests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_id')->constrained('inv_strategies');
            $table->foreignId('portfolio_id')->nullable()->constrained('inv_portfolios')->nullOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->date('fecha_corte_train');
            $table->decimal('capital_inicial', 18, 4);
            $table->json('parametros')->nullable();
            $table->json('resultado')->nullable();
            $table->decimal('retorno_total', 8, 4)->nullable();
            $table->decimal('retorno_anualizado', 8, 4)->nullable();
            $table->decimal('sharpe', 8, 4)->nullable();
            $table->decimal('max_drawdown', 8, 4)->nullable();
            $table->decimal('tasa_acierto', 8, 4)->nullable();
            $table->unsignedInteger('num_operaciones')->nullable();
            $table->decimal('costos_totales', 18, 4)->nullable();
            $table->decimal('retorno_out_of_sample', 8, 4)->nullable();
            $table->enum('estado', ['pendiente', 'corriendo', 'completado', 'error'])->default('pendiente');
            $table->text('mensaje_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_backtests');
    }
};
