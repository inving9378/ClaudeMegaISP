<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #9991150 (sub-item de #9991117) — auditoría dedicada de cambios de rol.
 *
 * Tabla append-only: se inserta una fila por cada cambio REAL de rol hecho desde
 * el diff dirigido de UserController::update (solo cuando !empty($toAdd) ||
 * !empty($toRemove), mismo guard que ya usa el Log::info existente, que NO se
 * reemplaza). Snapshots de login (actor/target) por si esas cuentas se borran
 * después. Modelo plano sin BaseModel/LogsActivity a propósito (mismo patrón que
 * FleetGeofenceEvent/AuditoriaSenal): alto volumen potencial, solo created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_change_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_login')->nullable();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_login');
            $table->json('roles_antes');
            $table->json('roles_despues');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('target_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_change_audits');
    }
};
