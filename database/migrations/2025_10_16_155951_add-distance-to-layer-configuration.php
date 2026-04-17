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
        Schema::table('map_layers_routes', function (Blueprint $table) {
            $table->decimal('calculate_distance')->after('input')->default(0);
            $table->decimal('real_distance')->after('calculate_distance')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_layers_routes', function (Blueprint $table) {
            $table->dropColumn(['calculate_distance', 'real_distance']);
        });
    }
};
