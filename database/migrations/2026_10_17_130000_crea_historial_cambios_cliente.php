<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-04 (#538, decisión de Irving 2026-10-17): historial de cambios de estado y de contrato del
 * cliente — quién, cuándo, valor anterior → nuevo y origen (usuario, sistema o pago). Aditiva:
 * tabla nueva, no toca datos existentes. Solo registra lo que cambie desde el despliegue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_historial_cambios')) {
            return;
        }
        Schema::create('client_historial_cambios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('campo', 40);            // estado | plazo | plazo_especial_meses | activation_date | ...
            $table->string('valor_anterior', 255)->nullable();
            $table->string('valor_nuevo', 255)->nullable();
            $table->string('origen', 20);           // usuario | sistema | pago
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('motivo', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['client_id', 'created_at']);
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_historial_cambios');
    }
};
