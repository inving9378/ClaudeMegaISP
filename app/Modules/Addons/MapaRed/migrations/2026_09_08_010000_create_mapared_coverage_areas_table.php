<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-22 Fase 2c-2 (item roadmap #9990540) — cobertura DECLARADA/editorial: polígonos
 * dibujados manualmente por un admin ("aquí SÍ damos servicio"), decidido por Irving en
 * #9990494 q2 (Opción 1). Concepto DISTINTO de MR-26 (item #9990522, tabla propia
 * `mapared_sectores_inalambricos` + servicio `MapaRedCoberturaService`, cobertura VENDIBLE
 * calculada automáticamente desde radios de drop de NAPs) — no comparten tabla ni permiso.
 * Nombre de tabla con prefijo `mapared_` (consistente con el resto de tablas nuevas del
 * módulo) y sufijo `_areas` (no `_zones`) para no chocar con ningún nombre ya usado por MR-26.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_coverage_areas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('tipo_tecnologia', ['ftth', 'inalambrico']);
            $table->json('polygon');
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('activo');
            $table->index('tipo_tecnologia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_coverage_areas');
    }
};
