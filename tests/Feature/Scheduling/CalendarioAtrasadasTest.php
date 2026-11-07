<?php

namespace Tests\Feature\Scheduling;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\CreatesApplication;

/**
 * Scheduling › Calendario con el rango visible encendido (UNI-T F4): lo atrasado se ve siempre, aunque su
 * día quede antes del rango, si el rango incluye hoy. Todo dentro de una transacción que se revierte.
 */
class CalendarioAtrasadasTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private User $yo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['unificacion.interruptores.calendario_f4' => true]);
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'Atrasadas', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => bcrypt(Str::random(20)), 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->yo = User::find($id);
    }

    private function tarea(string $dia, string $estado = 'ToDo'): int
    {
        $id = DB::table('tasks')->insertGetId([
            'title' => 'Tarea ' . Str::random(6), 'status' => $estado, 'priority' => 'Media', 'tipo' => 'interna', 'archived' => 0,
            'start_date' => $dia, 'start_time' => "$dia 09:00:00", 'end_time' => "$dia 10:00:00",
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('task_user')->insert(['task_id' => $id, 'user_id' => $this->yo->id]);

        return $id;
    }

    private function ids(string $desde, string $hasta)
    {
        return collect($this->actingAs($this->yo)->postJson('/fullcalendar/get-task-events', [
            'rango_inicio' => $desde, 'rango_fin' => $hasta,
        ])->assertOk()->json())->pluck('id');
    }

    public function test_con_hoy_en_el_rango_llega_lo_abierto_de_antes_y_no_lo_cerrado(): void
    {
        $viejo = now()->subDays(40)->toDateString();
        $abierta = $this->tarea($viejo);
        $enCurso = $this->tarea($viejo, 'InProgress');
        $terminada = $this->tarea($viejo, 'Done');
        $cancelada = $this->tarea($viejo, 'Cancelled');
        $deHoy = $this->tarea(now()->toDateString());

        $ids = $this->ids(now()->subDay()->toDateString(), now()->addDay()->toDateString());

        $this->assertTrue($ids->contains($abierta));
        $this->assertTrue($ids->contains($enCurso));
        $this->assertTrue($ids->contains($deHoy));
        $this->assertFalse($ids->contains($terminada));
        $this->assertFalse($ids->contains($cancelada));
    }

    public function test_sin_hoy_en_el_rango_solo_llega_lo_del_rango(): void
    {
        $vieja = $this->tarea(now()->subDays(40)->toDateString());
        $delRango = $this->tarea(now()->addDays(6)->toDateString());

        $ids = $this->ids(now()->addDays(5)->toDateString(), now()->addDays(10)->toDateString());

        $this->assertFalse($ids->contains($vieja));
        $this->assertTrue($ids->contains($delRango));
    }
}
