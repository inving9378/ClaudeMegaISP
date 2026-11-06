<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de modos de pago del motor de ventas (item #9990808, Fase 1 de #9990781).
 *
 * Se siembra (migración aparte, idempotente) con los 9 registros reales de
 * `method_of_payments`. Tabla independiente a propósito: `method_of_payments`
 * es solo-lectura para este item (fuera de alcance tocarla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_modos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 150);
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_modos_pago');
    }
};
