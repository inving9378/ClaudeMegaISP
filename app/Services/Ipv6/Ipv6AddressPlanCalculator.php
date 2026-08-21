<?php

namespace App\Services\Ipv6;

/**
 * Servicio PURO (sin I/O, sin contacto con el router) que traduce un prefijo IPv6
 * delegado + parámetros de crecimiento en un plan de direccionamiento jerárquico.
 *
 * Estructura generada dentro del prefijo /N de entrada:
 *   - primer  /(N+8) → infraestructura (un /64 por VLAN, se subdivide después)
 *   - segundo /(N+8) → enlaces P2P y reserva de infraestructura
 *   - una zona /(N+4) por cada distrito PPPoE (con su delegación /D recomendada)
 *   - una zona /(N+4) → dedicados/empresariales
 *   - el resto de los bloques /(N+4) → reservado para crecimiento futuro
 *
 * LIMITACIÓN CONOCIDA (decisión registrada vía circuito:reportar): esta versión solo
 * soporta prefijos alineados a nibble (longitud múltiplo de 4). Es el caso real de
 * MegaISP y de toda la literatura de delegación IPv6 (/32, /36, /40, /44, /48, /52,
 * /56), y permite implementar la aritmética de bloques con strings hexadecimales en
 * vez de operaciones a nivel de bit — más simple y sin dependencia de GMP (no
 * instalado en este entorno). Si algún día se necesita un prefijo no alineado a
 * nibble, se generaliza entonces.
 */
class Ipv6AddressPlanCalculator
{
    public const MIN_PREFIX_LEN = 32;
    public const MAX_PREFIX_LEN = 56;

    /** Bloques /(N+4) totales disponibles dentro del prefijo /N (2^4, un nibble). */
    public const TOTAL_ZONE_SLOTS = 16;

    /** Delegación por cliente jamás más específica que /64 (límite de LAN IPv6 estándar). */
    public const MAX_DELEGATION_LEN = 64;

    /**
     * @param string $prefijo             ej. "2001:db8::/48"
     * @param int    $clientesActuales    clientes activos hoy en el plan
     * @param float  $margenCrecimiento   multiplicador de proyección (1.5 = +50%)
     * @param array<int, string> $zonas   nombres de distritos PPPoE, uno por zona
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException si el prefijo, la longitud, las zonas o la
     *                                    delegación resultante no son válidos.
     */
    public static function calcular(string $prefijo, int $clientesActuales, float $margenCrecimiento, array $zonas): array
    {
        [$networkHex, $prefixLen] = self::parsePrefix($prefijo);

        if ($clientesActuales < 0) {
            throw new \InvalidArgumentException('clientes_actuales no puede ser negativo.');
        }
        if ($margenCrecimiento <= 0) {
            throw new \InvalidArgumentException('margen_crecimiento debe ser mayor que 0.');
        }
        $zonas = array_values($zonas);
        if (count($zonas) === 0) {
            throw new \InvalidArgumentException('Debe especificar al menos una zona (distrito PPPoE).');
        }

        // slot 0 = infraestructura+P2P, 1..count(zonas) = distritos, +1 = empresarial
        $slotsNecesarios = 2 + count($zonas);
        if ($slotsNecesarios > self::TOTAL_ZONE_SLOTS) {
            throw new \InvalidArgumentException(sprintf(
                'Las zonas no caben en /%d: se necesitan %d bloques /%d (infraestructura + %d distrito(s) + empresarial) y solo hay %d disponibles.',
                $prefixLen,
                $slotsNecesarios,
                $prefixLen + 4,
                count($zonas),
                self::TOTAL_ZONE_SLOTS
            ));
        }

        $zonePrefixLen = $prefixLen + 4;
        $infraPrefixLen = $prefixLen + 8;

        $clientesProyectados = (int) ceil($clientesActuales * $margenCrecimiento);

        $infraBloque = self::subBlock($networkHex, $prefixLen, 0, $zonePrefixLen);
        $infraSegmento = self::subBlock($infraBloque, $zonePrefixLen, 0, $infraPrefixLen);
        $p2pSegmento = self::subBlock($infraBloque, $zonePrefixLen, 1, $infraPrefixLen);

        $zonasPlan = [];
        foreach ($zonas as $i => $nombreZona) {
            $slot = 1 + $i;
            $zonaBloque = self::subBlock($networkHex, $prefixLen, $slot, $zonePrefixLen);
            $delegacion = self::delegacionRecomendada($zonePrefixLen, $clientesProyectados);
            $zonasPlan[] = [
                'nombre' => $nombreZona,
                'prefijo' => self::formatCidr($zonaBloque, $zonePrefixLen),
                'delegacion' => $delegacion,
                'capacidad_delegaciones' => 2 ** ($delegacion - $zonePrefixLen),
            ];
        }

        $empresarialSlot = 1 + count($zonas);
        $empresarialBloque = self::subBlock($networkHex, $prefixLen, $empresarialSlot, $zonePrefixLen);
        $empresarialDelegacion = self::delegacionRecomendada($zonePrefixLen, $clientesProyectados);

        $reservadoSlots = [];
        for ($slot = $empresarialSlot + 1; $slot < self::TOTAL_ZONE_SLOTS; $slot++) {
            $reservadoSlots[] = self::formatCidr(self::subBlock($networkHex, $prefixLen, $slot, $zonePrefixLen), $zonePrefixLen);
        }

        return [
            'prefijo_entrada' => self::formatCidr($networkHex, $prefixLen),
            'longitud_prefijo' => $prefixLen,
            'clientes_actuales' => $clientesActuales,
            'margen_crecimiento' => $margenCrecimiento,
            'clientes_proyectados' => $clientesProyectados,
            'segmentos' => [
                'infraestructura' => [
                    'prefijo' => self::formatCidr($infraSegmento, $infraPrefixLen),
                    'descripcion' => 'Un /64 por VLAN, asignados dentro de este bloque.',
                ],
                'enlaces_p2p_reserva' => [
                    'prefijo' => self::formatCidr($p2pSegmento, $infraPrefixLen),
                    'descripcion' => 'Enlaces punto a punto y reserva de infraestructura.',
                ],
                'zonas' => $zonasPlan,
                'empresarial' => [
                    'nombre' => 'dedicados/empresarial',
                    'prefijo' => self::formatCidr($empresarialBloque, $zonePrefixLen),
                    'delegacion' => $empresarialDelegacion,
                    'capacidad_delegaciones' => 2 ** ($empresarialDelegacion - $zonePrefixLen),
                ],
                'reservado' => [
                    'bloques_libres' => $reservadoSlots,
                    'sub_bloques_libres_infraestructura' => self::TOTAL_ZONE_SLOTS - 2,
                ],
            ],
        ];
    }

