<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-09 — `mapared_cables` + `mapared_hilos` (aditiva, item roadmap #945).
 *
 * El cable deja de ser una línea dibujada (mapared_layers) y pasa a ser una entidad propia con
 * estructura interna: al guardarse, `MapaRedCable::booted()` auto-instancia sus hilos (buffers +
 * colores) vía `CableStructureService`.
 *
 * `tipo_cable_id` es referencia BLANDA (sin FK real) a `mapared_tipo_cable`: ese catálogo lo
 * construye MR-08 (#944) en paralelo y todavía no existe al momento de este commit. Mientras
 * tanto `numero_hilos`/`hilos_por_buffer` se capturan directo en el cable (snapshot), mismo
 * patrón que `talento_work_orders.points` snapshottea de `talento_work_order_types`. Cuando #944
 * aterrice, un follow-up puede resolver `tipo_cable_id` para precargar esos dos campos.
 *
 * `mapared_hilos` guarda solo columnas base (cable/buffer/color); MR-11 (#947) las extenderá con
 * `estado`/`observaciones` vía ALTER, no recreación — no se anticipan esas columnas aquí para no
 * pisar el alcance de ese item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_cables', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo_tipo')->nullable();
            $table->unsignedBigInteger('tipo_cable_id')->nullable()->index();
            $table->unsignedSmallInteger('numero_hilos');
            $table->unsignedSmallInteger('hilos_por_buffer')->default(12);
            $table->longText('geom_json')->nullable();
            $table->decimal('longitud_metros', 12, 2)->nullable();
            $table->decimal('holgura_metros', 10, 2)->default(0);
            $table->enum('estado', ['planeado', 'en_construccion', 'activo', 'retirado'])->default('planeado');
            $table->unsignedBigInteger('proyecto_id')->nullable()->index();
            $table->string('zona')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('bbox_min_lat', 10, 7)->nullable();
            $table->decimal('bbox_max_lat', 10, 7)->nullable();
            $table->decimal('bbox_min_lng', 10, 7)->nullable();
            $table->decimal('bbox_max_lng', 10, 7)->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamps();

            $table->index(['lat', 'lng']);
        });

        Schema::create('mapared_hilos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cable_id')->index();
            $table->unsignedSmallInteger('buffer');
            $table->string('buffer_color');
            $table->unsignedSmallInteger('numero');
            $table->unsignedSmallInteger('numero_global');
            $table->string('color');
            $table->timestamps();

            $table->unique(['cable_id', 'buffer', 'numero']);
            $table->foreign('cable_id')->references('id')->on('mapared_cables')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_hilos');
        Schema::dropIfExists('mapared_cables');
    }
};
