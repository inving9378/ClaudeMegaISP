<?php

namespace App\Services\Metricas;

/**
 * Un bloque IPv4 en CIDR (item #988): el que se le entregó al dedicado, capturado o corregido a
 * mano. Si se escribe con una IP de host (203.0.113.121/29) se normaliza a la red
 * (203.0.113.120/29). Pura, para probarla sin base de datos.
 */
final class BloqueIpv4
{
    private function __construct(
        public readonly int $red,
        public readonly int $prefijo,
    ) {
    }

    public static function desde(?string $cidr): ?self
    {
        if (! preg_match('#^\s*(\d{1,3}(?:\.\d{1,3}){3})\s*/\s*(\d{1,2})\s*$#', (string) $cidr, $m)) {
            return null;
        }
        $ip = ip2long($m[1]);
        $prefijo = (int) $m[2];
        if ($ip === false || $prefijo < 0 || $prefijo > 32) {
            return null;
        }

        return new self($ip & self::mascara($prefijo), $prefijo);
    }

    public function cidr(): string
    {
        return long2ip($this->red) . '/' . $this->prefijo;
    }

    public function contiene(?string $ip): bool
    {
        $n = ip2long(trim((string) $ip));

        return $n !== false && ($n & self::mascara($this->prefijo)) === $this->red;
    }

    /**
     * IPs utilizables: sin la de red ni la de broadcast (salvo /31 y /32, donde todas cuentan).
     *
     * @return string[]
     */
    public function hosts(): array
    {
        $total = 2 ** (32 - $this->prefijo);
        [$desde, $hasta] = $this->prefijo >= 31 ? [0, $total - 1] : [1, $total - 2];

        $ips = [];
        for ($i = $desde; $i <= $hasta; $i++) {
            $ips[] = long2ip($this->red + $i);
        }

        return $ips;
    }

    /** ¿Es una IP utilizable del bloque (no la de red ni la de broadcast)? */
    public function esHost(?string $ip): bool
    {
        if (! $this->contiene($ip)) {
            return false;
        }
        if ($this->prefijo >= 31) {
            return true;
        }
        $n = ip2long(trim((string) $ip));

        return $n !== $this->red && $n !== $this->red + 2 ** (32 - $this->prefijo) - 1;
    }

    private static function mascara(int $prefijo): int
    {
        return $prefijo === 0 ? 0 : (0xFFFFFFFF << (32 - $prefijo)) & 0xFFFFFFFF;
    }
}
