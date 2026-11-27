<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-11-27 (pedido de Irving) — a quién se le llama ya no lo decide el tipo: se eligen los
 * estados del cliente (Activos, Bloqueados, Inactivos), si se quiere una zona o una caja, y si se
 * quiere solo a los que deben. El tipo queda como «Motivo» (cobranza, aviso, anuncio, corte).
 *
 * - `solo_deudores`: solo clientes con adeudo vencido; el mensaje puede decir su saldo y al que
 *   pague ya no se le llama. Las campañas de cobranza que ya existían quedan con él.
 * - `audiencia`: los filtros con que se activó y a quién se quitó a mano de la lista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cobranza_campanas') || Schema::hasColumn('cobranza_campanas', 'solo_deudores')) {
            return;
        }

        Schema::table('cobranza_campanas', function (Blueprint $table) {
            $table->boolean('solo_deudores')->default(false)->after('tipo');
            $table->json('audiencia')->nullable()->after('solo_deudores');
        });

        DB::table('cobranza_campanas')->where('tipo', 'cobranza')->update(['solo_deudores' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('cobranza_campanas', 'solo_deudores')) {
            Schema::table('cobranza_campanas', function (Blueprint $table) {
                $table->dropColumn(['solo_deudores', 'audiencia']);
            });
        }
    }
};
