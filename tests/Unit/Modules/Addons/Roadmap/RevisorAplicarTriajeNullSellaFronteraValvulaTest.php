<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use App\Modules\Addons\Roadmap\Services\ValvulaContextoService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;
use Tests\CreatesApplication;

/**
 * CANDADO — Defecto 1 de #902 (Válvula Fase 5, sub-item de #905).
 *
 * QUÉ PASÓ. `RevisorService::aplicarTriajeNull()` arma el log `valvula_contexto` cada vez que la
 * válvula de contexto (`afinarConValvula()`) fue consultada, pero —a diferencia de la válvula de
 * NACIMIENTO en `RoadmapController::store` (líneas ~2752-2753), que sí sella la columna— nunca
 * escribía `$item->frontera_valvula`/`frontera_valvula_at`. El log quedaba como la única fuente de
 * verdad, sin la columna que el resto del sistema lee (`DiagnosticoItemService`, la Torre). La Fase
 * 2 (#975, commit `c1be2472`) agregó el sellado en el mismo bloque que arma el log. Este test es el
 * candado de regresión de esa Fase 2: si alguien vuelve a tocar `aplicarTriajeNull()` y el sellado
 * se cae del bloque (por accidente, refactor o merge), este archivo debe fallar.
 *
 * PATRÓN DE TEST — mismo que `DiagnosticoItemServiceTest` (ver su docblock): bootea el contenedor
 * de Laravel vía `CreatesApplication` (lo necesita `afinarConValvula()` para `app(ValvulaContexto
 * Service::class)`), NUNCA `Tests\TestCase` (su `setUp()` corre `migrate:fresh --seed`). A
 * diferencia de `DiagnosticoItemService::para()` —solo lectura—, `aplicarTriajeNull()` SÍ llama
 * `$item->save()` (dos veces: la propia y, en la rama `requiere_irving`, la de `sellarHuecos()`) y
 * termina en `$item->fresh()` (una consulta real por PK). Para correr el método REAL sin tocar la
 * BD: `RoadmapItem` es un mock PARCIAL de Mockery con `save()`/`fresh()` interceptados (no-op /
 * `andReturnSelf()`) — todo lo demás (casts de `log`, `frontera_valvula_at`, etc.) sigue siendo
 * Eloquent real, en memoria. Los dos casos usan `estado='pendiente_revision'` en el `$t` de entrada
 * a propósito: así nunca se entra a la rama `requiere_irving` de `aplicarTriajeNull()`, que
 * dispararía `ProponerOpcionesJob::dispatch()` (cola real) — el sellado que se prueba aquí no
 * depende del estado (ver el comentario del propio método: "Cubre 'mencion' y 'accion' por igual").
 */
class RevisorAplicarTriajeNullSellaFronteraValvulaTest extends BaseTestCase
{
    use CreatesApplication;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function itemMockeado(): RoadmapItem
    {
        $item = Mockery::mock(RoadmapItem::class)->makePartial();
        $item->shouldReceive('save')->andReturn(true);
        $item->shouldReceive('fresh')->andReturnSelf();

        $item->id                = 999977;
        $item->title              = 'Rotar la contraseña de un servicio externo';
        $item->description        = 'cambiar credenciales de acceso';
        $item->comentarios_claude = '';
        $item->log                = [];

        return $item;
    }

    private function mockearValvula(array $veredicto): void
    {
        $mock = Mockery::mock(ValvulaContextoService::class);
        $mock->shouldReceive('evaluar')->once()->andReturn($veredicto);
        $this->app->instance(ValvulaContextoService::class, $mock);
    }

    /** Caso 1/2 — veredicto 'mencion' (afloja C→B): debe sellar la columna, no solo el log. */
    public function test_veredicto_mencion_sella_frontera_valvula(): void
    {
        $this->mockearValvula([
            'afloja' => true, 'ok' => true, 'veredicto' => ValvulaContextoService::MENCION,
            'razon' => 'solo se menciona el término, no se usa', 'modelo' => 'claude-test',
        ]);

        $item = $this->itemMockeado();
        $t = ['nivel' => 'C', 'estado' => 'pendiente_revision', 'match' => 'credenciales', 'motivo' => 'match de prueba'];

        $out = app(RevisorService::class)->aplicarTriajeNull($item, $t);

        $this->assertNotNull($out->frontera_valvula,
            'aplicarTriajeNull() dejó de sellar frontera_valvula en un veredicto de MENCIÓN — '
            . 'regresión exacta del Defecto 1 de #902.');
        $this->assertSame(ValvulaContextoService::MENCION, $out->frontera_valvula);
        $this->assertNotNull($out->frontera_valvula_at);
    }

    /** Caso 2/2 — veredicto 'accion' (NO afloja, manda el keyword): también debe sellar. */
    public function test_veredicto_accion_tambien_sella_frontera_valvula(): void
    {
        $this->mockearValvula([
            'afloja' => false, 'ok' => true, 'veredicto' => ValvulaContextoService::ACCION,
            'razon' => 'el término se usa de verdad, no solo se menciona', 'modelo' => 'claude-test',
        ]);

        $item = $this->itemMockeado();
        $t = ['nivel' => 'C', 'estado' => 'pendiente_revision', 'match' => 'credenciales', 'motivo' => 'match de prueba'];

        $out = app(RevisorService::class)->aplicarTriajeNull($item, $t);

        $this->assertNotNull($out->frontera_valvula,
            'aplicarTriajeNull() dejó de sellar frontera_valvula en un veredicto de ACCIÓN — '
            . 'regresión exacta del Defecto 1 de #902.');
        $this->assertSame(ValvulaContextoService::ACCION, $out->frontera_valvula);
        $this->assertNotNull($out->frontera_valvula_at);
    }

    /**
     * Sanity — sin match, `afinarConValvula()` ni siquiera consulta la válvula (guarda de
     * `afinarConValvula`: nivel≠C o match vacío ⇒ retorna temprano), así que la columna queda
     * intacta. Sin binding de `ValvulaContextoService`: si el código la llamara igual, este test lo
     * detectaría (intentaría resolver el servicio real, sin mock).
     */
    public function test_sin_match_no_se_consulta_la_valvula_ni_se_sella(): void
    {
        $item = $this->itemMockeado();
        $t = ['nivel' => 'B', 'estado' => 'pendiente_revision', 'match' => null, 'motivo' => 'sin match'];

        $out = app(RevisorService::class)->aplicarTriajeNull($item, $t);

        $this->assertNull($out->frontera_valvula);
        $this->assertNull($out->frontera_valvula_at);
    }
}
