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
        Schema::create('map_fibers_cut', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiber_id');
            $table->foreign('fiber_id')->references('id')->on('map_fibers')->onDelete('cascade');
            $table->unsignedBigInteger('layer_id');
            $table->foreign('layer_id')->references('id')->on('map_layers')->onDelete('cascade');
            $table->string('state');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_fibers_cut');
    }
};
