<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro del "pago reportado" capturado por mostrador (Paso 2 · FASE PAGOS).
 *
 * Liga el pago ya APLICADO (payments, vía PaymentApplicationService) con su
 * comprobante (foto), la clave de rastreo SPEI y el estado de conciliación
 * para que Tere lo cruce después contra el banco. NO reemplaza a payments:
 * el abono de saldo vive en payments/observers como cualquier otro pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reported_payments', function (Blueprint $table) {
            $table->id();

            // El pago aplicado (fuente de verdad del abono). Nullable + nullOnDelete
            // para no perder el registro reportado si el payment se borra.
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('receiver_account_id')->nullable(); // cuenta receptora (portal_pago_accounts)
            $table->unsignedBigInteger('method_of_payment_id');

            $table->decimal('amount', 12, 2);
            $table->date('fecha_pago');

            // Datos del comprobante SPEI reportado.
            $table->string('clave_rastreo')->nullable();
            $table->string('titular')->nullable();       // nombre del ordenante
            $table->string('banco_origen')->nullable();
            $table->string('comprobante_path')->nullable(); // foto (disk local, privado)

            // Estado de conciliación por pago (lo cruza Tere contra el banco).
            $table->enum('conciliation_status', ['pendiente_verificar', 'verificado', 'rechazado'])
                ->default('pendiente_verificar');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('conciliation_note')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('receiver_account_id')->references('id')->on('portal_pago_accounts')->nullOnDelete();
            $table->foreign('method_of_payment_id')->references('id')->on('method_of_payments');
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();

            $table->index('conciliation_status');
            $table->index('client_id');
            $table->index('clave_rastreo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reported_payments');
    }
};
