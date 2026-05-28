<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_item_stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->after('current_stock');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();

            $table->unsignedBigInteger('supplier_invoice_item_id')->nullable()->after('supplier_id');
            $table->foreign('supplier_invoice_item_id')->references('id')->on('supplier_invoice_items')->nullOnDelete();

            $table->decimal('unit_cost', 15, 2)->nullable()->after('supplier_invoice_item_id');

            $table->enum('condition', ['new', 'used', 'damaged'])->default('new')->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_item_stocks', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['supplier_invoice_item_id']);
            $table->dropColumn([
                'supplier_id',
                'supplier_invoice_item_id',
                'unit_cost',
                'condition',
            ]);
        });
    }
};
