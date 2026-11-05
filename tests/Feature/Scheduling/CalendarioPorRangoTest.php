<?php

namespace Tests\Feature\Scheduling;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/** UNI-T F4: el calendario pide solo el rango visible y excluye canceladas (interruptor calendario_f4). */
class CalendarioPorRangoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_rango_filtra_y_excluye_canceladas_con_interruptor(): void
    {
        config(['unificacion.interruptores.calendario_f4' => true]);
        $admin = User::find(config('app.uni_test_admin_id', 1)) ?? User::first();
        $this->actingAs($admin);

        $dentro = Task::forceCreate(['title' => 'UNI-PRUEBA dentro', 'status' => 'ToDo', 'start_date' => '2031-03-10', 'archived' => false]);
        $fuera = Task::forceCreate(['title' => 'UNI-PRUEBA fuera', 'status' => 'ToDo', 'start_date' => '2031-05-10', 'archived' => false]);
        $cancel = Task::forceCreate(['title' => 'UNI-PRUEBA cancelada', 'status' => 'Cancelled', 'start_date' => '2031-03-11', 'archived' => false]);

        $ids = (new TaskService())->getTasksToEvents(['rango_inicio' => '2031-03-01', 'rango_fin' => '2031-03-31'])->pluck('id')->all();

        $this->assertContains($dentro->id, $ids);
        $this->assertNotContains($fuera->id, $ids);
        $this->assertNotContains($cancel->id, $ids);
    }

    public function test_sin_rango_o_sin_interruptor_no_cambia(): void
    {
        $admin = User::first();
        $this->actingAs($admin);
        $t = Task::forceCreate(['title' => 'UNI-PRUEBA x', 'status' => 'ToDo', 'start_date' => '2031-05-10', 'archived' => false]);

        config(['unificacion.interruptores.calendario_f4' => false]);
        $ids = (new TaskService())->getTasksToEvents(['rango_inicio' => '2031-03-01', 'rango_fin' => '2031-03-31'])->pluck('id')->all();
        $this->assertContains($t->id, $ids);
    }
}
