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
 * `mapared_hilos` puede haber sido creada ya por MR-11 (#947, trabajo concurrente sobre la MISMA
 * BD de dev compartida) con su propio esquema (`estado`/`observaciones` incluidos desde el
 * arranque). Esta migración es defensiva: crea la tabla completa si no existe, o si ya existe
 * (caso real detectado en dev) solo AGREGA las dos columnas que MR-09 necesita y que su esquema
 * no tenía (`buffer_color`, `numero_global`) — nunca la recrea ni la toca de forma destructiva.
 * El borrado en cascada de hilos al borrar un cable se resuelve a nivel aplicación
 * (`MapaRedCable::booted()` → `deleting`), no con una FK real, para no arriesgar romper inserts
 * concurrentes de #947 contra una tabla que no controlamos por completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mapared_cables')) {
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
        }

        if (!Schema::hasTable('mapared_hilos')) {
            Schema::create('mapared_hilos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cable_id')->index();
                $table->unsignedSmallInteger('buffer');
                $table->string('buffer_color')->nullable();
                $table->unsignedSmallInteger('numero');
                $table->unsignedSmallInteger('numero_global')->nullable();
                $table->string('color')->nullable();
                $table->timestamps();

                $table->index(['cable_id', 'buffer', 'numero']);
            });
        } else {
            Schema::table('mapared_hilos', function (Blueprint $table) {
                if (!Schema::hasColumn('mapared_hilos', 'buffer_color')) {
                    $table->string('buffer_color')->nullable()->after('buffer');
                }
                if (!Schema::hasColumn('mapared_hilos', 'numero_global')) {
                    $table->unsignedSmallInteger('numero_global')->nullable()->after('numero');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mapared_hilos', 'numero_global')) {
            Schema::table('mapared_hilos', fn (Blueprint $table) => $table->dropColumn('numero_global'));
        }
        if (Schema::hasColumn('mapared_hilos', 'buffer_color')) {
            Schema::table('mapared_hilos', fn (Blueprint $table) => $table->dropColumn('buffer_color'));
        }
        Schema::dropIfExists('mapared_cables');
    }
};
