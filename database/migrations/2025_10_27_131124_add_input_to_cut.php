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
            $table->smallInteger('current_input')->default(0)->after('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_fibers_cut', function (Blueprint $table) {
            $table->dropColumn('current_input');
        });
    }
};
