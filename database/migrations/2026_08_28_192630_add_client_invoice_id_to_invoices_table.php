<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Aditiva — habilita el espejo idempotente de client_invoices en invoices
        // (roadmap #721, Fase 2 de la unificación). Sin FK: client_invoices no
        // participa de la integridad referencial de invoices, solo se usa como
        // llave de búsqueda para no duplicar la fila espejo.
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('client_invoice_id')->nullable()->after('client_id');
            $table->index('client_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['client_invoice_id']);
            $table->dropColumn('client_invoice_id');
        });
    }
};
