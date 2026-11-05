<?php

namespace App\Services\Metricas;

use App\Models\Router;

/**
 * Sesión simulada (solo DEV, item #988): contesta la identidad registrada o el título del router,
 * contadores que siempre crecen y ping sub-milisegundo. Ver SimuladoMetricasDriver. Con
 * metricas.simulado se pueden ver en pantalla los casos de otra identidad y de VLAN no encontrada o repetida.
 */
final class SimuladoSesion implements SesionRouter
{
    /** @var array<string, array{por: ?string, nombres: string[]}> */
    private array $resolucion = [];

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
        return (string) (config('metricas.simulado.identidades', [])[$this->router->title]
            ?? ($this->router->mikrotik?->identidad_esperada ?: $this->router->title));
    }

    public function interfaces(array $pedidas): array
    {
        $ahora = microtime(true);
        $lecturas = [];
        foreach ($pedidas as $clave => $p) {
            $vlan = $p['vlan_id'] ?? null;
            $porNombre = trim((string) ($p['interfaz'] ?? '')) !== '';
            $this->resolucion[$clave] = ['por' => $porNombre ? 'nombre' : ($vlan ? 'vlan_id' : null), 'nombres' => match (true) {
                $porNombre => [$p['interfaz']],
                ! $vlan || $vlan === config('metricas.simulado.vlan_no_encontrada') => [],
                $vlan === config('metricas.simulado.vlan_repetida') => ["VLAN {$vlan} (simulada)", "VLAN {$vlan} copia (simulada)"],
                default => ["VLAN {$vlan} (simulada)"],
            }];
            $semilla = SimuladoMetricasDriver::semilla((int) $this->router->id, $p['interfaz'] ?? null, $vlan);
            $lecturas[$clave] = $semilla === null || count($this->resolucion[$clave]['nombres']) !== 1
                ? null
                : SimuladoMetricasDriver::lectura($semilla, $ahora);
        }

        return $lecturas;
    }

    public function resolucion(): array
    {
        return $this->resolucion;
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
