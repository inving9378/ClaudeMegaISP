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
        try {
            Schema::table('map_routes', function (Blueprint $table) {
                $table->string('color')->nullable()->after('map_proyect_id');
                $table->json('coords')->nullable()->after('color');
                $table->string('description')->nullable()->change();
                $table->integer('fibers_amount')->nullable()->change();
                $table->integer('weight')->after('color')->default(4);
                $table->dropConstrainedForeignId('color_id');
            });
        } catch (\Throwable $th) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_routes', function (Blueprint $table) {
            $table->dropColumn('coords', 'color', 'weight');
        });
    }
};