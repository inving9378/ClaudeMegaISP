<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * Item #9990564 — candado de COMPORTAMIENTO (con BD real) que completa a
 * `ParseCurrentItemSieteDigitosTest` (parseo) y `RenovarLeaseFiltraPorItemTest` (inspección
 * estática del código fuente): aquí se simulan DOS RoadmapItem `en_progreso` con el MISMO
 * `worker_sid` — uno con id de 7+ dígitos igual al `current_item` parseado del log, y otro
 * distinto — y se verifica que `renovarLease()` SOLO toca el `claimed_at` del que coincide.
 *
 * Bug original (#210/#640, reintroducido por el tope `{1,6}` de #9990562): sin filtrar por
 * `id`, un mismo `worker_sid` con más de un item `en_progreso` le renovaba el lease a AMBOS en
 * cada latido, blindando al huérfano frente al reaper para siempre.
 *
 * USA `DatabaseTransactions` + `CreatesApplication` (rollback al terminar), mismo patrón que
 * `StatusResincronizaAlEscalarTest`/`DependenciaGateDespachoTest` — no ensucia la BD compartida.
 */
class RenovarLeaseAislaEntreItemsSieteDigitosTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_renovar_lease_con_id_de_siete_digitos_solo_renueva_el_item_actual(): void
    {
        $sid = 'wt-9999';
        // `claimed_at` es `timestamp` (segundos, sin microsegundos) — truncar aquí evita un falso
        // positivo al comparar contra lo que vuelve de la BD tras el `->fresh()`.
        $haceUnaHora = now()->subHour()->startOfSecond();

        // `id`/`claimed_at` no están en $fillable (a propósito: el guard de `creating()` bloquea
        // ids explícitos fuera de tests) — `forceCreate()` los setea directo, y el guard exime a
        // la suite (`app()->runningUnitTests()`, ver `RoadmapItem::boot()`).
        //
        // Id de 7 dígitos real (rango 9990000+ del roadmap) — el que el fix de #9990562 debía
        // dejar de perder al parsear el tail del log.
        $itemActual = RoadmapItem::forceCreate([
            'id'                => 9990524,
            'title'             => 'Item actual de la vuelta (7 dígitos) '.uniqid(),
            'estado_aprobacion' => 'en_progreso',
            'worker_sid'        => $sid,
            'claimed_at'        => $haceUnaHora,
        ]);

        $itemHuerfano = RoadmapItem::forceCreate([
            'title'             => 'Item huérfano con el mismo worker_sid '.uniqid(),
            'estado_aprobacion' => 'en_progreso',
            'worker_sid'        => $sid,
            'claimed_at'        => $haceUnaHora,
        ]);

        (new RoadmapCircuitoService())->renovarLease($sid, 9990524);

        $this->assertTrue(
            $itemActual->fresh()->claimed_at->gt($haceUnaHora),
            'renovarLease() debía renovar claimed_at del item actual (id de 7 dígitos).'
        );
        $this->assertTrue(
            $itemHuerfano->fresh()->claimed_at->equalTo($haceUnaHora),
            'renovarLease() NO debía tocar claimed_at del huérfano con el mismo worker_sid — '
            . 'si lo renovó, el filtro por id se rompió y el bug de #210/#640 volvió.'
        );
    }
}
