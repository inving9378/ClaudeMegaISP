<?php

namespace App\Services\Core;

use Throwable;

/**
 * SALUD DE LA IA DEL SISTEMA (#1073).
 *
 * Del 22 al 30-oct-2026 el supervisor trabajó sin modelo y nadie se enteró: la llave daba 401 y
 * además la cuenta no tenía saldo. El circuito no se cae cuando eso pasa: escala todo a Irving y
 * parece prudente. Aquí cada llamada a un proveedor anota si respondió bien o, si no, la causa
 * que la deja ciega; el vigilante (`circuito:jarvis-vigilar`) y la cabecera de la Torre lo leen.
 *
 * En ARCHIVO, no en BD (mismo criterio que la vigilia): lo escriben la web, el cron, las colas y
 * las terminales, y tiene que poder leerse aunque la base falle. Nunca lanza: anotar la salud
 * jamás puede tumbar la llamada que la reporta.
 */
final class SaludIA
{
    public const LLAVE_RECHAZADA = 'llave_rechazada';
    public const SIN_SALDO       = 'sin_saldo';
    public const LIMITE_GASTO    = 'limite_gasto';
    public const CUOTA           = 'cuota';

    /** Un 429 suelto es pasajero (el cliente reintenta); solo cuenta como ceguera si dura esto. */
    public const CUOTA_SOSTENIDA_SEG = 600;

    private const TEXTOS = [
        self::LLAVE_RECHAZADA => 'el proveedor rechaza la llave',
        self::SIN_SALDO       => 'la cuenta no tiene saldo',
        self::LIMITE_GASTO    => 'se alcanzó el límite de gasto de la cuenta',
        self::CUOTA           => 'el proveedor está limitando las llamadas (cuota)',
    ];

    /** Ruta fija para pruebas; en la app sale de `config('circuito.ia_salud.archivo')`. */
    private static ?string $archivo = null;

    public static function usarArchivo(?string $ruta): void
    {
        self::$archivo = $ruta;
    }

    /**
     * Causa que deja ciega a la IA, o null si la falla es pasajera (5xx, sobrecarga): esas se
     * reintentan y no se avisan. El texto importa: el saldo agotado llega como 400 en Anthropic
     * y como 429 «insufficient_quota» en OpenAI.
     */
    public static function clasificar(int $status, string $mensaje = ''): ?string
    {
        if ($status === 401 || $status === 403) {
            return self::LLAVE_RECHAZADA;
        }
        if ($status < 400) {
            return null;
        }
        if (preg_match('/credit balance|insufficient_quota|billing/i', $mensaje)) {
            return self::SIN_SALDO;
        }
        if (preg_match('/usage limit|spend(ing)? limit/i', $mensaje)) {
            return self::LIMITE_GASTO;
        }

        return $status === 429 ? self::CUOTA : null;
    }

    public static function texto(string $causa): string
    {
        return self::TEXTOS[$causa] ?? $causa;
    }

    /** Anota el resultado de una llamada real a `$proveedor` (anthropic, openai…). */
    public static function registrar(string $proveedor, string $origen, int $status, string $mensaje = ''): void
    {
        $ahora = time();
        $causa = $status >= 200 && $status < 300 ? null : self::clasificar($status, $mensaje);
        if ($causa === null && ($status < 200 || $status >= 300)) {
            return;
        }

        self::modificar(function (array $d) use ($proveedor, $origen, $causa, $ahora, $status) {
            $p = $d['proveedores'][$proveedor] ?? [];
            if ($causa === null) {
                $p['ultimo_ok'] = $ahora;
                $p['ultimo_ok_origen'] = $origen;
                unset($p['falla']);
            } else {
                $f = $p['falla'] ?? null;
                if (! $f || $f['causa'] !== $causa) {
                    $f = ['causa' => $causa, 'desde' => $ahora, 'conteo' => 0];
                }
                $f['ultima'] = $ahora;
                $f['conteo']++;
                $f['status'] = $status;
                $f['origen'] = $origen;
                $p['falla'] = $f;
            }
            $d['proveedores'][$proveedor] = $p;

            return $d;
        });
    }

    /** Marca que el latido corrió (haya salido bien o mal: el resultado ya lo anotó `registrar`). */
    public static function marcarLatido(string $proveedor, bool $ok, ?string $error = null): void
    {
        self::modificar(function (array $d) use ($proveedor, $ok, $error) {
            $d['latido'][$proveedor] = ['at' => time(), 'ok' => $ok, 'error' => $error ? mb_substr($error, 0, 200) : null];

            return $d;
        });
    }

    /**
     * Proveedores ciegos ahora: [proveedor => [causa, texto, desde, ultima, conteo, origen]].
     * Una cuota (429) solo cuenta si lleva CUOTA_SOSTENIDA_SEG sin una respuesta buena.
     */
    public static function ciegas(?int $ahora = null): array
    {
        $ahora = $ahora ?? time();
        $res = [];
        foreach ((self::leer()['proveedores'] ?? []) as $prov => $p) {
            $f = $p['falla'] ?? null;
            if (! $f) {
                continue;
            }
            if ($f['causa'] === self::CUOTA && ($ahora - (int) $f['desde']) < self::CUOTA_SOSTENIDA_SEG) {
                continue;
            }
            $res[$prov] = $f + ['texto' => self::texto($f['causa'])];
        }

        return $res;
    }

    public static function estado(): array
    {
        return self::leer();
    }

    private static function ruta(): ?string
    {
        if (self::$archivo !== null) {
            return self::$archivo;
        }
        try {
            $r = (string) config('circuito.ia_salud.archivo', '');
        } catch (Throwable) {
            return null;
        }

        return $r !== '' ? $r : null;
    }

    private static function leer(): array
    {
        $ruta = self::ruta();
        if (! $ruta || ! is_file($ruta)) {
            return [];
        }
        $d = json_decode((string) @file_get_contents($ruta), true);

        return is_array($d) ? $d : [];
    }

    /** Lee-modifica-escribe con candado: varias llamadas pueden terminar al mismo tiempo. */
    private static function modificar(callable $fn): void
    {
        try {
            $ruta = self::ruta();
            if (! $ruta) {
                return;
            }
            $dir = dirname($ruta);
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                return;
            }
            $nuevo = ! is_file($ruta);
            $h = @fopen($ruta, 'c+');
            if (! $h) {
                return;
            }
            try {
                flock($h, LOCK_EX);
                $d = json_decode((string) stream_get_contents($h), true);
                $d = $fn(is_array($d) ? $d : []);
                ftruncate($h, 0);
                rewind($h);
                fwrite($h, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                fflush($h);
            } finally {
                flock($h, LOCK_UN);
                fclose($h);
            }
            if ($nuevo) {
                // Lo escriben meganet (cron, terminales) y www-data (web, colas).
                @chmod($ruta, 0664);
            }
        } catch (Throwable) {
            // Nunca tumba la llamada que reporta.
        }
    }
}
