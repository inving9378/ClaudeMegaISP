<?php

use App\Models\MapCredential;
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
        Schema::table('system_map_credentials', function (Blueprint $table) {
            $table->dropColumn('api_key', 'map_id');
            $table->integer('zoom')->default(16)->after('longitude');
        });

        $map = MapCredential::count();
        if ($map == 0) {
            MapCredential::create([
                'latitude' => 19.70227382257621,
                'longitude' => -99.07251834869385,
                'zoom' => 15
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_map_credentials', function (Blueprint $table) {
            $table->string('api_key');
            $table->string('map_id');
            $table->dropColumn('zoom');
        });
    }
};
