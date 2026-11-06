<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained('inv_portfolios')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('inv_assets');
            $table->decimal('cantidad', 20, 8);
            $table->decimal('costo_promedio', 18, 4);
            $table->decimal('precio_actual', 18, 4)->nullable();
            $table->decimal('valor_mercado', 18, 4)->nullable();
            $table->decimal('pnl_no_realizado', 18, 4)->nullable();
            $table->decimal('pnl_no_realizado_pct', 8, 4)->nullable();
            $table->dateTime('abierta_en');
            $table->decimal('stop_loss_precio', 18, 4)->nullable();
            $table->decimal('take_profit_precio', 18, 4)->nullable();
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');
            $table->timestamps();

            $table->index(['portfolio_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_positions');
    }
};
