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
        Schema::table('app_layout_configurations', function (Blueprint $table) {
            $table->dropColumn('tabs_json');
        });
        Schema::table('app_layout_configurations', function (Blueprint $table) {
            $table->json('tabs_json')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_layout_configurations', function (Blueprint $table) {
            $table->dropColumn('tabs_json');
        });
        Schema::table('app_layout_configurations', function (Blueprint $table) {
            $table->longText('tabs_json')->nullable();
        });
    }
};
