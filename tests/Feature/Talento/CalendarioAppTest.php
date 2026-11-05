<?php

namespace Tests\Feature\Talento;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;

/** GET /talento/api/calendario: las órdenes y tareas de la persona en sesión (2026-11-05). */
class CalendarioAppTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private int $yo;

    private int $otro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yo = $this->usuario();
        $this->otro = $this->usuario();
        Sanctum::actingAs(User::find($this->yo));
    }

    private function usuario(bool $conPerfil = true): int
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'Calendario', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_cal_' . Str::random(8), 'password' => 'x',
            'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($conPerfil) {
            DB::table('talento_colaboradores')->insert([
                'user_id' => $id, 'type' => 'interno', 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }

    private function tarea(int $usuario, string $inicio, ?string $fin, array $extra = []): int
    {
        $id = DB::table('tasks')->insertGetId($extra + [
            'title' => 'Tarea ' . Str::random(6), 'status' => 'ToDo', 'priority' => 'Media', 'tipo' => 'interna',
            'start_date' => substr($inicio, 0, 10), 'start_time' => $inicio, 'end_time' => $fin,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('task_user')->insert(['task_id' => $id, 'user_id' => $usuario]);

        return $id;
    }

    private function eventos(string $desde, string $hasta)
    {
        return collect($this->getJson("/talento/api/calendario?desde={$desde}&hasta={$hasta}")->assertOk()->json('eventos'));
    }

    public function test_solo_trae_lo_de_la_persona_en_sesion(): void
    {
        $mia = $this->tarea($this->yo, '2030-03-10 09:00:00', '2030-03-10 10:00:00');
        $ajena = $this->tarea($this->otro, '2030-03-10 09:00:00', '2030-03-10 10:00:00');

        $ids = $this->eventos('2030-03-10', '2030-03-10')->pluck('id');

        $this->assertTrue($ids->contains($mia));
        $this->assertFalse($ids->contains($ajena));
    }

    public function test_respeta_el_rango_y_trae_la_que_empezo_antes(): void
    {
        $dentro = $this->tarea($this->yo, '2030-03-10 09:00:00', '2030-03-10 10:00:00');
        $antes = $this->tarea($this->yo, '2030-03-01 09:00:00', '2030-03-01 10:00:00');
        $despues = $this->tarea($this->yo, '2030-03-20 09:00:00', '2030-03-20 10:00:00');
        $variosDias = $this->tarea($this->yo, '2030-03-05 09:00:00', '2030-03-12 18:00:00');

        $ids = $this->eventos('2030-03-08', '2030-03-14')->pluck('id');

        $this->assertTrue($ids->contains($dentro));
        $this->assertTrue($ids->contains($variosDias));
        $this->assertFalse($ids->contains($antes));
        $this->assertFalse($ids->contains($despues));
    }

    public function test_no_trae_archivadas_ni_borradas(): void
    {
        $archivada = $this->tarea($this->yo, '2030-03-10 09:00:00', null, ['status' => 'Archivado']);
        $marcada = $this->tarea($this->yo, '2030-03-10 09:00:00', null, ['archived' => true]);
        $borrada = $this->tarea($this->yo, '2030-03-10 09:00:00', null, ['deleted_at' => now()]);

        $ids = $this->eventos('2030-03-10', '2030-03-10')->pluck('id');

        $this->assertFalse($ids->contains($archivada));
        $this->assertFalse($ids->contains($marcada));
        $this->assertFalse($ids->contains($borrada));
    }

    public function test_marca_atrasada_solo_lo_abierto_que_ya_paso(): void
    {
        $pasada = now()->subDays(2)->format('Y-m-d H:i:s');
        $futura = now()->addDays(2)->format('Y-m-d H:i:s');
        $abierta = $this->tarea($this->yo, $pasada, $pasada);
        $terminada = $this->tarea($this->yo, $pasada, $pasada, ['status' => 'Done']);
        $porVenir = $this->tarea($this->yo, $futura, $futura);

        $eventos = $this->eventos(now()->subDays(3)->toDateString(), now()->addDays(3)->toDateString())->keyBy('id');

        $this->assertTrue($eventos[$abierta]['atrasada']);
        $this->assertFalse($eventos[$terminada]['atrasada']);
        $this->assertFalse($eventos[$porVenir]['atrasada']);
    }

    public function test_distingue_orden_de_tarea_y_manda_lo_que_pinta_la_app(): void
    {
        $tipo = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'Orden sintética ' . Str::random(6), 'category' => 'campo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $orden = $this->tarea($this->yo, '2030-03-10 09:00:00', '2030-03-10 10:00:00', ['tipo' => 'campo', 'talento_type_id' => $tipo, 'priority' => 'Alta']);
        $tarea = $this->tarea($this->yo, '2030-03-10 11:00:00', '2030-03-10 12:00:00');

        $eventos = $this->eventos('2030-03-10', '2030-03-10')->keyBy('id');

        $this->assertTrue($eventos[$orden]['es_orden']);
        $this->assertFalse($eventos[$tarea]['es_orden']);
        $this->assertSame('Alta', $eventos[$orden]['prioridad']);
        $this->assertSame('ToDo', $eventos[$orden]['estado']);
        $this->assertSame('2030-03-10T09:00:00', $eventos[$orden]['inicio']);
        $this->assertSame('2030-03-10T10:00:00', $eventos[$orden]['fin']);
    }

    public function test_valida_el_rango(): void
    {
        $this->getJson('/talento/api/calendario?desde=2030-03-10&hasta=2030-03-01')->assertStatus(422);
        $this->getJson('/talento/api/calendario?desde=2030-01-01&hasta=2030-06-01')->assertStatus(422);
        $this->getJson('/talento/api/calendario?desde=ayer&hasta=hoy')->assertStatus(422);
    }

    public function test_sin_perfil_de_colaborador_no_entra(): void
    {
        Sanctum::actingAs(User::find($this->usuario(false)));

        $this->getJson('/talento/api/calendario?desde=2030-03-10&hasta=2030-03-10')->assertStatus(403);
    }
}
