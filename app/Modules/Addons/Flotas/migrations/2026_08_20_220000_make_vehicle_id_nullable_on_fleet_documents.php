<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #177 (177a) — Documentos de conductor sin vehículo: vehicle_id pasa a nullable.
 *
 * Decisión de Irving (brief estructurado, respondida 2026-08-20): opción recomendada de
 * cada pregunta — (q1) vehicle_id nullable + driver_id nullable, con la app exigiendo que
 * al menos uno esté presente; (q2) los documentos existentes NO se migran (se quedan con su
 * vehicle_id tal cual); (q3) UI en fase aparte (sub-item 177b).
 *
 * El tenant-scoping que bloqueaba el item (FleetDocument::scopeForClient vía whereHas('vehicle'))
 * se resuelve así: el "conductor" es un `users` (staff interno, sin client_id — ver migración
 * add_driver_id_to_fleet_documents). Un documento SOLO-conductor (vehicle_id NULL) por lo tanto
 * nunca puede pertenecer a un client externo: solo es visible bajo el scope interno
 * (clientId() === null). scopeForClient se actualiza en el modelo para reflejar exactamente eso
 * — cero riesgo de fuga entre clientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('vehicle_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fleet_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('vehicle_id')->nullable(false)->change();
        });
    }
};
