<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Velocidad reciente (bits/seg) derivada del delta de bytes entre dos pasadas de
     * mikrotik:sync-consumption. Nullable: en la primera muestra de una sesión no hay
     * delta previo para calcular tasa.
     */
    public function up(): void
    {
        Schema::table('internet_consumptions', function (Blueprint $table) {
            if (!Schema::hasColumn('internet_consumptions', 'rate_in_bps')) {
                $table->bigInteger('rate_in_bps')->nullable()->after('bytes_out');
            }
            if (!Schema::hasColumn('internet_consumptions', 'rate_out_bps')) {
                $table->bigInteger('rate_out_bps')->nullable()->after('rate_in_bps');
            }
        });
    }

    public function down(): void
    {
        Schema::table('internet_consumptions', function (Blueprint $table) {
            if (Schema::hasColumn('internet_consumptions', 'rate_in_bps')) {
                $table->dropColumn('rate_in_bps');
            }
            if (Schema::hasColumn('internet_consumptions', 'rate_out_bps')) {
                $table->dropColumn('rate_out_bps');
            }
        });
    }
};
