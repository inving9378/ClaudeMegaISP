<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1311 (Recorridos, fase a) — un recorrido de planta externa (a pie o en vehículo).
 *
 * - El archivo de ruta original (GPX/KML/KMZ de Geo Tracker, o el GPX de Flotas) se conserva tal
 *   cual como evidencia en el disco privado (`archivo_ruta`). En BD NO hay una fila por punto:
 *   solo la línea simplificada (`geometria`), una muestra para el recorte (`muestra`) y totales.
 * - El recorte se guarda como índices sobre los puntos del archivo original (sirve aunque la ruta
 *   no traiga horas); distancia, horas y geometría se recalculan sobre el tramo recortado.
 * - `tarea_id`: la orden que lo pidió, si lo asignó el supervisor (opcional).
 * - estado: borrador → enviado → evaluado (la evaluación es del item #1312).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_recorridos')) {
            return;
        }

        Schema::create('talento_recorridos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('tarea_id')->nullable();
            $table->string('titulo', 160)->nullable();
            $table->string('iniciado_por', 12);          // supervisor|colaborador
            $table->string('modo', 10);                  // a_pie|vehiculo
            $table->string('fuente', 10);                // gpx|kml|kmz|app
            $table->string('estado', 10)->default('borrador');
            $table->dateTime('iniciado_at')->nullable();
            $table->dateTime('terminado_at')->nullable();
            $table->unsignedInteger('recorte_desde')->nullable();
            $table->unsignedInteger('recorte_hasta')->nullable();
            $table->unsignedInteger('puntos_total')->default(0);
            $table->unsignedInteger('puntos')->default(0);   // dentro del recorte
            $table->unsignedInteger('distancia_m')->default(0);
            $table->json('geometria')->nullable();           // [[lat,lng],…] simplificada
            $table->json('muestra')->nullable();             // [[idx,lat,lng,t|null],…] para el recorte
            $table->json('bbox')->nullable();                // [minLat,minLng,maxLat,maxLng]
            $table->string('archivo_ruta', 255)->nullable();
            $table->string('archivo_nombre', 255)->nullable();
            $table->unsignedInteger('archivo_bytes')->nullable();
            $table->char('archivo_sha256', 64)->nullable();
            $table->dateTime('enviado_at')->nullable();
            $table->unsignedBigInteger('enviado_por')->nullable();
            $table->dateTime('evaluado_at')->nullable();
            $table->unsignedBigInteger('evaluado_por')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['colaborador_id', 'iniciado_at'], 'trec_colab_inicio_idx');
            $table->index('estado', 'trec_estado_idx');
            $table->index('tarea_id', 'trec_tarea_idx');
            $table->foreign('colaborador_id', 'trec_colab_fk')
                ->references('id')->on('talento_colaboradores')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_recorridos');
    }
};
