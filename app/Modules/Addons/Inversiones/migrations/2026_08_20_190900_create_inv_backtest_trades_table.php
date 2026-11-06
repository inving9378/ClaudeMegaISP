<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_backtest_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backtest_id')->constrained('inv_backtests')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('inv_assets');
            $table->enum('lado', ['compra', 'venta']);
            $table->date('fecha_entrada');
            $table->decimal('precio_entrada', 18, 4);
            $table->date('fecha_salida')->nullable();
            $table->decimal('precio_salida', 18, 4)->nullable();
            $table->decimal('cantidad', 20, 8);
            $table->decimal('pnl', 18, 4)->nullable();
            $table->decimal('pnl_pct', 8, 4)->nullable();
            $table->decimal('costos', 18, 4)->default(0);
            $table->boolean('es_out_of_sample')->default(false);
            $table->timestamps();

            $table->index(['backtest_id', 'es_out_of_sample']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_backtest_trades');
    }
};
