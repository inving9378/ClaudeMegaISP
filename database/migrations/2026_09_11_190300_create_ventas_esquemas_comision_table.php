<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquemas de comisión del motor de ventas (item #9990799, Ola 1 de la adenda #9990796).
 *
 * `modalidad`/`modo_pago` quedan como texto libre a propósito: los catálogos parametrizables
 * (#9990781, en paralelo en esta misma ola) todavía no existen — cuando cierren, una migración
 * aditiva posterior puede sumar las FKs a esos catálogos sin romper esta tabla.
 * `parametros` (json) guarda cualquier parámetro numérico del reglamento (#0) que aún no tiene
 * columna propia, para no inventar estructura antes de que el reglamento exista.
 *
 * `colaborador_id` NULLABLE: NULL = esquema general (no atado a un colaborador específico).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_esquemas_comision', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id')->nullable();
            $table->string('nombre', 150);
            $table->string('modalidad', 60)->nullable();
            $table->string('modo_pago', 60)->nullable();
            $table->decimal('porcentaje', 6, 2)->nullable();
            $table->json('parametros')->nullable();
            $table->boolean('activo')->default(true);
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
            $table->index('activo');
            $table->index('modalidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_esquemas_comision');
    }
};
