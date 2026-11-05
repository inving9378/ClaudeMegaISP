<?php

namespace App\Services\Metricas;

use App\Models\Router;
use RuntimeException;

/**
 * Métricas SIMULADAS para construir y probar la pantalla en DEV sin tocar los routers reales
 * (item #988). Las filas que produce quedan con origen 'simulado' y la pantalla avisa
 * «Datos simulados».
 *
 * Bloqueado en producción (EntornoMetricas: APP_ENV production o base en metricas.bases_produccion).
 *
 * Tráfico: la velocidad sigue una curva diaria (pico a las 15:00, valle de madrugada) con dos
 * ondas más cortas, y el contador es su integral exacta, así que siempre crece y la diferencia
 * entre dos lecturas da esa velocidad. Ping: sub-milisegundo (~0.2–0.4 ms) con algún pico,
 * pérdida parcial ocasional y ratos en que un equipo no responde. Todo sale del tiempo y de una
 * semilla, sin estado.
 */
final class SimuladoMetricasDriver implements MetricasDriver
{
    /** [base, diaria, por hora, cada 10 min] como fracción de la capacidad; base > suma de ondas. */
    private const CURVA = [
        'bajada' => [0.35, 0.22, 0.05, 0.03],
        'subida' => [0.18, 0.10, 0.03, 0.02],
    ];

    /** Bytes promedio por paquete, para que los paquetes sigan a los bytes. */
    private const BYTES_POR_PAQUETE = ['bajada' => 1100, 'subida' => 400];

    private const DIA = 86400;

    /** El pico diario cae a las 15:00 hora de México (UTC-6). */
    private const SEGUNDO_DEL_PICO = 15 * 3600 + 6 * 3600;

    public function __construct()
    {
        if (! self::permitido()) {
            throw new RuntimeException('Las métricas simuladas están bloqueadas en producción.');
        }
    }

    public static function permitido(): bool
    {
        return ! EntornoMetricas::esProduccion();
    }

    public function nombre(): string
    {
        return 'simulado';
    }

    public function abrir(Router $router): SesionRouter
    {
        return new SimuladoSesion($router);
    }

    /** Semilla del tráfico de una interfaz: por nombre, o por VLAN ID si no hay nombre. */
    public static function semilla(int $routerId, ?string $interfaz, ?int $vlanId): ?string
    {
        if (trim((string) $interfaz) !== '') {
            return $routerId . '|' . $interfaz;
        }

        return $vlanId ? $routerId . '|vlan:' . $vlanId : null;
    }

    /** Velocidad simulada (bits/s) en el instante $t. */
    public static function tasaBps(string $semilla, string $sentido, float $t): int
    {
        [$capacidad, $ondas] = self::parametros($semilla, $sentido);
        $f = $ondas[0][0];
        foreach (array_slice($ondas, 1) as [$amplitud, $omega, $fase]) {
            $f += $amplitud * sin($omega * $t + $fase);
        }

        return (int) round($capacidad * $f);
    }

    /** Bytes por segundo de la tasa, integrados desde t=0: siempre crece. */
    public static function bytesAcumulados(string $semilla, string $sentido, float $t): int
    {
        [$capacidad, $ondas] = self::parametros($semilla, $sentido);
        $integral = $ondas[0][0] * $t;
        foreach (array_slice($ondas, 1) as [$amplitud, $omega, $fase]) {
            $integral += $amplitud * (1 - cos($omega * $t + $fase)) / $omega;
        }

        return (int) floor($capacidad / 8 * $integral);
    }

    /** Paquetes por segundo que corresponden a una tasa en bits/s. */
    public static function ppsDe(int $bps, string $sentido): int
    {
        return intdiv(intdiv($bps, 8), self::BYTES_POR_PAQUETE[$sentido]);
    }

    /** Lectura de contadores simulada de una interfaz en el instante $t. */
    public static function lectura(string $semilla, float $t): LecturaInterfaz
    {
        $bajada = self::bytesAcumulados($semilla, 'bajada', $t);
        $subida = self::bytesAcumulados($semilla, 'subida', $t);

        // En la interfaz del router: tx = lo que baja al cliente, rx = lo que sube.
        return new LecturaInterfaz(
            rxByte: $subida,
            txByte: $bajada,
            rxPacket: intdiv($subida, self::BYTES_POR_PAQUETE['subida']),
            txPacket: intdiv($bajada, self::BYTES_POR_PAQUETE['bajada']),
            running: true,
            leidoEn: $t,
        );
    }

    /**
     * Ping simulado a una IP en el minuto de $t: ~0.2–0.4 ms; un pico de 2.5–8 ms un par de veces
     * al día; un paquete perdido de vez en cuando; y algunas horas en que ese equipo no responde.
     */
    public static function ping(string $ip, float $t, int $paquetes): LecturaPing
    {
        $minuto = (int) floor($t / 60);
        $h = crc32($ip);
        if (crc32($ip . '|h' . intdiv($minuto, 60)) % 97 === 0) {
            return new LecturaPing($paquetes, []);
        }

        $base = 0.22 + ($h % 20) / 100;
        $pico = crc32($ip . '|p' . $minuto) % 997 === 0 ? 2.5 + (crc32($ip . '|q' . $minuto) % 55) / 10 : 0.0;
        $pierde = crc32($ip . '|l' . $minuto) % 50 === 0 ? 1 : 0;

        $rtts = [];
        for ($i = 0; $i < $paquetes - $pierde; $i++) {
            $rtts[] = round($base + $pico + 0.05 * sin($minuto / 7 + $i + $h % 13), 3);
        }

        return new LecturaPing($paquetes, $rtts);
    }

    /**
     * Capacidad (100–500 Mbps según la semilla) y ondas [amplitud, omega, fase]; la primera
     * entrada es la base.
     *
     * @return array{0:int, 1:array<int, array{0:float, 1?:float, 2?:float}>}
     */
    private static function parametros(string $semilla, string $sentido): array
    {
        $h = crc32($semilla);
        $capacidad = (100 + $h % 401) * 1_000_000;
        [$base, $diaria, $hora, $diez] = self::CURVA[$sentido];

        $omegaDia = 2 * M_PI / self::DIA;

        return [$capacidad, [
            [$base],
            [$diaria, $omegaDia, M_PI / 2 - $omegaDia * self::SEGUNDO_DEL_PICO],
            [$hora, 2 * M_PI / 3600, ($h % 360) * M_PI / 180],
            [$diez, 2 * M_PI / 600, (($h >> 9) % 360) * M_PI / 180],
        ]];
    }
}
