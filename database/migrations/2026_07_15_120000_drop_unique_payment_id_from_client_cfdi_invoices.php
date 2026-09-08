<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrección pedida por Irving sobre #117: payment_id sigue siendo la relación
 * principal del CFDI, pero NO debe ser único — el diseño debe permitir historial
 * de sustituciones/reemisiones sobre el mismo pago sin perder los CFDI previos.
 * Se conserva un índice simple (no único) para las búsquedas por payment_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_cfdi_invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropUnique('client_cfdi_invoices_payment_id_unique');
            $table->index('payment_id');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('client_cfdi_invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropIndex(['payment_id']);
            $table->unique('payment_id');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
        });
    }
};