    /**
     * @return array{0: string, 1: int} hex de 32 nibbles (red, host en cero) y longitud de prefijo
     */
    private static function parsePrefix(string $prefijo): array
    {
        if (!str_contains($prefijo, '/')) {
            throw new \InvalidArgumentException("Prefijo inválido (falta '/longitud'): {$prefijo}");
        }
        [$direccion, $longitud] = explode('/', $prefijo, 2);

        if (!ctype_digit($longitud)) {
            throw new \InvalidArgumentException("Longitud de prefijo inválida: {$prefijo}");
        }
        $prefixLen = (int) $longitud;

        if (filter_var($direccion, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            throw new \InvalidArgumentException("Prefijo IPv6 mal formado: {$prefijo}");
        }

        if ($prefixLen < self::MIN_PREFIX_LEN || $prefixLen > self::MAX_PREFIX_LEN) {
            throw new \InvalidArgumentException(sprintf(
                'Longitud de prefijo /%d no soportada (rango soportado: /%d a /%d).',
                $prefixLen,
                self::MIN_PREFIX_LEN,
                self::MAX_PREFIX_LEN
            ));
        }

        if ($prefixLen % 4 !== 0) {
            throw new \InvalidArgumentException(sprintf(
                'Longitud de prefijo /%d no está alineada a nibble; esta versión solo soporta longitudes múltiplo de 4 (/32, /36, /40, /44, /48, /52, /56).',
                $prefixLen
            ));
        }

        $hex = bin2hex((string) inet_pton($direccion));
        // Enmascara los bits de host para trabajar siempre sobre la dirección de red.
        $networkHex = self::maskToNetwork($hex, $prefixLen);

        return [$networkHex, $prefixLen];
    }

    /**
     * Devuelve el hex32 (red, host en cero) del sub-bloque `$index` de longitud
     * `$newLen` dentro del bloque base `$baseHex32` de longitud `$baseLen`.
     * Requiere que ambas longitudes estén alineadas a nibble.
     */
    private static function subBlock(string $baseHex32, int $baseLen, int $index, int $newLen): string
    {
        $diffNibbles = intdiv($newLen - $baseLen, 4);
        $maxIndex = (16 ** $diffNibbles) - 1;
        if ($index < 0 || $index > $maxIndex) {
            throw new \InvalidArgumentException("Índice de sub-bloque fuera de rango ({$index} de {$maxIndex}) al dividir /{$baseLen} en /{$newLen}.");
        }

        $startNibble = intdiv($baseLen, 4);
        $indexHex = str_pad(dechex($index), $diffNibbles, '0', STR_PAD_LEFT);
        $prefixPart = substr($baseHex32, 0, $startNibble);
        $suffixLen = 32 - $startNibble - $diffNibbles;

        return $prefixPart . $indexHex . str_repeat('0', $suffixLen);
    }

    /** Conserva los primeros `$prefixLen` bits (nibble-alineados) y pone el resto en cero. */
    private static function maskToNetwork(string $hex32, int $prefixLen): string
    {
        $nibbles = intdiv($prefixLen, 4);

        return substr($hex32, 0, $nibbles) . str_repeat('0', 32 - $nibbles);
    }

    private static function formatCidr(string $hex32, int $prefixLen): string
    {
        $direccion = inet_ntop((string) hex2bin($hex32));

        return "{$direccion}/{$prefixLen}";
    }

    /**
     * Delegación /D más pequeña (más generosa) cuya capacidad, dentro de una zona
     * /$zonePrefixLen, alcanza a cubrir $clientesRequeridos. Redondeada hacia arriba
     * al múltiplo de 4 siguiente (convención de delegación: /48, /52, /56, /60, /64).
     */
    private static function delegacionRecomendada(int $zonePrefixLen, int $clientesRequeridos): int
    {
        $clientesRequeridos = max($clientesRequeridos, 1);
        $bitsNecesarios = (int) ceil(log($clientesRequeridos, 2));
        $crudo = $zonePrefixLen + max($bitsNecesarios, 0);

        $delegacion = (int) (ceil($crudo / 4) * 4);
        if ($delegacion <= $zonePrefixLen) {
            $delegacion = $zonePrefixLen + 4;
        }

        if ($delegacion > self::MAX_DELEGATION_LEN) {
            throw new \InvalidArgumentException(sprintf(
                'No se puede calcular una delegación válida: se requieren %d clientes proyectados en una zona /%d, y el bloque resultante (/%d) no sería menor (más específico) que /%d, el límite de delegación por cliente.',
                $clientesRequeridos,
                $zonePrefixLen,
                $delegacion,
                self::MAX_DELEGATION_LEN
            ));
        }

        return $delegacion;
    }
}
