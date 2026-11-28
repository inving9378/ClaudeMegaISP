<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 27-nov (pedido de Irving: «la IA de VoIP funcionando al máximo»): la asistente de voz elige
 * tono y velocidad como las campañas, y cuántas llamadas atiende a la vez se fija desde su
 * pantalla (antes solo con MEGAVOZ_BOT_VOZ_MAX_SIMULTANEAS en el .env). NULL = lo del .env.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ia_bot_config')) {
            return;
        }

        Schema::table('ia_bot_config', function (Blueprint $table) {
            if (! Schema::hasColumn('ia_bot_config', 'tono')) {
                $table->string('tono', 20)->nullable()->after('voice');
            }
            if (! Schema::hasColumn('ia_bot_config', 'velocidad')) {
                $table->decimal('velocidad', 3, 2)->nullable()->after('tono');
            }
            if (! Schema::hasColumn('ia_bot_config', 'lineas')) {
                $table->unsignedTinyInteger('lineas')->nullable()->after('piloto_porcentaje');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ia_bot_config')) {
            return;
        }

        Schema::table('ia_bot_config', function (Blueprint $table) {
            foreach (['tono', 'velocidad', 'lineas'] as $columna) {
                if (Schema::hasColumn('ia_bot_config', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
