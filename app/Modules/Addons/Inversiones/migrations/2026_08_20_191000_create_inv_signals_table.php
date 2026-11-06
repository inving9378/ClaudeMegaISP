<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_id')->constrained('inv_strategies');
            $table->foreignId('asset_id')->constrained('inv_assets');
            $table->date('fecha');
            $table->enum('direccion', ['comprar', 'vender', 'mantener']);
            $table->decimal('fuerza', 6, 4)->nullable();
            $table->text('razonamiento')->nullable();
            $table->json('datos')->nullable();
            $table->boolean('procesada')->default(false);
            $table->timestamps();

            $table->index(['strategy_id', 'fecha']);
            $table->index('procesada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_signals');
    }
};
