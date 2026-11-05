<?php

namespace App\Modules\Addons\Roadmap\Support;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;

/**
 * GASTO DE IA POR PIEZA (#1076) — lee el log del canal `claude` (storage/logs/claude-api-*.log).
 *
 * Cada línea «Claude API call» trae el modelo, el status, el `usage` y —desde #1076— la `pieza`
 * del supervisor y el costo en USD/MXN. Las líneas viejas (sin pieza ni costo) se cuentan bajo
 * «(sin etiqueta)» y su costo se recalcula del `usage`, para poder comparar antes/después.
 *
 * Solo lectura. El log del canal `claude` se guarda 7 días (config/logging.php).
 */
final class GastoIa
{
    public const SIN_ETIQUETA = '(sin etiqueta)';

    /**
     * Líneas de los logs entre dos fechas (Y-m-d, inclusivas).
     *
     * @return \Generator<int, array{fecha:string, datos:array}>
     */
    public static function lineas(string $dir, string $desde, string $hasta): \Generator
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $desde);
        $h = \DateTimeImmutable::createFromFormat('!Y-m-d', $hasta);
        if (! $d || ! $h) {
            return;
        }

        for ($f = $d; $f <= $h; $f = $f->modify('+1 day')) {
            $ruta = rtrim($dir, '/') . '/claude-api-' . $f->format('Y-m-d') . '.log';
            if (! is_readable($ruta)) {
                continue;
            }
            $fh = fopen($ruta, 'r');
            if (! $fh) {
                continue;
            }
            while (($linea = fgets($fh)) !== false) {
                $datos = self::parsear($linea);
                if ($datos !== null) {
                    yield ['fecha' => $f->format('Y-m-d'), 'datos' => $datos];
                }
            }
            fclose($fh);
        }
    }

    /** JSON de una línea «Claude API call», o null si la línea es otra cosa. */
    public static function parsear(string $linea): ?array
    {
        $pos = strpos($linea, 'Claude API call ');
        if ($pos === false) {
            return null;
        }
        $json = json_decode(trim(substr($linea, $pos + strlen('Claude API call '))), true);

        return is_array($json) ? $json : null;
    }

    /**
     * Suma por pieza + modelo (y por día si se pide).
     *
     * @param iterable<array{fecha:string, datos:array}> $lineas
     * @return array<string, array> llave "pieza|modelo" (o "fecha|pieza|modelo")
     */
    public static function sumar(iterable $lineas, bool $porDia = false, ?float $tipoCambio = null): array
    {
        $tc    = $tipoCambio ?? ClaudeApiClient::tipoCambioMxn();
        $filas = [];

        foreach ($lineas as $l) {
            $d      = $l['datos'];
            $pieza  = (string) ($d['pieza'] ?? self::SIN_ETIQUETA);
            $modelo = (string) ($d['model'] ?? '?');
            $llave  = ($porDia ? $l['fecha'] . '|' : '') . $pieza . '|' . $modelo;

            $f = $filas[$llave] ?? [
                'fecha' => $porDia ? $l['fecha'] : null, 'pieza' => $pieza, 'modelo' => $modelo,
                'llamadas' => 0, 'ok' => 0, 'errores' => 0,
                'entrada' => 0, 'cache_escritura' => 0, 'cache_lectura' => 0, 'salida' => 0, 'razonamiento' => 0,
                'usd' => 0.0,
            ];

            $f['llamadas']++;
            $usage = is_array($d['usage'] ?? null) ? $d['usage'] : null;
            if ((int) ($d['status'] ?? 0) === 200 && $usage) {
                $f['ok']++;
                $f['entrada']         += (int) ($usage['input_tokens'] ?? 0);
                $f['cache_escritura'] += (int) ($usage['cache_creation_input_tokens'] ?? 0);
                $f['cache_lectura']   += (int) ($usage['cache_read_input_tokens'] ?? 0);
                $f['salida']          += (int) ($usage['output_tokens'] ?? 0);
                $f['razonamiento']    += (int) data_get($usage, 'output_tokens_details.thinking_tokens', 0);
                $f['usd'] += isset($d['costo_usd'])
                    ? (float) $d['costo_usd']
                    : ClaudeApiClient::costoUsd($usage, $modelo);
            } else {
                $f['errores']++;
            }

            $filas[$llave] = $f;
        }

        foreach ($filas as &$f) {
            $f['usd'] = round($f['usd'], 6);
            $f['mxn'] = round($f['usd'] * $tc, 4);
            $f['mxn_por_llamada'] = $f['ok'] > 0 ? round($f['mxn'] / $f['ok'], 4) : 0.0;
        }
        unset($f);

        uasort($filas, fn ($a, $b) => [$a['fecha'], -$a['usd']] <=> [$b['fecha'], -$b['usd']]);

        return $filas;
    }
}
