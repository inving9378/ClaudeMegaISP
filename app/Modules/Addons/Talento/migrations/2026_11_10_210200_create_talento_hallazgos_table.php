<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1311 (Recorridos, fase a) — un hallazgo dentro de un recorrido: categoría, severidad,
 * descripción (obligatoria antes de enviar) y su ubicación.
 *
 * - fuente_ubicacion: exif (la foto trae GPS) · por_hora (la hora de la foto contra la ruta) ·
 *   manual (puesto o movido a mano en el mapa) · gps (capturado en la app, fase b / #1312).
 * - estado, evaluado_por, evaluado_at y motivo_descarte: los usa la evaluación del supervisor (#1312).
 * - El vínculo con un elemento de Mapa de Red queda fuera hasta que se decida cuál es el mapa
 *   oficial (#1290 / M9); se agregará como columna aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_hallazgos')) {
            return;
        }

        Schema::create('talento_hallazgos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recorrido_id');
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->string('severidad', 10)->nullable();     // baja|media|alta|critica
            $table->text('descripcion')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('precision_m')->nullable();
            $table->string('fuente_ubicacion', 10)->nullable();
            $table->dateTime('capturado_at')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->string('estado', 12)->default('pendiente'); // pendiente|descartado|convertido
            $table->unsignedBigInteger('evaluado_por')->nullable();
            $table->dateTime('evaluado_at')->nullable();
            $table->string('motivo_descarte', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['recorrido_id', 'orden'], 'thal_rec_orden_idx');
            $table->index('estado', 'thal_estado_idx');
            $table->foreign('recorrido_id', 'thal_rec_fk')
                ->references('id')->on('talento_recorridos')->cascadeOnDelete();
            $table->foreign('categoria_id', 'thal_cat_fk')
                ->references('id')->on('talento_hallazgo_categorias')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_hallazgos');
    }
};
