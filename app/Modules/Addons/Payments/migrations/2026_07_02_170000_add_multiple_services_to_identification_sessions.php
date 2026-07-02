<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3 — Marca "cliente identificado con varios servicios".
 *
 * Cuando un cliente queda identificado pero tiene MÁS DE UN servicio, no se
 * bloquea el flujo: se marca resolved_multiple_services=true para que la parte
 * humana / Fase 4 decida a qué servicio se aplica (Irving lo afina después).
 * NO se usa un "ID de servicio" en la conversación.
 *
 * Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_identification_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_identification_sessions', 'resolved_multiple_services')) {
                $table->boolean('resolved_multiple_services')->default(false)->after('resolved_client_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_identification_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_identification_sessions', 'resolved_multiple_services')) {
                $table->dropColumn('resolved_multiple_services');
            }
        });
    }
};
