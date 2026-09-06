<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * EL FRENO DE MANO, FUERA DE LA BASE (#170).
 *
 * ── POR QUÉ UN ARCHIVO Y NO UNA FILA ────────────────────────────────────────────────────────────
 *
 * El kill switch vivía SÓLO en `settings.circuito_pausado`. Eso significa que el único mecanismo
 * capaz de detener seis terminales con permiso de escritura sobre el repo depende de que MySQL
 * conteste. Con la base caída, `isPaused()` lanzaba, y la excepción se propagaba hacia arriba en
 * unos caminos y se tragaba en otros: el freno dejaba de existir justo cuando más falta hacía.
 *
 * El centinela es un archivo. **Su existencia es la pausa.** No hay que interpretar su contenido
 * para saber si está puesto — leerlo mal no puede soltar el freno.
 *
 * ── POR QUÉ ESTA RUTA Y NO `storage_path()` ─────────────────────────────────────────────────────
 *
 * ⚠️ Ruta ABSOLUTA y literal, nunca `storage_path()`. `vuelta.sh` hace `cd` al worktree del slot
 * (`wt-1`…`wt-6`) y corre `php artisan` DESDE AHÍ, y cada worktree tiene su propio `storage/` real
 * (no es un symlink al checkout principal — verificado el 2026-08-25). Con `storage_path()` cada
 * una de las seis terminales tendría su propio freno privado, y un freno que sólo detiene a una
 * terminal no es un freno: es un botón que miente en la Torre.
 *
 * La ruta está en `config('circuito.freno.centinela')` como string literal por el mismo motivo por
 * el que ya lo están `revisor.perfil_path` y las demás rutas del circuito.
 *
 * ── PERMISOS ────────────────────────────────────────────────────────────────────────────────────
 *
 * `storage/app/circuito` es `meganet:www-data` con setgid: lo escriben y lo leen tanto el cron/
 * ejecutor (`meganet`) como la app web (`www-data`). **`/home/meganet` es 0700**, así que el
 * runtime del circuito NO sirve: la Torre no podría leer el centinela desde ahí y mostraría
 * "suelto" con el freno puesto. Ese fue el descarte, y no es reversible sin cambiar permisos de
 * un home.
 *
 * El directorio está cubierto por `storage/app/.gitignore` (`*`), así que el `git clean -fdq` que
 * `vuelta.sh` corre en cada vuelta NO lo toca (clean sin `-x` no borra ignorados).
 */
class FrenoCircuito
{
    /** Dónde se registran los fallos del propio freno. En ARCHIVO: si la base es el problema,
     *  escribir el fallo en la base es perder justo el rastro que explica por qué se frenó. */
    private const LOG = '/var/www/megaisp/storage/app/circuito/freno-fallos.log';

    public static function ruta(): string
    {
        return (string) config('circuito.freno.centinela', '/var/www/megaisp/storage/app/circuito/PAUSA');
    }

    /** ¿Está puesto el freno? La EXISTENCIA del archivo es la respuesta; su contenido no se lee. */
    public static function activo(): bool
    {
        // `clearstatcache` porque en una misma petición PHP cachea el resultado de file_exists, y
        // el proceso que pone el freno puede ser OTRO (el cron, la Torre) en el mismo segundo.
        clearstatcache(true, self::ruta());

        return file_exists(self::ruta());
    }

    /**
     * Pone el freno. Escritura ATÓMICA (tmp + rename en el mismo filesystem): un lector nunca ve
     * un centinela a medio escribir, y un corte a media escritura deja el freno puesto o no puesto,
     * jamás un archivo corrupto que haya que interpretar.
     *
     * `$expiraEn` es OPCIONAL (#9990417 — FASE 4a). Con `null` el JSON queda IDÉNTICO al de
     * siempre: el freno manual (#342, botón de la Torre / `setPaused()`) y `circuito:pausar` NO
     * pasan este argumento y nunca se autolimpian. Solo un freno que declaró explícitamente cuándo
     * vence puede expirar solo — ver `expirado()`.
     */
    public static function poner(string $motivo, string $quien, ?string $expiraEn = null): void
    {
        $ruta = self::ruta();
        @mkdir(dirname($ruta), 0775, true);

        $datos = [
            'motivo' => $motivo,
            'quien'  => $quien,
            'cuando' => date('c'),
        ];
        if ($expiraEn !== null) {
            $datos['expira_en'] = $expiraEn;
        }

        $carga = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $tmp = $ruta . '.tmp.' . getmypid();
        if (@file_put_contents($tmp, $carga . "\n") === false || ! @rename($tmp, $ruta)) {
            @unlink($tmp);
            throw new \RuntimeException("No se pudo escribir el centinela del freno en {$ruta}.");
        }
        @chmod($ruta, 0664);
    }

    /** Quita el freno. Idempotente: quitar un freno que no está puesto no es un error. */
    public static function quitar(): void
    {
        $ruta = self::ruta();
        if (file_exists($ruta) && ! @unlink($ruta)) {
            throw new \RuntimeException("No se pudo borrar el centinela {$ruta} (¿permisos?).");
        }
        clearstatcache(true, $ruta);
    }

    /** Motivo/quién/cuándo del freno vigente, o null. Puro-lectura y tolerante: si el archivo está
     *  ilegible devuelve lo que sabe seguro —que está puesto— sin fingir que sabe por qué. */
    public static function detalle(): ?array
    {
        if (! self::activo()) {
            return null;
        }

        $crudo = @file_get_contents(self::ruta());
        $d     = is_string($crudo) ? json_decode($crudo, true) : null;

        return is_array($d) ? $d : [
            'motivo' => 'Centinela presente pero ilegible: el freno está PUESTO de todas formas.',
            'quien'  => null,
            'cuando' => null,
        ];
    }

    /**
     * ¿Ya venció este freno? (#9990417 — FASE 4a)
     *
     * Un freno SIN `expira_en` (el manual de #342, `circuito:pausar`) nunca vence: devuelve
     * `false` siempre, sin importar cuánto tiempo lleve puesto. Tolerante ante datos corruptos —
     * una fecha ilegible NO autolimpia el freno (ante la duda, sigue frenado).
     */
    public static function expirado(): bool
    {
        $d = self::detalle();
        if (! is_array($d) || ! isset($d['expira_en'])) {
            return false;
        }

        try {
            return \Carbon\Carbon::parse($d['expira_en'])->isPast();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Deja rastro de un fallo del freno EN ARCHIVO, nunca en base. Best-effort absoluto: si ni
     * siquiera se puede escribir el log, se traga — un fallo registrando no puede convertirse en
     * un fallo frenando.
     */
    public static function registrarFallo(string $contexto, \Throwable $e): void
    {
        try {
            @file_put_contents(
                self::LOG,
                sprintf("[%s] %s: %s%s", date('c'), $contexto, $e->getMessage(), PHP_EOL),
                FILE_APPEND
            );
        } catch (\Throwable) {
            // Silencio deliberado.
        }
    }
}
