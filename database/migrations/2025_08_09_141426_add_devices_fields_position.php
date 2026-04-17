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
        Schema::table('map_olts', function (Blueprint $table) {
            $table->smallInteger('position_x')->default(20)->after('rack_id');
            $table->smallInteger('position_y')->default(20)->after('position_x');
        });
        Schema::table('map_organizer', function (Blueprint $table) {
            $table->smallInteger('position_x')->default(20)->after('rack_id');
            $table->smallInteger('position_y')->default(20)->after('position_x');
        });
        Schema::table('map_switch', function (Blueprint $table) {
            $table->smallInteger('position_x')->default(20)->after('rack_id');
            $table->smallInteger('position_y')->default(20)->after('position_x');
        });
        Schema::table('map_layers_routes', function (Blueprint $table) {
            $table->smallInteger('position_x')->default(20)->after('layer_id');
            $table->smallInteger('position_y')->default(20)->after('position_x');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_olts', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y']);
        });
        Schema::table('map_organizer', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y']);
        });
        Schema::table('map_switch', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y']);
        });
        Schema::table('map_layers_routes', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y']);
        });
    }
};
