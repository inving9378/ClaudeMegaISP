<?php

namespace Tests\Unit\Roadmap;

use App\Modules\Addons\Roadmap\Services\Descomposicion\DependenciaGate;
use PHPUnit\Framework\TestCase;

/**
 * PIEZA C (#334, rescatada por #629) — test del GATE de dependencias. PURO (extiende
 * PHPUnit\Framework\TestCase, NO el TestCase de Laravel) → NO dispara `migrate:fresh` ni toca la
 * BD de dev.
 *
 * Caso crítico: una sección de FRONTEND que depende del BACKEND (posición previa) NO es elegible
 * hasta que el backend esté 'completado' → nunca corre en desorden ni rompe el build.
 */
class DependenciaGateTest extends TestCase
{
    private DependenciaGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new DependenciaGate();
    }

    /** CRÍTICO: frontend (depende de la pos 1 = backend) NO se libera antes que el backend. */
    public function test_frontend_no_se_libera_antes_que_su_backend(): void
    {
        $dependeDeFrontend = [1]; // la sección 2 (frontend) depende de la 1 (backend)

        // Backend AÚN no completado → frontend BLOQUEADO.
        $this->assertFalse(
            $this->gate->elegiblePorEstados($dependeDeFrontend, [1 => 'en_progreso']),
            'El frontend NO debe ser elegible mientras el backend está en_progreso.'
        );
        $this->assertFalse(
            $this->gate->elegiblePorEstados($dependeDeFrontend, [1 => 'pendiente_revision']),
            'El frontend NO debe ser elegible mientras el backend está pendiente.'
        );
        $this->assertSame(
            [1],
            $this->gate->bloqueadaPor($dependeDeFrontend, [1 => 'en_progreso']),
            'El diagnóstico debe reportar que la posición 1 (backend) bloquea.'
        );

        // Backend completado → frontend LIBERADO.
        $this->assertTrue(
            $this->gate->elegiblePorEstados($dependeDeFrontend, [1 => 'completado']),
            'El frontend debe ser elegible cuando el backend está completado.'
        );
        $this->assertSame([], $this->gate->bloqueadaPor($dependeDeFrontend, [1 => 'completado']));
    }

    /** Sección raíz (sin dependencias) es elegible siempre. */
    public function test_seccion_raiz_siempre_elegible(): void
    {
        $this->assertTrue($this->gate->elegiblePorEstados([], []));
        $this->assertSame([], $this->gate->bloqueadaPor([], []));
    }

    /** Cadena 1←2←3: cada eslabón espera a TODAS sus predecesoras. */
    public function test_cadena_secuencial_respeta_el_orden(): void
    {
        // Estado: sólo la 1 completada.
        $estados = [1 => 'completado', 2 => 'en_progreso', 3 => 'pendiente_revision'];

        $this->assertTrue($this->gate->elegiblePorEstados([], $estados), 'Sec 1 (raíz) elegible.');
        $this->assertTrue($this->gate->elegiblePorEstados([1], $estados), 'Sec 2 elegible: su predecesora 1 está completada.');
        $this->assertFalse($this->gate->elegiblePorEstados([2], $estados), 'Sec 3 NO elegible: la 2 no está completada.');
    }

    /** Dependencia múltiple (diamante): elegible sólo si TODAS las predecesoras completaron. */
    public function test_dependencia_multiple_exige_todas(): void
    {
        $dependeDe = [1, 2]; // la sección 3 depende de la 1 Y la 2

        $this->assertFalse(
            $this->gate->elegiblePorEstados($dependeDe, [1 => 'completado', 2 => 'en_progreso']),
            'Con una sola predecesora completada NO basta.'
        );
        $this->assertSame([2], $this->gate->bloqueadaPor($dependeDe, [1 => 'completado', 2 => 'en_progreso']));

        $this->assertTrue(
            $this->gate->elegiblePorEstados($dependeDe, [1 => 'completado', 2 => 'completado']),
            'Con AMBAS predecesoras completadas, elegible.'
        );
    }

    /** Predecesora ausente del mapa de estados = NO completada = bloquea (fail-closed). */
    public function test_predecesora_ausente_bloquea(): void
    {
        $this->assertFalse(
            $this->gate->elegiblePorEstados([1], []),
            'Si no hay información de la predecesora, se bloquea (fail-closed).'
        );
    }

    /** (a) Sin aristas -> no hay ciclo. */
    public function test_tiene_ciclo_sin_aristas(): void
    {
        $this->assertFalse($this->gate->tieneCiclo(1, []));
        $this->assertSame([], $this->gate->caminoCiclo(1, []));
    }

    /** (b) Cadena lineal 3->2->1 sin ciclo -> false. */
    public function test_tiene_ciclo_cadena_lineal_sin_ciclo(): void
    {
        $edges = [3 => [2], 2 => [1]];

        $this->assertFalse($this->gate->tieneCiclo(3, $edges));
        $this->assertSame([], $this->gate->caminoCiclo(3, $edges));
    }

    /** (c) Ciclo directo 1->2->1 -> true, con el camino exacto. */
    public function test_tiene_ciclo_directo(): void
    {
        $edges = [1 => [2], 2 => [1]];

        $this->assertTrue($this->gate->tieneCiclo(1, $edges));
        $this->assertSame([1, 2, 1], $this->gate->caminoCiclo(1, $edges));
    }

    /** (d) Ciclo indirecto 1->2->3->1 -> true, con el camino exacto. */
    public function test_tiene_ciclo_indirecto(): void
    {
        $edges = [1 => [2], 2 => [3], 3 => [1]];

        $this->assertTrue($this->gate->tieneCiclo(1, $edges));
        $this->assertSame([1, 2, 3, 1], $this->gate->caminoCiclo(1, $edges));
    }

    /** (e) Múltiples componentes: solo detecta el ciclo alcanzable DESDE el nodo de interés. */
    public function test_tiene_ciclo_solo_detecta_lo_alcanzable_desde_el_nodo(): void
    {
        // Componente A: 1<->2 con ciclo. Componente B: 10->11 sin ciclo, sin conexión con A.
        $edges = [1 => [2], 2 => [1], 10 => [11], 11 => []];

        $this->assertTrue($this->gate->tieneCiclo(1, $edges), 'Desde el nodo 1 sí se alcanza el ciclo de su componente.');
        $this->assertFalse($this->gate->tieneCiclo(10, $edges), 'Desde el nodo 10 NO se alcanza el ciclo de la otra componente.');
        $this->assertSame([], $this->gate->caminoCiclo(10, $edges));
    }
}
