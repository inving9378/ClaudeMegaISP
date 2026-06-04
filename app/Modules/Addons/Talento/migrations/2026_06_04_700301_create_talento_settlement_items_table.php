<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_settlement_items')) return;

        Schema::create('talento_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('settlement_id');
            $table->unsignedBigInteger('stock_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->string('item_name', 200);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('current_stock', 10, 3);
            $table->enum('disposition', ['returned', 'damaged', 'missing'])->default('returned');
            $table->decimal('debit_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('settlement_id')
                  ->references('id')->on('talento_settlements')->onDelete('cascade');
            $table->index(['settlement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_settlement_items');
    }
};
