<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * FASE 2A.5 — PRECEDENCIA del guard #456, escrita y verificada.
 *
 * Al ampliar el guard a `aprobado_irving`, las dos direcciones quedan activas a la vez:
 *
 *   (A) status → estado_aprobacion   (el Kanban legado sólo muta `status`)
 *   (B) estado_aprobacion → status   (hook de `completado`, parqueo de C-con-rama)
 *
 * REGLA: **gana `estado_aprobacion`**. Es la máquina de estados real del circuito; `status` es el
 * espejo Kanban. (A) sólo actúa si el llamador NO tocó `estado_aprobacion` en el mismo save.
 *
 * Es el único punto donde este arreglo podía volverse un bucle o un pisotón silencioso, así que la
 * precedencia vive en el código (early return explícito) y no en el orden de registro de los hooks
 * — que es un detalle frágil, invisible en el diff y que ya se documentó como delicado en 2A.4.
 *
 * Comportamiento verificado en dev dentro de una transacción con rollback (2026-08-18):
 *   aprobado_irving + status=done              → completado / done
 *   pendiente_revision + status=in_progress    → en_progreso / in_progress
 *   status=done + estado=requiere_irving       → requiere_irving / done   ← gana estado
 *   estado=completado (solo)                   → completado / done        ← dirección B
 *   aprobado_revisor + status=done             → aprobado_revisor / done  ← veredicto intacto
 */
class GuardKanbanPrecedenciaTest extends TestCase
{
    /** El Kanban arrastra desde los estados "de tablero", nunca desde un veredicto del circuito. */
    public function test_el_kanban_solo_arrastra_desde_estados_de_tablero(): void
    {
        $set = RoadmapItem::ESTADOS_SINCRONIZABLES_DESDE_KANBAN;

        $this->assertContains('aprobado_irving', $set,
            'Se quitó `aprobado_irving` del guard #456 (2A.5). Es donde vive la mayoría de lo ya '
            . 'autorizado: sin él, mover la tarjeta a "Hecho" no cierra nada.');

        foreach (['aprobado_claude', 'aprobado_revisor', 'requiere_irving', 'completado',
                  'cancelado', 'rechazado'] as $veredicto) {
            $this->assertNotContains($veredicto, $set,
                "`{$veredicto}` entró al guard del Kanban. Mover una tarjeta en un tablero NO puede "
                . 'deshacer un veredicto del revisor ni una decisión ya cerrada.');
        }
    }

    /**
     * La precedencia tiene que estar ESCRITA: el guard debe cortar cuando el llamador ya tocó
     * `estado_aprobacion`. Sin ese corte, `decidir()`, `integracionRechazo()` y
     * `MergeRunner::markMerged()` —que escriben los dos campos en el mismo save— quedarían a merced
     * del orden de registro de los hooks.
     */
    public function test_la_precedencia_esta_escrita_en_el_guard_no_en_el_orden_de_los_hooks(): void
    {
        $src = file_get_contents(dirname(__DIR__, 5) . '/app/Modules/Addons/Roadmap/Models/RoadmapItem.php');

        $pos = strpos($src, 'ESTADOS_SINCRONIZABLES_DESDE_KANBAN, true)');
        $this->assertNotFalse($pos, 'No encontré el guard #456 — ¿se reescribió? Revisa la precedencia.');

        // El early return de precedencia tiene que estar en el MISMO closure, ANTES de la condición.
        $antes = substr($src, max(0, $pos - 600), 600);
        $this->assertMatchesRegularExpression(
            "/if \(\\\$item->isDirty\('estado_aprobacion'\)\) \{\s*return;/",
            $antes,
            "El guard #456 perdió su corte de PRECEDENCIA (`if (\$item->isDirty('estado_aprobacion')) return;`). "
            . 'Sin él, un save que cambia los dos campos deja que el espejo Kanban pise la decisión '
            . 'explícita del llamador — en silencio, que es lo peor de las dos formas de fallar.'
        );
    }
}
