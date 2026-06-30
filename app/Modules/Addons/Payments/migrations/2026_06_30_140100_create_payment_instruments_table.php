<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instrumento de cobro multi-motor por cliente. Generaliza el concepto que
     * payment_clabes resolvía SOLO para OpenPay.
     *
     * No se pliega payment_clabes: su columna clabe es char(18) NOT NULL UNIQUE,
     * estructuralmente atada a CLABE. El motor nativo NO usa CLABE (usa referencia
     * de conciliación), así que forzarlo ahí exigiría romper ese UNIQUE. payment_clabes
     * queda viva y exclusiva para OpenPay; esta tabla es el registro genérico.
     *
     *   instrument_type=reference  → external_ref = client_payment_references.reference (motor nativo)
     *   instrument_type=clabe      → external_ref = CLABE OpenPay (espejo de payment_clabes)
     *   instrument_type=card       → external_ref = token de tarjeta (futuro)
     */
    public function up(): void
    {
        Schema::create('payment_instruments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('payment_provider_id')->nullable();
            $table->enum('instrument_type', ['reference', 'clabe', 'card'])->default('reference');
            $table->string('external_ref', 64)->nullable();
            $table->enum('status', ['active', 'suspended', 'revoked'])->default('active');
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('payment_provider_id')->references('id')->on('payment_providers')->nullOnDelete();
            // Un instrumento por (cliente, proveedor, tipo) — evita duplicados activos.
            $table->unique(['client_id', 'payment_provider_id', 'instrument_type'], 'pay_instr_client_prov_type_uniq');
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_instruments');
    }
};
