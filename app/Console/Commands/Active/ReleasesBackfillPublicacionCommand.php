<?php

namespace App\Console\Commands\Active;

use App\Models\Release;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * #9991209 — Backfill IDEMPOTENTE del estado de publicación en `releases`, reconciliando POR TAG
 * contra la API de GitHub (misma lectura que `releases:reconciliar`). Solo escribe en filas donde
 * los tres campos (estado_publicacion, published_at, github_release_id) están en NULL: las que ya
 * tienen estado (`publicada`, `historica_no_publicada`, `no_publicada`) no se tocan. Una fila sin
 * Release en GitHub se reporta y se deja como está (no se inventa `no_publicada` desde aquí: eso
 * lo decide el pipeline al intentar publicar).
 */
class ReleasesBackfillPublicacionCommand extends Command
{
    protected $signature = 'releases:backfill-publicacion
        {--dry-run : solo muestra qué se escribiría}
        {--solo=* : limitar a estas versiones (default: todas las filas con los 3 campos en NULL)}';

    protected $description = '#9991209 — rellena github_release_id/published_at/estado_publicacion desde la API de GitHub, solo en filas con los 3 campos en NULL.';

    public function handle(ReconcileReleasesCommand $reconciliar): int
    {
        [$github, $ok, $error] = $reconciliar->githubReleasesPorTag();
        if (! $ok) {
            $this->error("No se pudo leer GitHub: {$error}");

            return self::FAILURE;
        }

        $q = Release::query()
            ->whereNull('estado_publicacion')
            ->whereNull('published_at')
            ->whereNull('github_release_id')
            ->orderBy('id');
        if ($this->option('solo')) {
            $q->whereIn('version', (array) $this->option('solo'));
        }
        $filas = $q->get();
        if ($filas->isEmpty()) {
            $this->info('Nada que rellenar: no hay filas con los tres campos en NULL.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $tabla = [];
        $escritas = 0;
        foreach ($filas as $r) {
            $gh = $github[$r->version] ?? null;
            if (! $gh || empty($gh['id'])) {
                $tabla[] = [$r->id, $r->version, '—', '—', 'sin Release en GitHub: se deja como está'];
                continue;
            }
            $publishedAt = ! empty($gh['published_at'])
                ? Carbon::parse($gh['published_at'])->setTimezone(config('app.timezone'))
                : null;
            $tabla[] = [$r->id, $r->version, (string) $gh['id'], $publishedAt?->format('Y-m-d H:i:s') ?? '—', $dry ? 'se escribiría publicada' : 'publicada'];
            if (! $dry) {
                $r->forceFill([
                    'github_release_id'         => (int) $gh['id'],
                    'published_at'              => $publishedAt ?? now(),
                    'estado_publicacion'        => 'publicada',
                    'estado_publicacion_motivo' => null,
                ])->save();
                $escritas++;
            }
        }
        $this->table(['id', 'version', 'github_release_id', 'published_at', 'acción'], $tabla);
        $this->info($dry ? 'Dry-run: no se escribió nada.' : "{$escritas} fila(s) actualizada(s).");

        return self::SUCCESS;
    }
}
