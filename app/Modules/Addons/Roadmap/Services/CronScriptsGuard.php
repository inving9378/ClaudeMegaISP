<?php

namespace App\Modules\Addons\Roadmap\Services;

/**
 * #233 — candado: ningún script de `deploy/circuito/` referenciado por el crontab real puede
 * quedar sin permiso de ejecución (o inexistente) sin que algo lo delate. Nace del incidente de
 * `vigilia-wrap.sh` (modo 100644 el 25-ago): cron falló con "Permission denied" cada minuto
 * durante ~25 horas y el propio `>/dev/null 2>&1` de la línea se tragó el error — nadie lo supo
 * hasta que alguien corrió el comando a mano.
 *
 * PURO: sin Laravel, sin BD, sólo filesystem — recibe el texto del crontab como parámetro (no lo
 * lee él mismo) para poder probarse con un crontab de mentira sin bootear el framework. Mismo
 * patrón que `tests/Unit/Modules/Addons/Roadmap/PoolGuardCoherenceTest.php`.
 *
 * Alcance (a propósito): SOLO rutas bajo `deploy/circuito/`. El resto del crontab del sistema
 * (schedule:run, backup_db, etc.) no es responsabilidad de este candado.
 */
class CronScriptsGuard
{
    /**
     * @return array<int,array{linea:string,archivo:string,motivo:string}> vacío = todo OK
     */
    public static function problemas(string $crontabTexto): array
    {
        $problemas = [];

        foreach (preg_split('/\R/', $crontabTexto) as $linea) {
            $linea = trim((string) $linea);
            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }

            if (! preg_match('#(\S*/deploy/circuito/\S+\.sh)#', $linea, $m)) {
                continue;
            }

            $archivo = $m[1];

            if (! file_exists($archivo)) {
                $problemas[] = ['linea' => $linea, 'archivo' => $archivo, 'motivo' => 'el archivo no existe'];
            } elseif (! is_executable($archivo)) {
                $problemas[] = ['linea' => $linea, 'archivo' => $archivo, 'motivo' => 'sin permiso de ejecución (+x)'];
            }
        }

        return $problemas;
    }
}
