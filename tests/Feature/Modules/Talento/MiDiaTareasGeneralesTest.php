<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\Task;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use Database\Seeders\UniPruebaSeeder;
use Tests\TestCase;

class MiDiaTareasGeneralesTest extends TestCase
{
    private function tareaGeneral($colab): Task
    {
        $t = Task::create([
            'title'      => 'UNI-PRUEBA tarea general',
            'start_date' => now()->toDateString(),
            'start_time' => '10:30:00',
        ]);
        $t->users()->attach($colab->user_id);
        return $t;
    }

    public function test_mi_dia_incluye_tareas_generales_solo_con_el_interruptor_encendido(): void
    {
        $colabs = (new UniPruebaSeeder())->perfiles();
        $colab  = reset($colabs);
        $t = $this->tareaGeneral($colab);
        $svc = new OrdenTrabajoUnifiedService();

        config(['unificacion.interruptores.mi_dia_tareas' => false]);
        $this->assertNotContains($t->id, array_column($svc->summaryForHoy($colab->id), 'id'));

        config(['unificacion.interruptores.mi_dia_tareas' => true]);
        $fila = collect($svc->summaryForHoy($colab->id))->firstWhere('id', $t->id);
        $this->assertNotNull($fila);
        $this->assertTrue($fila['es_tarea_general']);
        $this->assertSame('Tarea', $fila['tipo']);
        $this->assertSame('10:30', $fila['hora']);
    }
}
