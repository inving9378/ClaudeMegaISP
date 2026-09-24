<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MegaVoz Fase 7 — generaliza el Blaster de cobranza a 4 tipos de campaña
 * (cobranza/aviso/anuncio/corte), cada uno con su propia troncal y su propio
 * límite de canales simultáneos, tal como pide el plan.
 *
 * `tipo` default 'cobranza': las campañas ya existentes (y el flujo actual,
 * sin tocar) siguen siendo exactamente lo que eran. Nada cambia para ellas.
 *
 * `troncal_id` nullable: si no se elige una, `AmiConnectionService::originate()`
 * sigue cayendo al troncal único global (config('voip.trunk_endpoint_id')) —
 * mismo comportamiento de siempre. Sin FK dura hacia `voip_troncales` (tabla
 * de un módulo distinto, addon-voip): se valida en el controller, no en el
 * esquema — mismo criterio que el resto del sistema para relaciones
 * entre-addons.
 *
 * `max_canales_simultaneos` nullable: sin límite = comportamiento actual
 * (BlastCampanaJob ya limitaba el LOTE a 50, pero nunca limitaba cuántas de
 * esas 50 pueden estar sonando/hablando AL MISMO TIEMPO — ahora sí, cuando
 * se configura).
 *
 * ADITIVA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobranza_campanas', function (Blueprint $table) {
            $table->enum('tipo', ['cobranza', 'aviso', 'anuncio', 'corte'])
                ->default('cobranza')
                ->after('nombre');
            $table->unsignedBigInteger('troncal_id')->nullable()->after('tipo');
            $table->unsignedSmallInteger('max_canales_simultaneos')->nullable()->after('troncal_id');
        });
    }

    public function down(): void
    {
        Schema::table('cobranza_campanas', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'troncal_id', 'max_canales_simultaneos']);
        });
    }
};
