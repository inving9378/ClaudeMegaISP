<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CLABE virtual por cliente — generada por OpenPay (o proveedor SPEI).
     * Cada pago a esa CLABE notifica vía webhook con el transaction_id.
     * El cliente puede tener UNA sola CLABE activa a la vez (hasOne).
     *
     * clabe: char(18) con UNIQUE — no permite que el mismo número quede
     * asignado a dos clientes ni a un cliente dos veces.
     * openpay_id: identificador interno de OpenPay para esa CLABE (su API
     * lo necesita para consultas y para revocación si se desactiva el cliente).
     */
    public function up(): void
    {
        Schema::create('payment_clabes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->char('clabe', 18)->unique();
            $table->string('openpay_id', 64)->nullable()->index();
            $table->unsignedBigInteger('payment_provider_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('payment_provider_id')->references('id')->on('payment_providers')->nullOnDelete();
            $table->index(['client_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_clabes');
    }
};
