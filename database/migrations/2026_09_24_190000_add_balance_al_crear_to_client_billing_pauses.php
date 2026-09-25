<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opción A (2026-09-24) para detectar el pago de la cuota de conservación: en vez de una
 * factura/proforma (InvoiceService::createProformaInvoice está atada a los servicios
 * contratados del cliente, no sirve para un cargo libre), se compara el balance del cliente
 * al momento de crear la pausa contra el balance actual — el DELTA es lo que se pagó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_billing_pauses', function (Blueprint $table) {
            $table->decimal('balance_al_crear', 10, 2)->nullable()->after('monto_cuota');
        });
    }

    public function down(): void
    {
        Schema::table('client_billing_pauses', function (Blueprint $table) {
            $table->dropColumn('balance_al_crear');
        });
    }
};
