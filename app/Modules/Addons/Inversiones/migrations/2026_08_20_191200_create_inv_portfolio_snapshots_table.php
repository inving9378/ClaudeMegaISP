<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foto diaria: fuente de verdad para evaluar límites de periodo (nunca recalcular sobre la marcha).
        Schema::create('inv_portfolio_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained('inv_portfolios')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('valor_total', 18, 4);
            $table->decimal('efectivo', 18, 4);
            $table->decimal('valor_posiciones', 18, 4);
            $table->decimal('pnl_dia', 18, 4)->nullable();
            $table->decimal('pnl_dia_pct', 8, 4)->nullable();
            $table->decimal('pnl_acumulado', 18, 4)->nullable();
            $table->decimal('pnl_acumulado_pct', 8, 4)->nullable();
            $table->decimal('drawdown_actual', 8, 4)->nullable();
            $table->timestamps();

            $table->unique(['portfolio_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_portfolio_snapshots');
    }
};
