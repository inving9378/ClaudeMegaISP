<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** UNI-T L1: evento de garantía que liga un ticket con la orden original, el cliente y los participantes (aditivo). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_warranty_events')) {
            return;
        }
        Schema::create('talento_warranty_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ticket_id')->unique();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('window_id')->nullable();
            $t->string('orden_origen', 20);          // task | work_order
            $t->unsignedBigInteger('orden_id');
            $t->string('orden_ref', 30);             // referencia E1: T-<id> | L-<id>
            $t->json('participantes')->nullable();   // [{user_id, rol}] de la orden original (D8)
            $t->timestamp('orden_validada_at')->nullable();
            $t->timestamp('ventana_expira_at')->nullable();
            $t->timestamps();
            $t->index(['client_id', 'orden_origen', 'orden_id'], 'tl_war_ev_orden_idx');
        });
    }

    public function down(): void
    {
        // Aditiva a propósito: conserva el historial de garantías.
    }
};
