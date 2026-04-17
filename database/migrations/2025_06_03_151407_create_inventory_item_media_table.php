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
        Schema::create('inventory_item_media', function (Blueprint $table) {
            $table->id();
            $table->string('inventory_item_stock_id')->nullable();
            $table->string('inventory_item_id')->nullable();
            $table->string('url');
            $table->string('name');
            $table->string('type');
            $table->integer('size');
            $table->integer('order');
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_item_media');
    }
};
