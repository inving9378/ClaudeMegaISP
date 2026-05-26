<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditoría de TODOS los webhooks entrantes — exitosos, fallidos,
     * duplicados, mal-firmados, todo. Sirve para:
     *   - Idempotencia: lookup por external_id antes de procesar (si ya
     *     existe row con status='processed', responder 200 sin duplicar).
     *   - Debugging: el payload crudo queda preservado aunque el handler
     *     truene a mitad del proceso.
     *   - Compliance/forense: trail completo de eventos del proveedor.
     *
     * external_id: id que asigna el proveedor a la transacción (transaction_id
     * de OpenPay, charge_id de Stripe, etc.). Indexado para idempotencia.
     */
    public function up(): void
    {
        Schema::create('payment_webhooks_log', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);                 // openpay | stripe | conekta | ...
            $table->string('event_type', 64);               // charge.succeeded | transaction.completed | ...
            $table->string('external_id', 128)->nullable(); // transaction_id del proveedor
            $table->json('payload');                        // payload crudo del webhook
            $table->string('status', 16)->default('pending'); // pending | processed | failed | duplicate | signature_invalid
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->index(['provider', 'external_id']); // idempotency lookup
            $table->index(['status', 'created_at']);    // retry / monitoring
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks_log');
    }
};
