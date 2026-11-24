<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-11-24 (pedido de Irving) — cada campaña elige su voz, la velocidad y el tono, y se
 * escucha desde el alta. El mensaje que se lee en la llamada es la nota de la campaña.
 * Las campañas que ya existían quedan con voz NULL y siguen leyendo su mensaje de antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cobranza_campanas') || Schema::hasColumn('cobranza_campanas', 'voz')) {
            return;
        }

        Schema::table('cobranza_campanas', function (Blueprint $table) {
            $table->string('voz', 20)->nullable()->after('notas');
            $table->decimal('voz_velocidad', 3, 2)->nullable()->after('voz');
            $table->string('voz_tono', 20)->nullable()->after('voz_velocidad');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('cobranza_campanas', 'voz')) {
            Schema::table('cobranza_campanas', function (Blueprint $table) {
                $table->dropColumn(['voz', 'voz_velocidad', 'voz_tono']);
            });
        }
    }
};
