<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo clave-valor tipado de parámetros del reglamento de ventas/comisiones
 * (item #9990808, Fase 1 de #9990781).
 *
 * Se crea VACÍA a propósito: bloqueada por #9990775 (reglamento sin texto fuente
 * todavía) — no reabrir esa investigación aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_parametros_reglamento', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->string('nombre', 150);
            $table->enum('tipo', ['entero', 'decimal', 'texto', 'booleano']);
            $table->string('valor', 255)->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_parametros_reglamento');
    }
};
