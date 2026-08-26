<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * ESTADO EN ARCHIVO DE LA VIGILIA DE THOMAS (entrega A).
 *
 * ── POR QUÉ NO VIVE EN BASE ─────────────────────────────────────────────────────────────────
 * Thomas no puede compartir destino con lo que vigila. Hoy todo lo que sabe está en MySQL
 * (`roadmap_items`, el historial, y el watchdog entero en `settings`), así que cuando la base
 * se cayó el 22-ago el único que podía contarlo se cayó con ella. Su criterio ya vivía en
 * archivo (`config/circuito.php`); su MEMORIA de vigilancia empieza a vivir aquí.
 *
 * Dos archivos, a propósito:
 *   · `latido.json` — diminuto, se reescribe cada vuelta. Es el interruptor de hombre muerto:
 *     la Torre muestra "Thomas midió hace X" y lo pinta en rojo si esa marca envejece.
 *   · `estado.json` — la medición completa de la última vuelta.
 * Separados porque el latido tiene que poder escribirse aunque la medición grande falle a la
 * mitad: si el latido dependiera de que todo salió bien, un Thomas medio roto se vería igual
 * que un Thomas muerto, y son dos cosas distintas.
 *
 * ── RUTA ABSOLUTA ───────────────────────────────────────────────────────────────────────────
 * Nunca `storage_path()`. Este código puede correr desde un worktree, y cada worktree tiene su
 * `storage/` REAL: con ruta relativa habría un Thomas por terminal, que es no tener ninguno.
 * Es la misma lección del centinela del freno (#170).
 *
 * Esta clase NO mide y NO actúa: sólo guarda y devuelve. Quien mide es
 * `circuito:thomas-vigilar`; quien pinta es la Torre.
 */
class ThomasVigilia
{
    public const ARCHIVO_LATIDO = 'latido.json';

    public const ARCHIVO_ESTADO = 'estado.json';

    public static function dir(): string
    {
        return rtrim((string) config(
            'circuito.thomas.vigilia.dir',
            '/var/www/megaisp/storage/app/circuito/thomas'
        ), '/');
    }

    public static function rutaLatido(): string
    {
        return self::dir() . '/' . self::ARCHIVO_LATIDO;
    }

    public static function rutaEstado(): string
    {
        return self::dir() . '/' . self::ARCHIVO_ESTADO;
    }

    /** Umbral del hombre muerto: pasado esto, el latido está viejo y la Torre lo pinta en rojo. */
    public static function umbralLatidoSeg(): int
    {
        return max(30, (int) config('circuito.thomas.vigilia.latido_umbral_seg', 180));
    }

    /**
     * Escribe latido + estado. ATÓMICO (tmp + rename en el mismo filesystem): un lector jamás ve
     * un archivo a medio escribir, y un corte deja el anterior intacto en vez de un JSON corrupto
     * que alguien tendría que interpretar — interpretar es justo lo que no se puede hacer cuando
     * el sistema está mal.
     *
     * El latido se escribe DESPUÉS del estado: si la escritura grande falla, el latido viejo se
     * queda viejo y el hombre muerto se dispara. Preferimos que Thomas se declare muerto a que
     * se declare vivo mostrando una medición que no pudo guardar.
     */
    public static function guardar(array $estado): void
    {
        $dir = self::dir();
        @mkdir($dir, 0775, true);

        self::escribirAtomico(self::rutaEstado(), $estado);

        self::escribirAtomico(self::rutaLatido(), [
            'medido_en'  => date('c'),
            'medido_ts'  => time(),
            'pid'        => getmypid(),
            'hostname'   => gethostname(),
            'modo'       => $estado['modo'] ?? 'desconocido',
            'alertas'    => count($estado['alertas'] ?? []),
        ]);
    }

    /** @return array{medido_ts:int,medido_en:string,modo:string,alertas:int,pid:int}|null */
    public static function latido(): ?array
    {
        return self::leer(self::rutaLatido());
    }

    public static function estado(): ?array
    {
        return self::leer(self::rutaEstado());
    }

    /**
     * Segundos desde la última medición, o null si nunca midió. `null` y `9999` no son lo mismo:
     * "nunca arrancó" y "se murió hace rato" se arreglan distinto, así que no se colapsan.
     */
    public static function edadSeg(): ?int
    {
        $l = self::latido();
        if ($l === null || empty($l['medido_ts'])) {
            return null;
        }

        return max(0, time() - (int) $l['medido_ts']);
    }

    /** ¿El latido envejeció más allá del umbral? Sin latido = true: la ausencia también es muerte. */
    public static function hombreMuerto(): bool
    {
        $edad = self::edadSeg();

        return $edad === null || $edad > self::umbralLatidoSeg();
    }

    private static function leer(string $ruta): ?array
    {
        clearstatcache(true, $ruta);
        if (! is_readable($ruta)) {
            return null;
        }
        $j = json_decode((string) @file_get_contents($ruta), true);

        return is_array($j) ? $j : null;
    }

    private static function escribirAtomico(string $ruta, array $carga): void
    {
        $tmp = $ruta . '.tmp.' . getmypid();
        $json = json_encode($carga, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if ($json === false || @file_put_contents($tmp, $json . "\n") === false || ! @rename($tmp, $ruta)) {
            @unlink($tmp);
            throw new \RuntimeException("No se pudo escribir el estado de la vigilia en {$ruta}.");
        }
        @chmod($ruta, 0664);
    }
}
