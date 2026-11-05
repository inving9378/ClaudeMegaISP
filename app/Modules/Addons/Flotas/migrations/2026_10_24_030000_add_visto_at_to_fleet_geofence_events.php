<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Historial de alertas del portal (#645): marca "vista" por el cliente. Aditiva.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('fleet_geofence_events', 'visto_at')) {
            Schema::table('fleet_geofence_events', function (Blueprint $table) {
                $table->timestamp('visto_at')->nullable()->after('occurred_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fleet_geofence_events', 'visto_at')) {
            Schema::table('fleet_geofence_events', function (Blueprint $table) {
                $table->dropColumn('visto_at');
            });
        }
    }
};
