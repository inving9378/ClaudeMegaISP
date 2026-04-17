<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('update client_main_information set geodata=geo_data where geodata is null');

        Schema::table('map_layers', function (Blueprint $table) {
            $table->unsignedBigInteger('layerable_id')->nullable()->after('label');
            $table->string('layerable_type')->nullable()->after('layerable_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_layers', function (Blueprint $table) {
            $table->dropColumn('layerable_id', 'layerable_type');
        });
    }
};
