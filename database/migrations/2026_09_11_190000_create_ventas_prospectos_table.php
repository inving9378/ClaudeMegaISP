<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fuente única de prospectos del motor de ventas (item #9990799, Ola 1 de la adenda #9990796).
 *
 * `colaborador_id` es NULLABLE a propósito: la identidad unificada de colaborador (#9990778)
 * todavía no está lista. NULL = prospecto en el pool general, sin asignar.
 *
 * `origen`/`origen_id` guardan la traza hacia la fuente legacy (CRM, Vendedores, Portal
 * Colaborador) para que #9990779 pueda consolidar sin perder de dónde vino cada prospecto.
 *
 * Solo esquema — sin modelo Eloquent ni lógica de negocio (eso lo consumen #9990780 custodia
 * y #9990779 catálogo único, ambos posteriores).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_prospectos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('nombre', 150);
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('origen', 60)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->enum('estado', ['pool', 'en_custodia', 'vendido', 'perdido'])->default('pool');
            $table->text('notas')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->index('estado');
            $table->index(['origen', 'origen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_prospectos');
    }
};
