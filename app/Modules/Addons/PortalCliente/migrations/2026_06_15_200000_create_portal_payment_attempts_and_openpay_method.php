<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración ADITIVA — Portal OpenPay.
 *
 * 1. Crea portal_payment_attempts para rastrear intentos de cobro por factura.
 * 2. Inserta el método de pago "Tarjeta OpenPay" en method_of_payments si no existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Tabla de intentos de cobro OpenPay ──────────────────────────
        Schema::create('portal_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('invoice_id');

            // Generado internamente para idempotencia — único por intento
            $table->string('order_id', 80)->unique();

            // ID del cargo en OpenPay (disponible tras respuesta exitosa o fallida)
            $table->string('openpay_charge_id', 100)->nullable();

            $table->decimal('amount', 10, 2);

            // Estados: pending, completed, failed, refunded
            $table->string('status', 20)->default('pending');

            // Código de error OpenPay (3001 = declinada, 3003 = fondos, etc.)
            $table->unsignedSmallInteger('error_code')->nullable();
            $table->string('error_message', 500)->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['client_id', 'invoice_id']);
            $table->index('status');
        });

        // ── Método de pago Tarjeta OpenPay ──────────────────────────────
        $existe = DB::table('method_of_payments')
            ->where('type', 'Tarjeta OpenPay')
            ->exists();

        if (! $existe) {
            DB::table('method_of_payments')->insert([
                'type'       => 'Tarjeta OpenPay',
                'active'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_payment_attempts');
        // No se revierte el método de pago (podría haber pagos reales referenciándolo)
    }
};
