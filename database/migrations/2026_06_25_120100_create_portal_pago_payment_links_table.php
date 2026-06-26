<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal de Pago Meganet — ligas de pago por token.
 *
 * Cada liga expone UNA sola factura (client_invoices) via token no enumerable.
 * document_id referencia client_invoices.id (la tabla LEGACY del cobro vivo,
 * confirmada en Paso 0). Se usa columna indexada + FK logica (sin constraint
 * fisico) para no acoplar una tabla legacy de ~110k filas a este modulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_pago_payment_links')) {
            return;
        }

        Schema::create('portal_pago_payment_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            // FK logica -> client_invoices.id (tabla legacy del cobro vivo).
            $table->unsignedBigInteger('document_id')->index();
            // FK logica -> clients.id.
            $table->unsignedBigInteger('client_id')->index();
            // FK fisica -> portal_pago_accounts (tabla propia del modulo).
            $table->unsignedBigInteger('account_id');
            $table->decimal('monto_esperado', 12, 2);
            $table->string('referencia_unica')->unique();
            $table->enum('estado', [
                'pendiente',
                'reportado',
                'validado',
                'conciliado',
                'expirado',
                'rechazado',
            ])->default('pendiente');
            $table->timestamp('expira_at')->nullable();
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')->on('portal_pago_accounts')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->index(['estado', 'expira_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_pago_payment_links');
    }
};
