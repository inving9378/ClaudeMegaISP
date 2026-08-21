<?php

namespace App\Services\Ipv6;

use App\Services\Ipv6\Contracts\Ipv6CommandDriverInterface;

/**
 * Servicio PURO (entra plan + versión, sale texto — nunca toca un router real):
 * traduce el plan abstracto de Ipv6AddressPlanCalculator (#950 fase 1.4) a
 * comandos concretos de RouterOS usando el driver de la familia de versión
 * correspondiente (Ipv6CommandDriverFactory).
 */
class Ipv6CommandGenerator
{
    /**
     * @param array<string, mixed> $plan salida de Ipv6AddressPlanCalculator::calcular()
     * @return array{driver: string, comandos: string[], advertencias: string[]}
     */
    public function generarParaPlan(array $plan, string $version): array
    {
        $driver = Ipv6CommandDriverFactory::paraVersion($version);

        $comandos = [];
        $advertencias = [];
        $agregar = function (array $resultado) use (&$comandos, &$advertencias): void {
            $comandos = array_merge($comandos, $resultado['comandos']);
            $advertencias = array_merge($advertencias, $resultado['advertencias']);
        };

        $agregar($driver->crearBlackhole($plan['prefijo_entrada'], 'MgNet-IPv6 agregado'));
        $agregar($driver->crearSegmento(
            $plan['segmentos']['infraestructura']['prefijo'],
            'MgNet-IPv6-infra',
            'MgNet-IPv6 infra'
        ));

        foreach ($plan['segmentos']['zonas'] as $zona) {
            $nombrePool = 'pool-' . $zona['nombre'];
            $agregar($driver->crearPool($nombrePool, $zona['prefijo'], $zona['delegacion']));
            $agregar($driver->asignarPerfilPPP($zona['nombre'], $nombrePool));
            $agregar($driver->crearListaFirewall('MgNet-IPv6-zonas', $zona['prefijo'], 'MgNet-IPv6 zona:' . $zona['nombre']));
        }

        $empresarial = $plan['segmentos']['empresarial'];
        $agregar($driver->crearPool('pool-empresarial', $empresarial['prefijo'], $empresarial['delegacion']));
        $agregar($driver->crearListaFirewall('MgNet-IPv6-empresarial', $empresarial['prefijo'], 'MgNet-IPv6 empresarial'));

        return [
            'driver' => $driver->nombre(),
            'comandos' => $comandos,
            'advertencias' => array_values(array_unique($advertencias)),
        ];
    }

    /** @return array{driver: string, comandos: string[], advertencias: string[]} */
    public function generarEliminarPorMarcador(string $marcador, string $version): array
    {
        $driver = Ipv6CommandDriverFactory::paraVersion($version);
        $resultado = $driver->eliminarPorMarcador($marcador);

        return [
            'driver' => $driver->nombre(),
            'comandos' => $resultado['comandos'],
            'advertencias' => $resultado['advertencias'],
        ];
    }

    public function driverParaVersion(string $version): Ipv6CommandDriverInterface
    {
        return Ipv6CommandDriverFactory::paraVersion($version);
    }
}
