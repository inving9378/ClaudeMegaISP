<?php

namespace Tests\Feature\Scheduling;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * El calendario de Scheduling muestra quién tiene la tarea: antes se le quitaban las 2 últimas letras
 * al nombre (BRANDON salía como BRAND). No usa la base de datos.
 */
class NombreAsignadoCalendarioTest extends TestCase
{
    use CreatesApplication;

    private function persona(string $nombre, ?string $apellido): User
    {
        return (new User())->forceFill(['name' => $nombre, 'father_last_name' => $apellido]);
    }

    private function tarea(array $asignados, ?User $directo = null): Task
    {
        return (new Task())->setRelation('users', new Collection($asignados))->setRelation('user', $directo);
    }

    public function test_una_persona_asignada_sale_con_su_nombre_completo(): void
    {
        $this->assertSame('TECNICO PRUEBA', $this->tarea([$this->persona('TECNICO', 'PRUEBA')])->user_or_team_name);
    }

    public function test_sin_apellido_sale_solo_el_nombre(): void
    {
        $this->assertSame('Prueba', $this->tarea([$this->persona('Prueba', null)])->user_or_team_name);
    }

    public function test_sin_lista_de_asignados_usa_la_persona_de_la_tarea(): void
    {
        $this->assertSame('Prueba Admin', $this->tarea([], $this->persona('Prueba', 'Admin'))->user_or_team_name);
    }

    public function test_sin_nadie_asignado_queda_vacio(): void
    {
        $this->assertSame('', $this->tarea([])->user_or_team_name);
    }
}
