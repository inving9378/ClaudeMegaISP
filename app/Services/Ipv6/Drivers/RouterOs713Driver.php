<?php

namespace App\Services\Ipv6\Drivers;

/**
 * RouterOS 7.13+ (familia del CCR2216 actual, prioridad 1 — item padre #811).
 *
 * Diferencia documentada: el 18/08/2026 `/ipv6 address remove [find comment~"..."]`
 * falló contra un router 7.13+ con "this is configured elsewhere" porque parte de
 * las entradas que matchean el marcador son DINÁMICAS (creadas por DHCPv6-PD/SLAAC,
 * no por el operador) y RouterOS reciente ya no permite borrarlas directo desde
 * `/ipv6 address remove` — hay que desactivarlas desde su origen. En versiones
 * viejas (6.x, 7.0-7.12) el remove directo sí funciona incluso sobre dinámicas.
 */
class RouterOs713Driver extends AbstractRouterOsDriver
{
    public function nombre(): string
    {
        return 'RouterOS 7.13+';
    }

    public function eliminarPorMarcador(string $marcador): array
    {
        $marcadorEscapado = $this->escapar($marcador);

        $comandos = [
            sprintf(':foreach i in=[/ipv6 address find comment~"%s"] do={', $marcadorEscapado),
            '  :if ([/ipv6 address get $i dynamic] = false) do={',
            '    /ipv6 address remove $i',
            '  } else={',
            sprintf('    :log warning "%s: entrada dinamica no removida, revisar origen (DHCPv6-PD/SLAAC): $i"', self::MARCADOR_DEFECTO),
            '  }',
            '}',
        ];

        return [
            'comandos' => $comandos,
            'advertencias' => [
                'RouterOS 7.13+ bloquea el remove directo de entradas dinámicas ("this is configured elsewhere"); '
                . 'las que matcheen el marcador y sean dinámicas (DHCPv6-PD/SLAAC) no se borran aquí — desactívalas desde su origen.',
            ],
        ];
    }
}
