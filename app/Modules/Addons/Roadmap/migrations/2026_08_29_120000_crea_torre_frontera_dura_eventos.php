<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pieza 1a (#764, sub-item de #672) — bitácora dedicada de disparos de frontera dura.
 *
 * POR QUÉ EXISTE. Hasta hoy el único rastro de que la válvula (`ValvulaContextoService`/
 * `RoadmapController::store`) sella un item como MENCIÓN vive mezclado dentro de
 * `roadmap_items.log` (JSON) y `comentarios_claude` (texto libre) — auditar "cuántas veces se
 * ablandó la frontera `dinero`" exige recorrer el JSON de cada item a mano. Esta tabla es una
 * PROYECCIÓN de lectura de esos mismos eventos, no una fuente nueva: `roadmap_items.log` sigue
 * siendo la fuente de verdad; esta tabla solo la hace consultable con SQL normal (filtros de
 * Torre, Pieza 1c). Aditiva y reversible: crear/borrar la tabla no toca `log` ni la frontera viva.
 *
 * ORIGEN: `origen` distingue filas escritas por el backfill histórico (Pieza 1a,
 * `circuito:backfill-frontera-dura-eventos`) de las que capture en vivo la Pieza 1b (aún no
 * existe) — para no confundir "medido después" con "medido en el momento".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('torre_frontera_dura_eventos')) {
            return;
        }

        Schema::create('torre_frontera_dura_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_item_id')->constrained('roadmap_items')->cascadeOnDelete();

            // dinero | seguridad | prod | datos | negocio — mismo vocabulario que
            // `circuito_fronteras.categoria` (#648). String y no enum: agregar una categoría no
            // debe pedir un ALTER (misma decisión que el resto de las columnas-perilla del circuito).
            $table->string('categoria', 40);

            $table->string('termino', 120);

            // mencion | accion — el veredicto de la válvula tal como lo escribe
            // `RevisorService::afinarConValvula()` / el alta de `RoadmapController::store`.
            $table->string('veredicto', 20);

            $table->text('razon')->nullable();

            $table->dateTime('ocurrido_at');

            // backfill_log | vivo — de dónde salió la fila (ver docblock arriba).
            $table->string('origen', 20)->default('backfill_log');

            $table->timestamp('created_at')->useCurrent();

            // IDEMPOTENCIA: el backfill se re-corre sin duplicar (mismo item + término + instante).
            $table->unique(['roadmap_item_id', 'termino', 'ocurrido_at'], 'torre_fde_item_termino_ts_unique');
            $table->index('categoria');
            $table->index('ocurrido_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torre_frontera_dura_eventos');
    }
};
