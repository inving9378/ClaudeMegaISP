<?php

namespace Tests\Feature\Talento;

use App\Modules\Addons\Talento\Services\CorreccionOrdenValidadaService as S;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Spatie\Permission\Models\Permission;
use Tests\CreatesApplication;

/** UNI-T D7.1 (#1095): corrección de orden validada — compuertas de interruptor, permiso, motivo y orden. */
class CorreccionOrdenValidadaD71Test extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function usuario(): \App\Models\User
    {
        $u = new \App\Models\User();
        $u->forceFill(['name' => 'Prueba D71', 'login_user' => 'prueba_d71_' . uniqid(), 'email' => uniqid() . '@prueba.test', 'password' => 'x', 'estado' => 'inactivo'])->save();
        return $u;
    }

    public function test_apagado_no_corrige_nada(): void
    {
        config(['unificacion.interruptores.correccion_validada_d71' => false]);
        $r = app(S::class)->corregir('task', 1, ['puntos' => 3], 'motivo suficiente', 1);
        $this->assertFalse($r['success']);
        $this->assertSame(403, $r['status_code']);
    }

    public function test_sin_permiso_se_rechaza(): void
    {
        config(['unificacion.interruptores.correccion_validada_d71' => true]);
        $uid = $this->usuario()->id;
        $r = app(S::class)->corregir('task', 1, ['puntos' => 3], 'motivo suficiente', $uid);
        $this->assertSame(403, $r['status_code']);
    }

    public function test_exige_motivo_y_cambios(): void
    {
        config(['unificacion.interruptores.correccion_validada_d71' => true]);
        $u = $this->usuario();
        $uid = $u->id;
        $u->givePermissionTo(Permission::firstOrCreate(['name' => S::PERMISO, 'guard_name' => 'web']));
        $s = app(S::class);
        $this->assertSame(422, $s->corregir('task', 1, ['puntos' => 3], 'corto', $uid)['status_code']);
        $this->assertSame(422, $s->corregir('task', 1, [], 'motivo suficiente', $uid)['status_code']);
        $this->assertSame(404, $s->corregir('task', 999999999, ['puntos' => 3], 'motivo suficiente', $uid)['status_code']);
    }

    /** @return array{0:\App\Models\User,1:int,2:int,3:\Carbon\Carbon} usuario con permiso, colaborador, orden, instante validado */
    private function escenario(): array
    {
        config(['unificacion.interruptores.correccion_validada_d71' => true]);
        $u = $this->usuario();
        $u->givePermissionTo(Permission::firstOrCreate(['name' => S::PERMISO, 'guard_name' => 'web']));
        $colab = new \App\Modules\Addons\Talento\Models\TalentoColaborador();
        $colab->forceFill(['user_id' => $u->id])->save();
        $tipo = \App\Modules\Addons\Talento\Models\TalentoWorkOrderType::query()->value('id');
        $validada = \Carbon\Carbon::parse('2026-01-14 10:00:00');
        $orden = \Illuminate\Support\Facades\DB::table('talento_work_orders')->insertGetId([
            'colaborador_id' => $colab->id, 'type_id' => $tipo, 'points' => 3, 'is_billable' => 1, 'status' => 'validated',
            'validated_at' => $validada, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$u, $colab->id, $orden, $validada];
    }

    private function cerrarSemana(int $colabId, \Carbon\Carbon $instante): void
    {
        $w = \App\Modules\Addons\Talento\Support\PayWeek::boundsFor($instante->copy());
        \Illuminate\Support\Facades\DB::table('talento_liquidations')->insert([
            'colaborador_id' => $colabId, 'period_start' => $w['period_start'], 'period_end' => $w['period_end'],
            'total_units' => 0, 'base_paid' => 0, 'overproduction_paid' => 0, 'other_credits' => 0, 'other_debits' => 0,
            'gross_pay' => 0, 'status' => 'closed', 'closed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_semana_abierta_corrige_la_orden(): void
    {
        [$u, , $orden] = $this->escenario();
        $r = app(S::class)->corregir('work_order', $orden, ['puntos' => 5], 'motivo suficiente', $u->id);
        $this->assertTrue($r['success']);
        $this->assertArrayNotHasKey('ajuste', $r);
        $this->assertSame(5, (int) \Illuminate\Support\Facades\DB::table('talento_work_orders')->where('id', $orden)->value('points'));
    }

    public function test_semana_cerrada_no_reescribe_y_deja_ajuste_en_la_siguiente(): void
    {
        [$u, $colab, $orden, $validada] = $this->escenario();
        $this->cerrarSemana($colab, $validada);
        $r = app(S::class)->corregir('work_order', $orden, ['puntos' => 5], 'motivo suficiente', $u->id);
        $this->assertTrue($r['success']);
        $this->assertTrue($r['ajuste']);
        $this->assertSame(3, (int) \Illuminate\Support\Facades\DB::table('talento_work_orders')->where('id', $orden)->value('points'));
        $aj = \Illuminate\Support\Facades\DB::table('talento_period_adjustments')->where('original_id', $orden)->where('colaborador_id', $colab)->first();
        $this->assertNotNull($aj);
        $this->assertSame(2.0, (float) $aj->points_delta);
        $this->assertGreaterThan($aj->original_period_start, $aj->target_period_start);
        $this->assertTrue(\App\Modules\Addons\Talento\Models\TalentoOrderEvent::where('orden_id', $orden)->where('evento', 'order.corrected')->exists());
    }

    public function test_semana_cerrada_de_otro_participante_tambien_ajusta(): void
    {
        [$u, $colab, $orden, $validada] = $this->escenario();
        $otro = $this->usuario();
        $c2 = new \App\Modules\Addons\Talento\Models\TalentoColaborador();
        $c2->forceFill(['user_id' => $otro->id])->save();
        $this->cerrarSemana($c2->id, $validada);
        $r = app(S::class)->corregir('work_order', $orden, ['participantes' => [['user_id' => $u->id], ['user_id' => $otro->id]]], 'motivo suficiente', $u->id);
        $this->assertTrue($r['success']);
        $this->assertTrue($r['ajuste'] ?? false);
        $this->assertTrue(\Illuminate\Support\Facades\DB::table('talento_period_adjustments')->where('original_id', $orden)->where('colaborador_id', $c2->id)->exists());
    }
}
