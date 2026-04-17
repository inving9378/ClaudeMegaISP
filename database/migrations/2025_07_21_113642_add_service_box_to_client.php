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
        Schema::table('map_layers', function (Blueprint $table) {
            $table->unsignedBigInteger('service_box_id')->nullable()->after('layerable_type');
            $table->foreign('service_box_id')->references('id')->on('map_layers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_layers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_box_id');
        });
    }
};
