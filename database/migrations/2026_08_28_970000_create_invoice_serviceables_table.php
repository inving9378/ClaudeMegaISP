<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #719 — Fase 1 de la unificación invoices/client_invoices (#632).
 *
 * `client_invoices` representa sus servicios asociados vía la tabla pivote
 * `client_serviceables` (morph client_serviceable_id/type). `invoices` no tiene
 * ningún equivalente. Esta migración crea el pivote análogo para `invoices`,
 * sin tocar `client_invoices`/`client_serviceables` ni ningún flujo vivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_serviceables')) {
            return;
        }

        Schema::create('invoice_serviceables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->boolean('pay')->default(false);
            $table->unsignedBigInteger('invoice_serviceable_id');
            $table->string('invoice_serviceable_type');
            $table->timestamps();

            $table->index(['invoice_serviceable_type', 'invoice_serviceable_id'], 'invoice_serviceables_morph_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_serviceables');
    }
};
