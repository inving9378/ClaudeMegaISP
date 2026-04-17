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
        Schema::table('map_layers', function (Blueprint $table) {
            $table->smallInteger('inputs')->default(6)->after('data');
        });
        Schema::table('map_layers_routes', function (Blueprint $table) {
            $table->smallInteger('input')->nullable()->after('direction');
        });

        DB::statement('UPDATE map_layers_routes t JOIN (SELECT id, ROW_NUMBER() OVER (PARTITION BY layer_id ORDER BY id) AS new_input FROM map_layers_routes) AS sub ON t.id = sub.id SET t.input = sub.new_input');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_layers', function (Blueprint $table) {
            $table->dropColumn('inputs');
        });
        Schema::table('map_layers_routes', function (Blueprint $table) {
            $table->dropColumn('input')->default(6)->after('data');
        });
    }
};
