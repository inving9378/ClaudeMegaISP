<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal de Pago Meganet — recurrencia asistida.
 *
 * NO es auto-debito (SPEI no se jala). Segun dia_corte, el comando
 * pagos:enviar-recurrentes (Paso 5) genera y envia la liga del mes al cliente:
 * link + recordatorio + un clic. Distinto de Domiciliacion (auto-debito tarjeta).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_pago_recurrences')) {
            return;
        }

        Schema::create('portal_pago_recurrences', function (Blueprint $table) {
            $table->id();
            // FK logica -> clients.id.
            $table->unsignedBigInteger('client_id')->index();
            // FK fisica -> portal_pago_accounts (tabla propia del modulo).
            $table->unsignedBigInteger('account_id');
            $table->unsignedTinyInteger('dia_corte');
            $table->decimal('monto', 12, 2);
            $table->boolean('activa')->default(true);
            $table->timestamp('ultimo_link_enviado_at')->nullable();
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')->on('portal_pago_accounts')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->index(['activa', 'dia_corte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_pago_recurrences');
    }
};
