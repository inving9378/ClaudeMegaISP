<?php

namespace Tests\Feature\Talento;

use App\Modules\Addons\Talento\Services\OrderParticipantsService;
use App\Modules\Addons\Talento\Services\ReconocimientoPorRolService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\CreatesApplication;

/**
 * #1325 — reparto de puntos de una orden en equipo, regla de Irving (11-nov): la orden reparte el
 * 100 %. Sola, la persona se lleva todo; en equipo, 70 % el responsable y el 30 % se divide entre los
 * auxiliares. Datos sintéticos; correr SOLO por nombre (megaisp_test, transacción que se deshace).
 */
class RepartoPuntosEquipoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'unificacion.interruptores.participantes_d8'                => true,
            'unificacion.interruptores.porcentajes_rol_d8b'             => true,
            'unificacion.interruptores.porcentajes_rol_d8b_responsable' => 70,
            'unificacion.interruptores.porcentajes_rol_d8b_participante' => 30,
        ]);
    }

    private function usuario(): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => Str::random(6), 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => 'x', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Orden con responsable y auxiliares; devuelve [orden, responsable, auxiliares]. */
    private function orden(int $auxiliares): array
    {
        $tarea = DB::table('tasks')->insertGetId([
            'title' => 'Orden de prueba', 'status' => 'Done', 'tipo' => 'campo', 'points' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $responsable = $this->usuario();
        $aux = $auxiliares ? array_map(fn () => $this->usuario(), range(1, $auxiliares)) : [];
        foreach ([$responsable, ...$aux] as $uid) {
            DB::table('task_user')->insert(['task_id' => $tarea, 'user_id' => $uid]);
        }
        app(OrderParticipantsService::class)->fijar('task', $tarea, $responsable, array_map(fn ($u) => ['user_id' => $u], $aux));

        return [$tarea, $responsable, $aux];
    }

    private function puntos(int $tarea, int $userId): float
    {
        return round(10 * app(ReconocimientoPorRolService::class)->factor('task', $tarea, $userId), 2);
    }

    public function test_sola_la_persona_recibe_la_orden_completa(): void
    {
        [$tarea, $responsable] = $this->orden(0);

        $this->assertSame(10.0, $this->puntos($tarea, $responsable));
    }

    public function test_responsable_y_un_auxiliar_setenta_treinta(): void
    {
        [$tarea, $responsable, $aux] = $this->orden(1);

        $this->assertSame(7.0, $this->puntos($tarea, $responsable));
        $this->assertSame(3.0, $this->puntos($tarea, $aux[0]));
    }

    public function test_con_varios_auxiliares_el_treinta_se_reparte_y_la_orden_suma_cien(): void
    {
        [$tarea, $responsable, $aux] = $this->orden(2);

        $this->assertSame(7.0, $this->puntos($tarea, $responsable));
        $this->assertSame(1.5, $this->puntos($tarea, $aux[0]));
        $this->assertSame(1.5, $this->puntos($tarea, $aux[1]));
        $total = $this->puntos($tarea, $responsable) + array_sum(array_map(fn ($u) => $this->puntos($tarea, $u), $aux));
        $this->assertSame(10.0, $total);
    }

    public function test_apagado_cada_quien_la_orden_completa(): void
    {
        config(['unificacion.interruptores.porcentajes_rol_d8b' => false]);
        [$tarea, $responsable, $aux] = $this->orden(2);

        $this->assertSame(10.0, $this->puntos($tarea, $responsable));
        $this->assertSame(10.0, $this->puntos($tarea, $aux[1]));
    }
}
