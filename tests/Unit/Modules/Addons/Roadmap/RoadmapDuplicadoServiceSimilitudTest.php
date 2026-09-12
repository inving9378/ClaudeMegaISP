<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Services\RoadmapDuplicadoService;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD — igual
// que GuardIdExplicitoRoadmapItemsTest de este mismo directorio. `similitud()`/`normalizar()` son
// funciones puras (sin Eloquent), así que no hace falta bootear el framework para probarlas.

/**
 * Item roadmap #9990999 — CIRC-02 prevención (q2 de #9990903): dedupe por similitud de
 * título/descripción al crear items en la bandeja.
 *
 * Cubre el cálculo puro de similitud (`RoadmapDuplicadoService::similitud()`), incluido el caso
 * REAL que motivó el item — #9990854 vs #9990874, dos items raíz con ~19 min de diferencia y
 * título casi idéntico que nadie notó hasta ejecutar uno. La parte con BD (`buscar()`, que
 * consulta `roadmap_items`) se verificó a mano en dev dentro de una transacción con rollback —
 * mismo criterio que el guard de id explícito de este directorio.
 */
class RoadmapDuplicadoServiceSimilitudTest extends TestCase
{
    public function test_detecta_el_caso_real_9990854_vs_9990874(): void
    {
        // Títulos reales de los dos items raíz duplicados que motivaron este item.
        $a = 'corrección del Circuito CC — para desatorar el flujo';
        $b = 'Items de corrección del Circuito CC — para desatorar el flujo';

        $pct = RoadmapDuplicadoService::similitud($a, $b);

        $this->assertGreaterThanOrEqual(
            RoadmapDuplicadoService::UMBRAL_SIMILITUD,
            $pct,
            'El detector debía marcar #9990854/#9990874 como posible duplicado (>=80%).'
        );
    }

    public function test_no_marca_titulos_genuinamente_distintos(): void
    {
        $a = 'Migrar la tabla de inventario de OLTs a un nuevo motor de reportes';
        $b = 'El comentario de Irving en un item requiere_irving es la respuesta';

        $pct = RoadmapDuplicadoService::similitud($a, $b);

        $this->assertLessThan(
            RoadmapDuplicadoService::UMBRAL_SIMILITUD,
            $pct,
            'Dos items sobre temas distintos no deben quedar marcados como duplicados.'
        );
    }

    public function test_ignora_diferencias_de_mayusculas_acentos_y_espacios(): void
    {
        $a = 'Corrección   del  Circuito CC';
        $b = 'correccion del circuito cc';

        $pct = RoadmapDuplicadoService::similitud($a, $b);

        $this->assertGreaterThanOrEqual(
            RoadmapDuplicadoService::UMBRAL_SIMILITUD,
            $pct,
            'La normalización debe plegar acentos/mayúsculas/espacios repetidos antes de comparar.'
        );
    }

    public function test_el_umbral_de_la_decision_de_irving_sigue_en_80(): void
    {
        // q2 de #9990903, opción 1 recomendada: "más de 80% de similitud". Si esto cambia, debe
        // ser una decisión explícita, no una deriva silenciosa del valor.
        $this->assertSame(80.0, RoadmapDuplicadoService::UMBRAL_SIMILITUD);
    }
}
