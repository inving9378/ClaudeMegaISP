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
        Schema::table(
            'map_layers_routes',
            function (Blueprint $table) {
                $table->string('direction')->default('right')->after('position_y');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(
            'map_layers_routes',
            function (Blueprint $table) {
                $table->dropColumn('direction');
            }
        );
    }
};
