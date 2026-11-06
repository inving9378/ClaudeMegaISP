<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de modalidades de venta (item #9990808, Fase 1 de #9990781).
 *
 * Se crea VACÍA a propósito: no existe hoy ningún concepto de "modalidad de venta"
 * en el código/BD (verificado por #9990808, no reabrir esa investigación).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_modalidades', function (Blueprint $table) {
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
        Schema::dropIfExists('ventas_modalidades');
    }
};
