<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clave de conciliación estable por cliente — motor de cobro NATIVO.
     *
     * A diferencia de payment_clabes (atada a OpenPay), esta referencia NO es
     * enrutable: el cliente transfiere a las cuentas reales de Meganet
     * (portal_pago_accounts) y la referencia es lo único que lo identifica al
     * conciliar. Es inmutable, una por cliente, agnóstica al motor.
     *
     * Formato: MEG-{client_id 8 díg}-{verificador mod-97 ISO 7064}. Ej: MEG-00000142-37.
     * algo_version permite versionar el algoritmo sin romper referencias viejas.
     *
     * Tabla aparte (no columna en clients) para: versionar algoritmo, no tocar
     * el esquema crítico de clients, y ser trivialmente reversible.
     */
    public function up(): void
    {
        Schema::create('client_payment_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->unique();
            $table->string('reference', 32)->unique();
            $table->unsignedTinyInteger('algo_version')->default(1);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_payment_references');
    }
};
