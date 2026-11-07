<?php

namespace Tests\Feature\Talento;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoOrderEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;

/** POST /talento/api/ots/{id}/reprogramar: lo atrasado se mueve desde la app, con motivo (2026-11-06). */
class ReprogramarAppTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private int $yo;

    private int $otro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yo = $this->persona();
        $this->otro = $this->persona();
        Sanctum::actingAs(User::find($this->yo));
    }

    private function persona(): int
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'Reprogramar', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_repro_' . Str::random(8), 'password' => 'x',
            'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('talento_colaboradores')->insert([
            'user_id' => $id, 'type' => 'interno', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function orden(int $userId, string $dia, string $estado = 'ToDo', bool $deTalento = true): int
    {
        $tipo = $deTalento ? DB::table('talento_work_order_types')->insertGetId([
            'name' => 'Orden sintética ' . Str::random(6), 'category' => 'campo',
            'created_at' => now(), 'updated_at' => now(),
        ]) : null;
        $id = DB::table('tasks')->insertGetId([
            'title' => 'Orden de prueba', 'status' => $estado, 'tipo' => $deTalento ? 'campo' : 'interna', 'talento_type_id' => $tipo,
            'start_date' => $dia, 'start_time' => "{$dia} 09:30:00", 'end_time' => "{$dia} 10:30:00",
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('task_user')->insert(['task_id' => $id, 'user_id' => $userId]);

        return $id;
    }

    private function reprogramar(int $id, array $datos)
    {
        return $this->postJson("/talento/api/ots/{$id}/reprogramar", $datos);
    }

    public function test_mueve_la_orden_propia_conserva_la_hora_y_guarda_el_motivo(): void
    {
        $id = $this->orden($this->yo, now()->subDays(3)->toDateString());
        $manana = now()->addDay()->toDateString();

        $this->reprogramar($id, ['fecha' => $manana, 'motivo' => 'El cliente pidió otro día'])
            ->assertOk()
            ->assertJson(['ok' => true, 'fecha' => $manana, 'hora' => '09:30']);

        $tarea = DB::table('tasks')->where('id', $id)->first();
        $this->assertSame($manana, substr($tarea->start_date, 0, 10));
        $this->assertSame("{$manana} 09:30", substr($tarea->start_time, 0, 16));

        $evento = TalentoOrderEvent::deOrden('task', $id)->where('evento', 'order.rescheduled')->first();
        $this->assertSame('El cliente pidió otro día', $evento->motivo);
        $this->assertSame($this->yo, (int) $evento->actor_id);
    }

    public function test_deja_de_salir_atrasada_en_mi_dia(): void
    {
        $id = $this->orden($this->yo, now()->subDays(2)->toDateString());
        $enHoy = fn () => collect($this->getJson('/talento/api/ots/hoy')->assertOk()->json('ots'))->firstWhere('id', $id);

        $this->assertTrue($enHoy()['atrasada']);
        $this->reprogramar($id, ['fecha' => now()->addDay()->toDateString(), 'motivo' => 'Falta material o equipo'])->assertOk();
        $this->assertNull($enHoy());
    }

    public function test_el_detalle_dice_si_esta_atrasada_y_desde_cuando(): void
    {
        $dia = now()->subDays(2)->toDateString();
        $atrasada = $this->getJson('/talento/api/ots/' . $this->orden($this->yo, $dia))->assertOk();
        $this->assertTrue($atrasada->json('ot.atrasada'));
        $this->assertSame($dia, $atrasada->json('ot.fecha'));
        $this->assertSame('task', $atrasada->json('ot.origen'));

        $deHoy = $this->getJson('/talento/api/ots/' . $this->orden($this->yo, now()->toDateString()))->assertOk();
        $this->assertFalse($deHoy->json('ot.atrasada'));

        $hecha = $this->getJson('/talento/api/ots/' . $this->orden($this->yo, $dia, 'Done'))->assertOk();
        $this->assertFalse($hecha->json('ot.atrasada'));
    }

    public function test_no_se_mueve_la_orden_de_otra_persona(): void
    {
        $id = $this->orden($this->otro, now()->subDay()->toDateString());

        $this->reprogramar($id, ['fecha' => now()->toDateString(), 'motivo' => 'El cliente no estaba'])->assertNotFound();
        $this->assertSame(now()->subDay()->toDateString(), substr(DB::table('tasks')->where('id', $id)->value('start_date'), 0, 10));
    }

    public function test_pide_dia_de_hoy_en_adelante_y_motivo(): void
    {
        $ayer = now()->subDay()->toDateString();
        $id = $this->orden($this->yo, $ayer);

        $this->reprogramar($id, ['fecha' => $ayer, 'motivo' => 'El cliente no estaba'])
            ->assertStatus(422)->assertJson(['message' => 'La nueva fecha debe ser de hoy en adelante.']);
        $this->reprogramar($id, ['fecha' => now()->toDateString(), 'motivo' => 'no'])
            ->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->reprogramar($id, ['motivo' => 'El cliente no estaba'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha');
        // Son solo espacios: pasa la validación de largo, pero no cuenta como motivo.
        $this->reprogramar($id, ['fecha' => now()->toDateString(), 'motivo' => '      '])->assertStatus(422);

        $this->assertSame($ayer, substr(DB::table('tasks')->where('id', $id)->value('start_date'), 0, 10));
    }

    public function test_solo_se_mueve_lo_abierto_y_de_talento(): void
    {
        $manana = now()->addDay()->toDateString();
        $ayer = now()->subDay()->toDateString();

        $this->reprogramar($this->orden($this->yo, $ayer, 'Done'), ['fecha' => $manana, 'motivo' => 'El cliente no estaba'])->assertStatus(422);
        $this->assertContains(
            $this->reprogramar($this->orden($this->yo, $ayer, 'ToDo', false), ['fecha' => $manana, 'motivo' => 'El cliente no estaba'])->status(),
            [404, 422],
        );
    }
}
