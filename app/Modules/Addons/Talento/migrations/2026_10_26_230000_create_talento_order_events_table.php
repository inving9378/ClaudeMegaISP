<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNI-T E2 (item #882): historial de eventos de orden, append-only.
 * Aditiva: ningún flujo la lee ni escribe hasta que D2 (OrdenTrabajoUnifiedService) use
 * TalentoOrderEventRecorder. Sin updated_at ni soft deletes: un evento no se edita ni se borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_order_events')) {
            return;
        }
        Schema::create('talento_order_events', function (Blueprint $table) {
            $table->id();
            // Origen de la orden: 'task' (tasks) | 'work_order' (talento_work_orders) | otros futuros.
            $table->string('origen', 40);
            $table->unsignedBigInteger('orden_id');
            $table->string('evento', 60);
            $table->string('estado_de', 40)->nullable();
            $table->string('estado_a', 40)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('motivo', 500)->nullable();
            $table->json('datos')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['origen', 'orden_id', 'id'], 'toe_orden_idx');
            $table->index('evento');
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_order_events');
    }
};
