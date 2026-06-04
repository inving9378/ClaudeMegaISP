<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_projects', function (Blueprint $table) {
            // Polilínea del corredor: array de [lat, lng]
            // e.g. [[19.432, -99.133], [19.435, -99.130], ...]
            $table->json('corridor_path')->nullable()->after('bonus_scale');
        });
    }

    public function down(): void
    {
        Schema::table('talento_projects', function (Blueprint $table) {
            $table->dropColumn('corridor_path');
        });
    }
};
