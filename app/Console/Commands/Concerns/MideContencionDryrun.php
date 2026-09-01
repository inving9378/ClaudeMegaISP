<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Item #831 — instrumentación DIAGNÓSTICA (opción 1 elegida por Irving: medir antes de
 * resolver, ver preguntas del item). Envuelve el drop/recreate/migrate de la BD compartida
 * `{database}_dryrun` con un GET_LOCK NO bloqueante (timeout 0) para registrar si dos
 * invocaciones — de cualquier combinación entre `schema:rebuild-dryrun` y
 * `deploy:dry-run-migrations` — se solapan en el tiempo.
 *
 * A propósito NO serializa ni bloquea nada: con timeout 0, si el lock ya está tomado
 * GET_LOCK regresa 0 al instante y el comando SIGUE exactamente como hoy. Solo queda la
 * colisión anotada en storage/logs/dryrun-contention-*.log, para el reporte que la opción 1
 * pide generar después de correr el circuito con varios agentes en paralelo (ver
 * `dryrun:reporte-contencion`).
 */
trait MideContencionDryrun
{
    private bool $dryrunLockAcquired = false;

    private float $dryrunLockStart = 0.0;

    protected function dryrunLockName(string $tempDb): string
    {
        return 'megaisp_dryrun_contencion:' . $tempDb;
    }

    protected function iniciarMedicionContencion(string $comando, string $tempDb): void
    {
        $this->dryrunLockStart = microtime(true);

        try {
            $row = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$this->dryrunLockName($tempDb)]);
            $this->dryrunLockAcquired = ((int) ($row->acquired ?? 0)) === 1;
        } catch (Throwable $e) {
            // El lock es solo de medición: si falla, no debe tumbar el comando real.
            $this->dryrunLockAcquired = false;
            Log::channel('dryrun_contention')->warning('No se pudo evaluar GET_LOCK (se continúa sin medir)', [
                'comando' => $comando,
                'temp_db' => $tempDb,
                'sid'     => $this->identidadAgenteDryrun(),
                'error'   => $e->getMessage(),
            ]);

            return;
        }

        Log::channel('dryrun_contention')->info(
            $this->dryrunLockAcquired
                ? 'Sin colisión: lock adquirido'
                : 'COLISIÓN DETECTADA: otro proceso ya sostiene el lock de megaisp_dryrun',
            [
                'comando'  => $comando,
                'temp_db'  => $tempDb,
                'sid'      => $this->identidadAgenteDryrun(),
                'colision' => ! $this->dryrunLockAcquired,
                'pid'      => getmypid(),
            ]
        );
    }

    protected function cerrarMedicionContencion(string $comando, string $tempDb, bool $ok, ?string $error = null): void
    {
        $elapsed = round(microtime(true) - $this->dryrunLockStart, 2);

        if ($this->dryrunLockAcquired) {
            try {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$this->dryrunLockName($tempDb)]);
            } catch (Throwable $e) {
                // El lock de MySQL se libera solo al cerrarse la sesión; nada más que hacer aquí.
            }
        }

        Log::channel('dryrun_contention')->info('Fin de operación sobre megaisp_dryrun', [
            'comando'            => $comando,
            'temp_db'            => $tempDb,
            'sid'                => $this->identidadAgenteDryrun(),
            'colision_al_inicio' => ! $this->dryrunLockAcquired,
            'resultado'          => $ok ? 'OK' : 'FALLÓ',
            'elapsed_seg'        => $elapsed,
            'error'              => $error,
        ]);
    }

    protected function identidadAgenteDryrun(): string
    {
        return getenv('CIRCUITO_SID') ?: (basename(getcwd()) ?: 'desconocido');
    }
}
