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
            $table->boolean('client_datatable_color')->after('color_mode')->default(false)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_layout_configurations', function (Blueprint $table) {
            $table->dropColumn('client_datatable_color');
        });
    }
};
