<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #93 — Documentos de conductor: agrega driver_id a fleet_documents.
 *
 * El "conductor" es un operador (users), igual que fleet_assignments.user_id
 * (no existe tabla fleet_drivers). Aditiva y opcional:
 *  - nullable: hoy los documentos son solo-de-vehículo; driver_id es opcional.
 *  - ON DELETE SET NULL: borrar el user NO debe eliminar el documento.
 *
 * vehicle_id se mantiene NOT NULL a propósito: scopeForClient deriva el tenant
 * (client_id) vía whereHas('vehicle'). Documentos 100% sin vehículo quedan como
 * seguimiento aparte (requiere rediseñar el tenant scoping).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_documents', function (Blueprint $table) {
            $table->foreignId('driver_id')
                ->nullable()
                ->after('vehicle_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fleet_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
        });
    }
};
