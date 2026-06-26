<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte de geocercas POLIGONALES en el portal MegaFamilia.
 *
 * `coordinates` = array JSON de vértices [[lat,lng],[lat,lng],...] para polígonos.
 * Las geocercas circulares siguen usando lat/lng/radius_meters (coordinates = null).
 * El discriminador es la columna `type` ('circle' | 'polygon'). Aditiva, NO destructiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('parental_geofences', 'coordinates')) {
            Schema::table('parental_geofences', function (Blueprint $table) {
                $table->json('coordinates')->nullable()->after('radius_meters');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('parental_geofences', 'coordinates')) {
            Schema::table('parental_geofences', function (Blueprint $table) {
                $table->dropColumn('coordinates');
            });
        }
    }
};
