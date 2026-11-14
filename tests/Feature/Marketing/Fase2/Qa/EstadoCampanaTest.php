<?php

namespace Tests\Feature\Marketing\Fase2\Qa;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\EscenarioFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 2 — activar / pausar / terminar (contrato §2.4 ruta 6).
 * Transiciones válidas: draft→active (exige estrategia lista), active→paused, paused→active,
 * active|paused→finished, draft|paused→canceled. Cualquier otra → 422 `transicion_invalida`.
 * La campaña activa aparece en el panel (`dashboard/campanas-activas`) con su presupuesto en MXN.
 */
class EstadoCampanaTest extends MarketingTestCase
{
    use EscenarioFase2;

    private const VALIDAS = [
        'draft' => ['active', 'canceled'],
        'active' => ['paused', 'finished'],
        'paused' => ['active', 'finished', 'canceled'],
        'finished' => [],
        'canceled' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioFase2(false);
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function cambiar(int $id, $estado): \Illuminate\Testing\TestResponse
    {
        return $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/estado", ['estado' => $estado]);
    }

    public function test_matriz_completa_de_transiciones(): void
    {
        $errores = [];
        foreach (self::VALIDAS as $desde => $validas) {
            foreach (array_keys(self::VALIDAS) as $hacia) {
                if ($hacia === $desde) {
                    continue;
                }
                $c = $this->campanaDirecta(1, ['status' => $desde, 'estrategia_estado' => 'lista', 'estrategia' => $this->fixture('estrategia-valida.json')]);
                $res = $this->cambiar($c->id, $hacia);
                $ok = in_array($hacia, $validas, true);
                if ($ok && $res->getStatusCode() !== 200) {
                    $errores[] = "{$desde}→{$hacia} debía pasar: " . $this->etiqueta($res);
                }
                if ($ok && $res->getStatusCode() === 200 && $res->json('data.estado') !== $hacia) {
                    $errores[] = "{$desde}→{$hacia}: devolvió estado " . $res->json('data.estado');
                }
                if (! $ok && ($res->getStatusCode() !== 422 || $res->json('error') !== 'transicion_invalida')) {
                    $errores[] = "{$desde}→{$hacia} debía dar 422 transicion_invalida: " . $this->etiqueta($res);
                }
                if (! $ok) {
                    $this->assertSame($desde, DB::table('marketing_campaigns')->where('id', $c->id)->value('status'));
                }
            }
        }
        $this->assertSame([], $errores, implode("\n", $errores));
    }

    public function test_mensaje_de_transicion_invalida_en_espanol(): void
    {
        $c = $this->campanaDirecta(1, ['status' => 'draft']);
        $this->cambiar($c->id, 'finished')->assertStatus(422)
            ->assertJson(['ok' => false, 'error' => 'transicion_invalida', 'mensaje' => 'No se puede pasar de Borrador a Terminada.']);
    }

    public function test_activar_sin_estrategia_lista_da_422_sin_estrategia(): void
    {
        foreach (['sin_generar', 'generando', 'error'] as $e) {
            $c = $this->campanaDirecta(1, ['status' => 'draft', 'estrategia_estado' => $e]);
            $this->cambiar($c->id, 'active')->assertStatus(422)->assertJsonPath('error', 'sin_estrategia');
            $this->assertSame('draft', DB::table('marketing_campaigns')->where('id', $c->id)->value('status'));
        }
    }

    public function test_estados_raros_dan_422(): void
    {
        $c = $this->campanaDirecta(1, ['status' => 'draft', 'estrategia_estado' => 'lista']);
        foreach (['ACTIVE', 'borrador', '', null, ['active'], "active' OR 1=1", 'deleted'] as $e) {
            $res = $this->cambiar($c->id, $e);
            if ($res->getStatusCode() === 403 && str_contains((string) $res->getContent(), 'bloqueada por seguridad')) {
                continue; // WAF global del sistema (SqlInjectionProtection): correcto, no llega al controlador
            }
            $this->assertSame(422, $res->getStatusCode(), json_encode($e) . ' → ' . $this->etiqueta($res));
        }
        $this->assertSame('draft', DB::table('marketing_campaigns')->where('id', $c->id)->value('status'));
    }

    public function test_la_campana_activa_aparece_en_el_panel_con_presupuesto_mxn(): void
    {
        $c = $this->campanaDirecta(1, ['status' => 'draft', 'estrategia_estado' => 'lista', 'budget' => '15000.50', 'name' => 'QA2 Activa panel',
            'start_date' => $this->hoy()->toDateString(), 'estrategia' => $this->fixture('estrategia-valida.json')]);
        $this->cambiar($c->id, 'active')->assertStatus(200)->assertJsonPath('data.estado_etiqueta', 'Activa')->assertJsonPath('data.presupuesto_mxn', '15000.50');
        $panel = $this->como($this->admin)->getJson('/api/marketing/dashboard/campanas-activas');
        $this->assertSame(200, $panel->getStatusCode(), $this->etiqueta($panel));
        $this->assertStringContainsString('QA2 Activa panel', (string) $panel->getContent(), 'La campaña activa aparece en el panel');
        $this->assertStringContainsString('15000.50', (string) $panel->getContent(), 'Presupuesto en MXN con 2 decimales');
    }

    public function test_doble_clic_en_activar_no_rompe(): void
    {
        $c = $this->campanaDirecta(1, ['status' => 'draft', 'estrategia_estado' => 'lista', 'estrategia' => $this->fixture('estrategia-valida.json')]);
        $this->cambiar($c->id, 'active')->assertStatus(200);
        $this->cambiar($c->id, 'active')->assertStatus(422)->assertJsonPath('error', 'transicion_invalida');
        $this->assertSame('active', DB::table('marketing_campaigns')->where('id', $c->id)->value('status'));
    }
}
