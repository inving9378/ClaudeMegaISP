<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_invoice_id')->nullable()->after('status');
            $table->foreign('supplier_invoice_id')->references('id')->on('supplier_invoices')->nullOnDelete();

            $table->unsignedBigInteger('supplier_invoice_item_id')->nullable()->after('supplier_invoice_id');
            $table->foreign('supplier_invoice_item_id')->references('id')->on('supplier_invoice_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['supplier_invoice_id']);
            $table->dropForeign(['supplier_invoice_item_id']);
            $table->dropColumn(['supplier_invoice_id', 'supplier_invoice_item_id']);
        });
    }
};
