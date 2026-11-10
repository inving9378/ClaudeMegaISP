<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1235 — primera toma de potencia (bajada, dBm) de cada conexión splitter→cliente en una
 * caja de servicio. Se autocaptura una sola vez (PotenciaCajaService) como referencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapared_devices_ports', function (Blueprint $table) {
            if (!Schema::hasColumn('mapared_devices_ports', 'potencia_inicial_dbm')) {
                $table->decimal('potencia_inicial_dbm', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('mapared_devices_ports', 'potencia_inicial_at')) {
                $table->timestamp('potencia_inicial_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['potencia_inicial_dbm', 'potencia_inicial_at'] as $col) {
            if (Schema::hasColumn('mapared_devices_ports', $col)) {
                Schema::table('mapared_devices_ports', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
