<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * REGISTRO DE PROCESOS DEL CIRCUITO — prerrequisito de cualquier autoridad para matar.
 *
 * ── POR QUÉ EXISTE ──────────────────────────────────────────────────────────────────────────
 * El encargo dice que el vigilante sólo puede matar procesos que identifique como del circuito
 * "por su propio registro". Ese registro no existía: `circuito_ejecuciones` guarda UNA fila, se
 * escribe al TERMINAR la vuelta y no guarda PID. Sin él, la única forma de identificar un proceso
 * es por el nombre del binario — y medido el 2026-08-25, `ps | grep claude` devuelve once
 * coincidencias que incluyen la sesión interactiva de Claude Code con la que Irving trabaja, más
 * cuatro sesiones abandonadas de 41 días dentro de un `tmux`. Un `pkill claude` es autoinmune.
 *
 * Así que la regla es al revés de como suele escribirse: no se enumera lo prohibido, se enumera
 * lo propio. Lo que no está en este registro NO ES DEL CIRCUITO, por sospechoso que se vea.
 *
 * ── IDENTIDAD = PID + STARTTIME ─────────────────────────────────────────────────────────────
 * Un PID se recicla. Si sólo guardáramos el número, un registro viejo podría apuntar a un proceso
 * nuevo y ajeno — y ese es exactamente el error que convierte un vigilante en un arma. El campo 22
 * de `/proc/<pid>/stat` (jiffies desde el arranque del sistema) es inmutable para ese proceso: si
 * no coincide, el PID se reusó y la entrada está muerta, no viva.
 *
 * ── QUIÉN ESCRIBE ───────────────────────────────────────────────────────────────────────────
 * Lo escribe `deploy/circuito/vuelta.sh` EN BASH, no PHP: tiene que funcionar con MySQL caído y
 * con la app rota, que es justo cuando hace falta saber quién está corriendo. PHP sólo lee.
 *
 * ── LO QUE ESTA CLASE NO TIENE, A PROPÓSITO ─────────────────────────────────────────────────
 * No hay `matar()`. La entrega A construye el registro; la autoridad se otorga después y por
 * separado. Un registro sin autoridad es inútil pero inofensivo; una autoridad sin registro es
 * lo contrario de las dos cosas.
 */
class RegistroPids
{
    public static function dir(): string
    {
        return JarvisVigilia::dir() . '/pids';
    }

    /**
     * Todo lo registrado, cada entrada con su veredicto de vida.
     *
     * @return array<int,array{sid:string,pid:int,vivo:bool,motivo:string,edad_seg:?int,item:?string}>
     */
    public static function todos(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.json') ?: [] as $ruta) {
            $e = json_decode((string) @file_get_contents($ruta), true);
            if (! is_array($e) || empty($e['pid'])) {
                $out[] = [
                    'sid' => basename($ruta, '.json'), 'pid' => 0, 'vivo' => false,
                    'motivo' => 'registro ilegible', 'edad_seg' => null, 'item' => null,
                    'archivo' => $ruta,
                ];
                continue;
            }

            [$vivo, $motivo] = self::verificar((int) $e['pid'], $e['starttime'] ?? null);

            $out[] = [
                'sid'      => (string) ($e['sid'] ?? basename($ruta, '.json')),
                'pid'      => (int) $e['pid'],
                'pgid'     => isset($e['pgid']) ? (int) $e['pgid'] : null,
                'item'     => isset($e['item']) && $e['item'] !== '' ? (string) $e['item'] : null,
                'wt'       => $e['wt'] ?? null,
                'log'      => $e['log'] ?? null,
                'modelo'   => $e['modelo'] ?? null,
                'timeout'  => isset($e['timeout']) ? (int) $e['timeout'] : null,
                'desde_ts' => isset($e['desde_ts']) ? (int) $e['desde_ts'] : null,
                'edad_seg' => isset($e['desde_ts']) ? max(0, time() - (int) $e['desde_ts']) : null,
                'vivo'     => $vivo,
                'motivo'   => $motivo,
                'archivo'  => $ruta,
            ];
        }

        usort($out, fn ($a, $b) => strcmp((string) $a['sid'], (string) $b['sid']));

        return $out;
    }

    /** Las vueltas realmente vivas según el registro. */
    public static function vivos(): array
    {
        return array_values(array_filter(self::todos(), fn ($e) => $e['vivo']));
    }

    /**
     * Entradas cuyo proceso ya no existe: el registro quedó colgado porque la vuelta murió sin
     * pasar por su `trap`. Es basura de registro, no un problema del sistema — pero se reporta,
     * porque un registro que miente sobre quién vive es peor que no tenerlo.
     */
    public static function colgados(): array
    {
        return array_values(array_filter(self::todos(), fn ($e) => ! $e['vivo']));
    }

    /**
     * Vueltas que llevan corriendo más que su propio timeout. Su `timeout` sale del registro, no
     * de una constante: si alguien lanzó la vuelta con otro valor, manda el que se usó de verdad.
     */
    public static function pasadasDeTimeout(int $margenSeg = 60): array
    {
        return array_values(array_filter(self::vivos(), function ($e) use ($margenSeg) {
            $t = (int) ($e['timeout'] ?? 0);

            return $t > 0 && (int) ($e['edad_seg'] ?? 0) > $t + $margenSeg;
        }));
    }

    /**
     * LA PREGUNTA QUE AUTORIZA: ¿este PID es del circuito, según el registro propio?
     *
     * Devuelve false ante la mínima duda —registro ausente, `starttime` distinto, proceso ya
     * muerto—. Es el único lugar donde debería preguntarse "¿puedo tocar este proceso?", y su
     * respuesta por defecto es que no.
     */
    public static function esDelCircuito(int $pid): bool
    {
        foreach (self::vivos() as $e) {
            if ((int) $e['pid'] === $pid) {
                return true;
            }
        }

        return false;
    }

    /**
     * ¿Vive el PID y es el MISMO proceso que se registró?
     *
     * @return array{0:bool,1:string}
     */
    private static function verificar(int $pid, $starttimeRegistrado): array
    {
        if ($pid <= 1) {
            return [false, 'pid inválido'];
        }
        $stat = @file_get_contents("/proc/{$pid}/stat");
        if ($stat === false) {
            return [false, 'el proceso ya no existe'];
        }
        if ($starttimeRegistrado === null || $starttimeRegistrado === '') {
            // Sin starttime no se puede probar identidad; se trata como no verificable, que para
            // efectos de autoridad es igual que muerto.
            return [false, 'registro sin starttime: no se puede probar que sea el mismo proceso'];
        }

        $actual = self::starttimeDe($stat);
        if ($actual === null) {
            return [false, '/proc ilegible'];
        }
        if ((string) $actual !== (string) $starttimeRegistrado) {
            return [false, "el PID {$pid} se reusó (starttime {$actual} ≠ {$starttimeRegistrado})"];
        }

        return [true, 'vivo y verificado'];
    }

    /**
     * Campo 22 de /proc/<pid>/stat. Se parsea desde el ÚLTIMO ')' porque el campo 2 es el nombre
     * del ejecutable entre paréntesis y puede contener espacios (y paréntesis): partir por espacios
     * desde el inicio es el bug clásico de leer este archivo.
     */
    private static function starttimeDe(string $stat): ?string
    {
        $cierre = strrpos($stat, ')');
        if ($cierre === false) {
            return null;
        }
        $campos = preg_split('/\s+/', trim(substr($stat, $cierre + 1)));

        // Tras el ')' el primer campo es el 3 (state), así que el 22 es el índice 19.
        return $campos[19] ?? null;
    }
}
