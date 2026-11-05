<?php

namespace App\Services\Metricas;

/**
 * Cuándo se mide un servicio dedicado (item #988): cliente de tipo Dedicado + cliente «Activo» +
 * servicio activo. Cualquier otra cosa lo pausa con su motivo; nada de esto es manual.
 * Un estado que no se conoce también pausa: mejor sin medir que medir a quien no toca.
 */
final class PoliticaMonitoreo
{
    /** En los datos conviven los dos: «Activado» lo escribe el alta del servicio y «Activo» el resto. */
    public const SERVICIO_ACTIVO = ['Activo', 'Activado'];

    public const CLIENTE_ACTIVO = 'Activo';

    private const CLIENTE = [
        'Bloqueado'    => 'cliente_bloqueado',
        'Inactivo'     => 'cliente_inactivo',
        'Cancelado'    => 'cliente_cancelado',
        'Desinstalado' => 'cliente_desinstalado',
        'Nuevo'        => 'cliente_nuevo',
    ];

    private const SERVICIO = [
        'Pendiente'   => 'servicio_pendiente',
        'Desactivado' => 'servicio_desactivado',
    ];

    private const TEXTOS = [
        'no_dedicado'          => 'ya no es cliente dedicado',
        'cliente_eliminado'    => 'cliente eliminado',
        'cliente_bloqueado'    => 'cliente bloqueado',
        'cliente_inactivo'     => 'cliente inactivo',
        'cliente_cancelado'    => 'cliente cancelado',
        'cliente_desinstalado' => 'cliente desinstalado',
        'cliente_nuevo'        => 'cliente nuevo, todavía sin activar',
        'cliente_otro'         => 'el cliente no está activo',
        'servicio_pendiente'   => 'servicio pendiente',
        'servicio_desactivado' => 'servicio desactivado',
        'servicio_otro'        => 'el servicio no está activo',
    ];

    /** @return string|null  el motivo de la pausa; null = se mide */
    public static function motivoPausa(bool $dedicado, bool $clienteEliminado, ?string $estadoCliente, ?string $estadoServicio): ?string
    {
        if (! $dedicado) {
            return 'no_dedicado';
        }
        if ($clienteEliminado) {
            return 'cliente_eliminado';
        }
        if ($estadoCliente !== self::CLIENTE_ACTIVO) {
            return self::CLIENTE[$estadoCliente] ?? 'cliente_otro';
        }
        if (! in_array($estadoServicio, self::SERVICIO_ACTIVO, true)) {
            return self::SERVICIO[$estadoServicio] ?? 'servicio_otro';
        }

        return null;
    }

    /**
     * ¿Esta IP es del router y no del cliente? Es la IP del router o la puerta de enlace del bloque (su
     * primera IP utilizable). Medirla diría «En línea» aunque el cliente esté caído (#999).
     *
     * @param  array<int, ?string>  $ipsDelRouter
     */
    public static function ipPareceDelRouter(string $ip, array $ipsDelRouter, ?string $bloque): bool
    {
        if (in_array($ip, array_filter($ipsDelRouter), true)) {
            return true;
        }
        $b = BloqueIpv4::desde((string) $bloque);

        return $b !== null && $b->prefijo <= 30 && $ip === long2ip($b->red + 1);
    }

    public static function texto(?string $motivo): string
    {
        return self::TEXTOS[$motivo] ?? 'estado no reconocido';
    }
}
