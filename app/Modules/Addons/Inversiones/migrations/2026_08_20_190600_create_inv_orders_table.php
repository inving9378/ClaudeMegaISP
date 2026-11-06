<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained('inv_portfolios')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('inv_assets');
            $table->foreignId('strategy_id')->nullable()->constrained('inv_strategies')->nullOnDelete();
            $table->enum('lado', ['compra', 'venta']);
            $table->enum('tipo', ['market', 'limit', 'stop'])->default('market');
            $table->decimal('cantidad', 20, 8);
            $table->decimal('precio_limite', 18, 4)->nullable();
            $table->enum('estado', [
                'propuesta', 'aprobada', 'rechazada_riesgo', 'enviada', 'ejecutada', 'cancelada',
            ])->default('propuesta');
            $table->string('motivo_rechazo', 255)->nullable();
            $table->decimal('precio_ejecucion', 18, 4)->nullable();
            $table->decimal('comision', 18, 4)->nullable();
            $table->dateTime('fecha_propuesta');
            $table->dateTime('fecha_ejecucion')->nullable();
            $table->string('broker_order_id', 100)->nullable();
            $table->json('payload_riesgo')->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_orders');
    }
};
