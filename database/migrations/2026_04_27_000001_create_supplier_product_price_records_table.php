<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_product_price_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_product_price_id')
                  ->constrained('supplier_product_prices', 'id')
                  ->onDelete('cascade')
                  ->name('sppr_price_foreign');
            $table->decimal('base_price', 15, 2)->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('bulk_price', 15, 2)->nullable();
            $table->unsignedInteger('bulk_min_quantity')->nullable();
            $table->enum('source', ['catalog_create', 'catalog_update', 'invoice']);
            $table->foreignId('supplier_invoice_id')->nullable()
                  ->constrained('supplier_invoices', 'id')
                  ->nullOnDelete()
                  ->name('sppr_invoice_foreign');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete()->name('sppr_changed_by_foreign');
            $table->timestamps();

            $table->index(
                ['supplier_product_price_id', 'created_at'],
                'sppr_price_created_at_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_price_records');
    }
};
