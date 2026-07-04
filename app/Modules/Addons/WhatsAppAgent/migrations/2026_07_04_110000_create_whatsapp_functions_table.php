<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3 (capa de funciones WhatsApp) — Catálogo editable de funciones.
 *
 * Una "función" es lo que sabe hacer una línea (Ventas, Cobranza, Soporte, Atención…).
 * `exclusive` = una función que solo puede vivir en UNA línea a la vez (nace TRUE;
 * Irving puede pasarla a FALSE para permitir varias líneas). `slug` es estable — la
 * usará Fase 4c para enrutar "qué línea atiende mi función".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_functions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->string('description', 255)->nullable();
            $table->boolean('exclusive')->default(true);   // exclusiva = una sola línea
            $table->string('color', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->integer('position')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->unique('name');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_functions');
    }
};
