<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_product_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->unsignedBigInteger('inventory_item_id');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items');
            $table->foreignId('inventory_item_stock_id')->nullable()->constrained('inventory_item_stocks')->onDelete('cascade');
            $table->decimal('base_price', 15, 2)->default(0);
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('bulk_price', 15, 2)->nullable();
            $table->unsignedInteger('bulk_min_quantity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['supplier_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_prices');
    }
};
