<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal de Pago Meganet — cuentas de cobro propias (CLABEs Meganet).
 *
 * NO confundir con payment_clabes (CLABE virtual por cliente de OpenPay, con
 * comision). Estas son CLABEs NUESTRAS a las que el cliente transfiere por SPEI
 * y luego reporta la clave de rastreo para conciliacion via CEP. Comision = $0.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_pago_accounts')) {
            return;
        }

        Schema::create('portal_pago_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->char('clabe', 18);
            $table->string('banco')->nullable();
            $table->string('titular')->nullable();
            $table->string('beneficiario')->nullable();
            $table->boolean('activa')->default(true);
            // Multi-tenant: aislable por instancia. NULL = cuenta global Meganet.
            $table->unsignedBigInteger('instance_id')->nullable()->index();
            $table->timestamps();

            $table->index('clabe');
            $table->index('activa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_pago_accounts');
    }
};
