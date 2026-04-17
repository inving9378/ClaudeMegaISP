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
        Schema::table('map_splitters_ports', function (Blueprint $table) {
            $table->smallInteger('position_x')->default(20)->after('connected');
            $table->smallInteger('position_y')->default(20)->after('position_x');
            $table->enum('orientation', ['left', 'right'])->default('left')->after('position_y');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_splitters_ports', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y', 'orientation']);
        });
    }
};
