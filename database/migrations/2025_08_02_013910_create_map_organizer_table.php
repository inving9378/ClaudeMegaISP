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
        Schema::create('map_organizer', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Organizador');
            $table->smallInteger('rows');
            $table->smallInteger('columns');
            $table->morphs('rack');
            $table->timestamps();
        });

        Schema::create('map_ports', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->smallInteger('position_x')->default(20);
            $table->smallInteger('position_y')->default(20);
            $table->enum('type', ['in', 'out'])->default('out');
            $table->enum('orientation', ['left', 'right'])->default('left');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->foreign('client_id')->references('id')->on('client_main_information')->cascadeOnDelete();
            $table->boolean('connected')->default(false);
            $table->longText('note')->nullable();
            $table->morphs('device');
            $table->timestamps();
        });

        Schema::create('map_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_id');
            $table->foreign('from_id')->references('id')->on('map_ports')->onDelete('cascade');
            $table->unsignedBigInteger('to_id');
            $table->foreign('to_id')->references('id')->on('map_ports')->onDelete('cascade');
            $table->enum('type', ['dotted', 'dashed', 'default'])->default('default');
            $table->string('color')->nullable();
            $table->smallInteger('width')->default(4);
            $table->enum('animate', ['left', 'right', 'default'])->default('default');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_connections');
        Schema::dropIfExists('map_ports');
        Schema::dropIfExists('map_organizer');
    }
};
