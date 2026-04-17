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
        Schema::create('map_splitters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->smallInteger('ports');
            $table->smallInteger('position_x')->default(20);
            $table->smallInteger('position_y')->default(20);
            $table->enum('orientation', ['left', 'right'])->default('right');
            $table->unsignedBigInteger('box_id');
            $table->foreign('box_id')->references('id')->on('map_layers')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('map_splitters_ports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['in', 'out'])->default('out');
            $table->unsignedBigInteger('splitter_id');
            $table->foreign('splitter_id')->references('id')->on('map_splitters')->onDelete('cascade');
            $table->boolean('selected')->default(false);
            $table->timestamps();
        });

        Schema::create('map_splitters_ports_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('port_from_id');
            $table->foreign('port_from_id')->references('id')->on('map_splitters_ports')->onDelete('cascade');
            $table->unsignedBigInteger('port_to_id');
            $table->foreign('port_to_id')->references('id')->on('map_splitters_ports')->onDelete('cascade');
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
        Schema::dropIfExists('map_splitters_ports_connections');
        Schema::dropIfExists('map_splitters_ports');
        Schema::dropIfExists('map_splitters');
    }
};
