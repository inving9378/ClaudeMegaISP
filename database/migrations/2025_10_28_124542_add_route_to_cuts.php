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
        Schema::table('map_fibers_cut', function (Blueprint $table) {
            $table->unsignedBigInteger('route_id')->nullable()->after('current_input');
            $table->foreign('route_id')->references('id')->on('map_layers_routes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_fibers_cut', function (Blueprint $table) {
            $table->dropConstrainedForeignId('route_id');
        });
    }
};
