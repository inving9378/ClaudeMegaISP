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
        Schema::create('map_site_racks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Rack');
            $table->text('description')->nullable();
            $table->smallInteger('position_x')->default(20);
            $table->smallInteger('position_y')->default(20);
            $table->unsignedBigInteger('site_id');
            $table->foreign('site_id')->references('id')->on('map_layers')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_site_racks');
    }
};
