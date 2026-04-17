<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('inventory_item_stocks');
        Schema::create('inventory_item_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_item_id'); // Corrección: nombre correcto de la columna
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('cascade');

            // Relación polimórfica
            $table->string('modelable_type'); // Tipo de modelo (ejemplo: 'Warehouse', 'Supplier')
            $table->unsignedBigInteger('modelable_id'); // ID del modelo asociado
            $table->integer('current_stock')->default(0); // Stock actual
            $table->timestamps();

            // Índice optimizado
            $table->index(['modelable_type', 'modelable_id'], 'idx_modelable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_item_stocks');
    }
};
