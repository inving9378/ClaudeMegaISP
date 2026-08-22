<?php

namespace Tests\Unit\Services\Ipv6;

use App\Modules\Addons\Ipv6\Models\Ipv6RenumberingPlan;
use App\Services\Ipv6\Exceptions\Ipv6RenumberingStateException;
use App\Services\Ipv6\Ipv6RenumberingStateMachine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Ipv6RenumberingStateMachineTest extends TestCase
{
    use DatabaseTransactions;

    private function crearPlan(array $overrides = []): Ipv6RenumberingPlan
    {
        return Ipv6RenumberingPlan::create(array_merge([
            'bloque_viejo' => '2001:db8:aaaa::/48',
            'bloque_nuevo' => '2001:db8:bbbb::/48',
            'estado' => 'planificado',
        ], $overrides));
    }

    public function test_transicion_adyacente_valida_actualiza_estado_y_registra_fila(): void
    {
        $plan = $this->crearPlan();
        $fsm = new Ipv6RenumberingStateMachine();

        $resultado = $fsm->transition($plan, 'activo', 'arranca el overlap');

        $this->assertSame('activo', $resultado->estado);
        $this->assertSame('activo', $plan->fresh()->estado);

        $fila = $plan->transitions()->latest('id')->first();
        $this->assertNotNull($fila);
        $this->assertSame('planificado', $fila->de_estado);
        $this->assertSame('activo', $fila->a_estado);
        $this->assertSame('arranca el overlap', $fila->nota);
    }

    public function test_transicion_con_salto_lanza_excepcion_y_no_modifica_nada(): void
    {
        $plan = $this->crearPlan();
        $fsm = new Ipv6RenumberingStateMachine();

        $this->expectException(Ipv6RenumberingStateException::class);

        try {
            $fsm->transition($plan, 'retirado');
        } finally {
            $this->assertSame('planificado', $plan->fresh()->estado);
            $this->assertSame(0, $plan->transitions()->count());
        }
    }

    public function test_bloquea_retirado_si_falta_morosos_reconstruido(): void
    {
        $plan = $this->crearPlan([
            'estado' => 'en_deprecacion',
            'historico_preservado' => true,
            'simple_queues_actualizado_at' => now(),
            'morosos_reconstruido_at' => null,
        ]);
        $fsm = new Ipv6RenumberingStateMachine();

        try {
            $fsm->transition($plan, 'retirado');
            $this->fail('Debió lanzar Ipv6RenumberingStateException');
        } catch (Ipv6RenumberingStateException $e) {
            $this->assertStringContainsString('morosos_reconstruido_at', $e->getMessage());
        }

        $this->assertSame('en_deprecacion', $plan->fresh()->estado);
    }

    public function test_bloquea_retirado_si_historico_no_preservado(): void
    {
        $plan = $this->crearPlan([
            'estado' => 'en_deprecacion',
            'historico_preservado' => false,
            'simple_queues_actualizado_at' => now(),
            'morosos_reconstruido_at' => now(),
        ]);
        $fsm = new Ipv6RenumberingStateMachine();

        try {
            $fsm->transition($plan, 'retirado');
            $this->fail('Debió lanzar Ipv6RenumberingStateException');
        } catch (Ipv6RenumberingStateException $e) {
            $this->assertStringContainsString('historico_preservado', $e->getMessage());
        }

        $this->assertSame('en_deprecacion', $plan->fresh()->estado);
    }

    public function test_bloquea_retirado_si_falta_simple_queues_actualizado(): void
    {
        $plan = $this->crearPlan([
            'estado' => 'en_deprecacion',
            'historico_preservado' => true,
            'simple_queues_actualizado_at' => null,
            'morosos_reconstruido_at' => now(),
        ]);
        $fsm = new Ipv6RenumberingStateMachine();

        try {
            $fsm->transition($plan, 'retirado');
            $this->fail('Debió lanzar Ipv6RenumberingStateException');
        } catch (Ipv6RenumberingStateException $e) {
            $this->assertStringContainsString('simple_queues_actualizado_at', $e->getMessage());
        }

        $this->assertSame('en_deprecacion', $plan->fresh()->estado);
    }

    public function test_bloquea_retirado_lista_los_tres_puntos_si_faltan_todos(): void
    {
        $plan = $this->crearPlan(['estado' => 'en_deprecacion', 'historico_preservado' => false]);
        $fsm = new Ipv6RenumberingStateMachine();

        try {
            $fsm->transition($plan, 'retirado');
            $this->fail('Debió lanzar Ipv6RenumberingStateException');
        } catch (Ipv6RenumberingStateException $e) {
            $this->assertStringContainsString('morosos_reconstruido_at', $e->getMessage());
            $this->assertStringContainsString('historico_preservado', $e->getMessage());
            $this->assertStringContainsString('simple_queues_actualizado_at', $e->getMessage());
        }
    }

    public function test_transicion_a_retirado_procede_cuando_los_tres_puntos_estan_completos(): void
    {
        $plan = $this->crearPlan([
            'estado' => 'en_deprecacion',
            'historico_preservado' => true,
            'simple_queues_actualizado_at' => now(),
            'morosos_reconstruido_at' => now(),
        ]);
        $fsm = new Ipv6RenumberingStateMachine();

        $resultado = $fsm->transition($plan, 'retirado');

        $this->assertSame('retirado', $resultado->estado);
    }

    public function test_rollback_exitoso_desde_activo_regresa_a_planificado(): void
    {
        $plan = $this->crearPlan();
        $fsm = new Ipv6RenumberingStateMachine();
        $fsm->transition($plan, 'activo');

        $resultado = $fsm->rollback($plan);

        $this->assertSame('planificado', $resultado->estado);
        $fila = $plan->transitions()->latest('id')->first();
        $this->assertSame('activo', $fila->de_estado);
        $this->assertSame('planificado', $fila->a_estado);
        // Historial nunca se borra: siguen existiendo las 2 filas (transición + rollback).
        $this->assertSame(2, $plan->transitions()->count());
    }

    public function test_rollback_exitoso_desde_en_deprecacion_regresa_a_activo(): void
    {
        $plan = $this->crearPlan();
        $fsm = new Ipv6RenumberingStateMachine();
        $fsm->transition($plan, 'activo');
        $fsm->transition($plan, 'en_deprecacion');

        $resultado = $fsm->rollback($plan);

        $this->assertSame('activo', $resultado->estado);
    }

    public function test_rollback_exitoso_desde_planificado_tras_un_rollback_previo(): void
    {
        $plan = $this->crearPlan();
        $fsm = new Ipv6RenumberingStateMachine();
        $fsm->transition($plan, 'activo');
        $fsm->rollback($plan); // vuelve a planificado, deja una fila de rollback en el historial

        $this->assertSame('planificado', $plan->fresh()->estado);

        $resultado = $fsm->rollback($plan);

        $this->assertSame('activo', $resultado->estado);
        $this->assertSame(3, $plan->transitions()->count());
    }

    public function test_rollback_sin_transiciones_previas_lanza_excepcion(): void
    {
        $plan = $this->crearPlan();
        $fsm = new Ipv6RenumberingStateMachine();

        $this->expectException(Ipv6RenumberingStateException::class);
        $fsm->rollback($plan);
    }

    public function test_rollback_bloqueado_en_retirado_no_modifica_nada(): void
    {
        $plan = $this->crearPlan([
            'estado' => 'en_deprecacion',
            'historico_preservado' => true,
            'simple_queues_actualizado_at' => now(),
            'morosos_reconstruido_at' => now(),
        ]);
        $fsm = new Ipv6RenumberingStateMachine();
        $fsm->transition($plan, 'retirado');
        $transicionesAntes = $plan->transitions()->count();

        try {
            $fsm->rollback($plan);
            $this->fail('Debió lanzar Ipv6RenumberingStateException');
        } catch (Ipv6RenumberingStateException $e) {
            $this->assertStringContainsString('requiere intervención manual', $e->getMessage());
        }

        $this->assertSame('retirado', $plan->fresh()->estado);
        $this->assertSame($transicionesAntes, $plan->transitions()->count());
    }
}
