<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REANUDACIÓN POR TIMEOUT — restaurar, no adivinar.
 *
 * Una vuelta se corta a los 600 s. Hasta hoy, el item que timeouteaba iba a `requiere_irving`
 * (`vuelta.sh`, firma `timeout`) para no re-encolarse y volver a quemar 600 s de Max. La intención
 * es correcta, pero trataba igual dos casos muy distintos: el item que **avanzó** (abrió rama y
 * commiteó) y el que **giró en vacío**. `scopeOrdenCola` ya prioriza explícitamente los items con
 * rama como "POR CONCLUIRSE / REANUDABLES" — o sea, el anti-quemado estaba apagando justo el
 * mecanismo de reanudación que el sistema ya tenía.
 *
 * Reanudar exige saber A DÓNDE volver, y ese dato no se guardaba en ninguna parte: el reclamo
 * sobrescribe `estado_aprobacion` con `en_progreso` y el valor anterior se pierde. Elegirlo por
 * heurística sería un ascenso encubierto (un item que llegó por `aprobado_revisor` no debe volver
 * como `aprobado_irving`). Por eso se GUARDA, en el mismo UPDATE atómico del reclamo:
 *
 *   · `estado_previo_claim`     → de dónde vino. Al reanudar se restaura tal cual.
 *   · `reanudaciones_timeout`   → cuántas veces se le dio otra vuelta. A la 2ª, deja de reanudarse
 *                                 solo y pasa a la bandeja de Irving CON el motivo. Es el tope que
 *                                 conserva intacta la protección de quemado.
 *
 * El contador no es sólo un freno: un item en su segunda reanudación es INFORMACIÓN — significa que
 * es más grande de lo que parecía. Por eso se muestra en la Torre en vez de vivir sólo en el log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'estado_previo_claim')) {
                // varchar y no enum: es una FOTO de un valor de `estado_aprobacion`, no una segunda
                // fuente de verdad. Si mañana se agrega un estado, esta columna no debe ser el sitio
                // que hay que acordarse de migrar para que el reclamo no truene.
                $table->string('estado_previo_claim', 32)->nullable()->after('claimed_at');
            }
            if (! Schema::hasColumn('roadmap_items', 'reanudaciones_timeout')) {
                $table->unsignedSmallInteger('reanudaciones_timeout')->default(0)->after('estado_previo_claim');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            foreach (['estado_previo_claim', 'reanudaciones_timeout'] as $col) {
                if (Schema::hasColumn('roadmap_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
