<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración de proveedores externos de pago (OpenPay, Stripe, etc.).
     * Renombrado de "payment_methods" → "payment_providers" para evitar
     * colisión semántica con method_of_payments (catálogo simple de tipos
     * humanos como "Efectivo en Caja", "Transferencia Bancaria").
     *
     * config: JSON encriptado vía Crypt facade (cast 'encrypted:json' en
     * el modelo). Contiene merchant_id, api_key, webhook_secret, sandbox(bool)
     * y cualquier otro campo específico del proveedor.
     */
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider', 32); // openpay | stripe | paypal | spei_manual | conekta...
            $table->boolean('is_active')->default(false);
            $table->text('config')->nullable(); // encrypted JSON con credenciales
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_providers');
    }
};
