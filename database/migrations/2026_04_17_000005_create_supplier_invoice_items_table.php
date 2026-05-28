<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_invoice_id');
            $table->foreign('supplier_invoice_id')->references('id')->on('supplier_invoices')->onDelete('cascade');
            $table->unsignedBigInteger('inventory_item_id');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items');
            $table->enum('purchase_type', ['unit', 'bulk', 'other'])->default('unit');
            $table->decimal('quantity', 15, 2);
            $table->decimal('store_price', 15, 2);
            $table->decimal('total', 15, 2);
            $table->unsignedInteger('bulk_quantity')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_items');
    }
};
