<?php

namespace App\Services\Metricas;

use App\Models\Router;

/**
 * Sesión simulada (solo DEV, item #988): contesta la identidad que el router tiene registrada
 * como esperada, contadores que siempre crecen y ping sub-milisegundo. Ver SimuladoMetricasDriver.
 */
final class SimuladoSesion implements SesionRouter
{
    public function __construct(private readonly Router $router)
    {
    }

    public function host(): string
    {
        return 'simulado';
    }

    public function usuario(): string
    {
        return 'simulado';
    }

    public function soloLectura(): bool
    {
        return true;
    }

    public function redirigido(): bool
    {
        return false;
    }

    public function identidad(): string
    {
        return (string) ($this->router->mikrotik?->identidad_esperada ?: $this->router->title);
    }

    public function interfaces(array $pedidas): array
    {
        $ahora = microtime(true);
        $lecturas = [];
        foreach ($pedidas as $clave => $p) {
            $semilla = SimuladoMetricasDriver::semilla((int) $this->router->id, $p['interfaz'] ?? null, $p['vlan_id'] ?? null);
            $lecturas[$clave] = $semilla === null ? null : SimuladoMetricasDriver::lectura($semilla, $ahora);
        }

        return $lecturas;
    }

    public function ping(string $ip, int $paquetes, string $intervalo): LecturaPing
    {
        return SimuladoMetricasDriver::ping($ip, microtime(true), $paquetes);
    }

    public function tasasEnVivo(array $vlanIds): array
    {
        $t = microtime(true);

        return array_map(function ($id) use ($t) {
            $semilla = SimuladoMetricasDriver::semilla((int) $this->router->id, null, (int) $id);
            $bajada = SimuladoMetricasDriver::tasaBps($semilla, 'bajada', $t);
            $subida = SimuladoMetricasDriver::tasaBps($semilla, 'subida', $t);

            // Como Winbox: rx = lo que entra al router (subida del cliente), tx = lo que sale (bajada).
            return ['vlan_id' => (int) $id, 'nombre' => "VLAN {$id} (simulada)", 'padre' => null,
                'rx_bps' => $subida, 'tx_bps' => $bajada,
                'rx_pps' => SimuladoMetricasDriver::ppsDe($subida, 'subida'), 'tx_pps' => SimuladoMetricasDriver::ppsDe($bajada, 'bajada')];
        }, array_values(array_unique($vlanIds)));
    }

    public function cerrar(): void
    {
    }
}
