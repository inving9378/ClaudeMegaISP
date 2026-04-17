<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            // Relación polimórfica (puede ser servicio, producto, paquete, etc.)
            $table->nullableMorphs('modelable');
            $table->string('name'); // Nombre del item
            $table->text('description')->nullable(); // Descripción detallada

            // Información de precios e impuestos
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_rate', 5, 2)->default(0); // Porcentaje de impuesto
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
