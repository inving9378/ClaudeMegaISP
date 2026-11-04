<?php

namespace App\Services\Metricas;

/**
 * Resultado de un ping desde el router a una IP del cliente (item #988). Tiempos en ms con
 * decimales: en estas VLAN la latencia real es sub-milisegundo (~0.3 ms).
 */
final class LecturaPing
{
    /** Estados de RouterOS que significan que el paquete no volvió. */
    private const SIN_RESPUESTA = ['timeout', 'host unreachable', 'net unreachable', 'no route to host', 'packet too large'];

    /**
     * @param  float[]  $rttsMs  un tiempo por paquete que volvió
     * @param  array<int, array{seq: ?int, ms: ?float, estado: string}>  $detalle  uno por paquete
     *         tal como contestó el router, también los que no volvieron (lo imprime el dry-run)
     */
    public function __construct(
        public readonly int $enviados,
        public readonly array $rttsMs,
        public readonly ?string $error = null,
        public readonly array $detalle = [],
    ) {
    }

    public static function conError(int $enviados, string $error): self
    {
        return new self($enviados, [], $error);
    }

    /**
     * Desde las respuestas de /ping de la API (una por paquete). Un paquete volvió si trae
     * `time` y no trae un estado de falla.
     *
     * @param  array<int, array<string, string|null>>  $filas
     */
    public static function desdeRespuestas(array $filas, int $paquetes): self
    {
        $rtts = [];
        $detalle = [];
        $enviados = $paquetes;
        foreach ($filas as $f) {
            $enviados = max($enviados, (int) ($f['sent'] ?? 0));
            $estado = strtolower(trim((string) ($f['status'] ?? '')));
            $ms = self::tiempoEnMs($f['time'] ?? null);
            $volvio = $ms !== null && ($estado === '' || ! in_array($estado, self::SIN_RESPUESTA, true));
            if ($volvio) {
                $rtts[] = $ms;
            }
            $detalle[] = [
                'seq'    => ctype_digit((string) ($f['seq'] ?? '')) ? (int) $f['seq'] : null,
                'ms'     => $volvio ? $ms : null,
                'estado' => $volvio ? 'ok' : ($estado !== '' ? $estado : 'sin respuesta'),
            ];
        }

        return new self($enviados, $rtts, null, $detalle);
    }

    /**
     * Tiempo de RouterOS a milisegundos: v7 da «356us», «1ms234us», «12ms», «1s5ms»; v6 da
     * «12ms» y «<1ms» (que se toma como 0.5, igual que mikrotik:sync-ping).
     */
    public static function tiempoEnMs(?string $tiempo): ?float
    {
        $t = trim((string) $tiempo);
        if ($t === '') {
            return null;
        }
        if (str_starts_with($t, '<')) {
            $valor = self::tiempoEnMs(substr($t, 1));

            return $valor === null ? null : $valor / 2;
        }
        if (! preg_match_all('/(\d+(?:\.\d+)?)\s*(ms|us|s)/', $t, $m, PREG_SET_ORDER)) {
            return null;
        }

        $ms = 0.0;
        foreach ($m as [, $n, $unidad]) {
            $ms += match ($unidad) {
                's'  => (float) $n * 1000,
                'ms' => (float) $n,
                'us' => (float) $n / 1000,
            };
        }

        return round($ms, 3);
    }

    public function recibidos(): int
    {
        return count($this->rttsMs);
    }

    public function responde(): bool
    {
        return $this->error === null && $this->rttsMs !== [];
    }

    public function minMs(): ?float
    {
        return $this->rttsMs === [] ? null : min($this->rttsMs);
    }

    public function avgMs(): ?float
    {
        return $this->rttsMs === [] ? null : round(array_sum($this->rttsMs) / count($this->rttsMs), 3);
    }

    public function maxMs(): ?float
    {
        return $this->rttsMs === [] ? null : max($this->rttsMs);
    }

    public function perdidaPct(): float
    {
        return $this->enviados > 0 ? round((1 - $this->recibidos() / $this->enviados) * 100, 2) : 100.0;
    }
}
