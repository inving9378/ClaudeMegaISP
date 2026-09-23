<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MegaVoz Fase 2 — mini-teléfono WebRTC.
 *
 * "Endpoint doble" (plan 22-sep-2026): un colaborador de atención directa
 * tiene su extensión de escritorio de siempre (1201) y una extensión gemela
 * para el navegador (web1201) — nunca la misma fila con dos comportamientos.
 * `es_webrtc` distingue cuál es cuál para que
 * AsteriskProvisioningService::provisionarExtension() sepa si debe escribir
 * los campos WebRTC (webrtc/media_encryption/ice_support/...) en ps_endpoints.
 *
 * Aditiva, con default false: ninguna extensión existente cambia de
 * comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voip_extensiones', function (Blueprint $table) {
            $table->boolean('es_webrtc')->default(false)->after('tipo_dispositivo');
        });
    }

    public function down(): void
    {
        Schema::table('voip_extensiones', function (Blueprint $table) {
            $table->dropColumn('es_webrtc');
        });
    }
};
