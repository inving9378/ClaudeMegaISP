<?php

namespace Tests\Unit\Talento;

use App\Modules\Addons\Talento\Support\Actor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActorTrackUnicoTest extends TestCase
{
    public static function combinaciones(): array
    {
        return [
            'planta'                 => [['TECNICO_PLANTA'], 'tecnico_planta_interna'],
            'planta gana a todo'     => [['Mostrador', 'TECNICO', 'TECNICO_PLANTA'], 'tecnico_planta_interna'],
            'instalador'             => [['TECNICO_INSTALADOR', 'Vendedor'], 'tecnico_planta_externa'],
            'tecnico'                => [['TECNICO'], 'tecnico_planta_externa'],
            'tecnico + mostrador'    => [['Mostrador', 'TECNICO'], 'tecnico_planta_externa'],
            'mostrador'              => [['Mostrador'], 'mostrador'],
            'supervisor mostrador'   => [['SUPERVISOR_MOSTRADOR'], 'mostrador'],
            'supervisor + vendedor'  => [['SUPERVISOR_MOSTRADOR', 'Vendedor'], 'mostrador'],
            'vendedor'               => [['Vendedor'], 'vendedor'],
            'sin rol de track'       => [['client'], 'generico'],
        ];
    }

    #[DataProvider('combinaciones')]
    public function test_track_encendido(array $roles, string $esperado): void
    {
        config(['unificacion.interruptores.track_unico' => true]);
        $this->assertSame($esperado, Actor::trackDeRoles($roles));
    }

    public function test_apagado_conserva_el_comportamiento_anterior(): void
    {
        config(['unificacion.interruptores.track_unico' => false]);
        $this->assertSame('generico', Actor::trackDeRoles(['SUPERVISOR_MOSTRADOR']));
        $this->assertSame('mostrador', Actor::trackDeRoles(['Mostrador']));
    }

    public function test_orden_de_puesto_es_el_de_actor(): void
    {
        $this->assertSame(['TECNICO_PLANTA', 'TECNICO_INSTALADOR', 'TECNICO', 'SUPERVISOR_MOSTRADOR', 'Mostrador'], Actor::ROLES_POR_PRIORIDAD);
    }
}
