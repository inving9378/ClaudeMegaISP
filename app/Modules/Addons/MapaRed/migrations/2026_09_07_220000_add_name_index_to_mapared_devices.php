<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-22 Fase 1a-i (#9990532) — índice sobre mapared_devices.name para soportar el LIKE
 * del buscador global (Fase 1a-ii, controller+endpoint). Aditiva e idempotente
 * (Schema::hasIndex guard).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapared_devices', function (Blueprint $table) {
            if (!Schema::hasIndex('mapared_devices', 'mapared_devices_name_index')) {
                $table->index('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mapared_devices', function (Blueprint $table) {
            if (Schema::hasIndex('mapared_devices', 'mapared_devices_name_index')) {
                $table->dropIndex('mapared_devices_name_index');
            }
        });
    }
};
