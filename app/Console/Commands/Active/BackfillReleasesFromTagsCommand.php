<?php

namespace App\Console\Commands\Active;

use App\Models\Release;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Backfill idempotente de `releases` desde los tags de git (item roadmap #9990637).
 *
 * Causa raíz encontrada (auditoría del item, time-boxed — q4): NO es una fila borrada. La
 * tabla salta de id=68 (V1.15, 26-jun) a id=69 (04-sep) SIN hueco en el autoincrement — si esos
 * ~16 releases se hubieran insertado y luego perdido (TRUNCATE, migrate:fresh + restore), el
 * autoincrement habría quedado adelantado y el restore habría traído de vuelta sus ids/fechas
 * originales. Que el siguiente id disponible sea exactamente el consecutivo de junio indica que
 * los builds V1.16–V1.32 (tageados en git entre el 29-jun y el 10-ago, releases reales) JAMÁS
 * se insertaron en la tabla `releases` de ESTA base (dev) — se cortaron por una vía que no pasó
 * por `ReleaseController::store()` / `RemoteDeployCommand::saveRelease()`. Los dos incidentes de
 * `migrate:fresh` documentados en dev (22-ago y 25-ago, `docs/bitacora-sesiones.md:1936`) son
 * posteriores a todo ese rango y el PITR del 25-ago reconstruyó la base EXACTAMENTE como estaba
 * un instante antes del DROP — con el hueco ya presente —, así que no son la causa de este hueco
 * en particular, aunque comparten familia (escritura de `releases` que no ocurrió).
 *
 * Este comando NO reescribe historia inventada: por cada tag de git que no tenga ya una fila con
 * ese `version` exacto, crea una fila con SOLO `version` + `release_date` (fecha del commit del
 * tag) + `origin='backfill_git_tags'`. Todo lo demás (título, resumen, vínculo técnico) queda
 * NULL — no se inventa (decisión de Irving, item #9990637 q2). Upsert por `version`: reejecutar
 * el comando nunca duplica ni pisa una fila ya existente (creada por este comando o por el flujo
 * normal).
 */
class BackfillReleasesFromTagsCommand extends Command
{
    protected $signature = 'releases:backfill-from-tags
                            {--dry-run : Muestra qué filas se crearían sin escribir en la BD}';

    protected $description = 'Crea en `releases` las filas faltantes para los tags de git que aún no tienen fila (item #9990637)';

    public function handle(): int
    {
        $tags = $this->tagsLocales();
        if (empty($tags)) {
            $this->warn('No se encontraron tags "V*" en este repositorio local.');
            return self::SUCCESS;
        }

        $existentes = Release::pluck('id', 'version')->all();
        $porInsertar = [];

        foreach ($tags as $tag) {
            if (array_key_exists($tag, $existentes)) {
                continue;
            }

            $fecha = $this->fechaDelTag($tag);
            if (!$fecha) {
                $this->warn("  Omitido «{$tag}»: no se pudo leer la fecha del commit (git log -1 sin salida).");
                continue;
            }

            $porInsertar[] = ['version' => $tag, 'release_date' => $fecha];
        }

        if (empty($porInsertar)) {
            $this->info('Nada que backfillear: todos los tags de git ya tienen fila en `releases`.');
            return self::SUCCESS;
        }

        usort($porInsertar, fn ($a, $b) => $a['release_date'] <=> $b['release_date']);

        $this->info(count($porInsertar) . ' fila(s) faltante(s) detectada(s):');
        foreach ($porInsertar as $fila) {
            $this->line("  {$fila['version']}\t{$fila['release_date']}");
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: no se escribió nada.');
            return self::SUCCESS;
        }

        $creadoPor = User::systemBot()?->id ?? 1;

        DB::transaction(function () use ($porInsertar, $creadoPor) {
            foreach ($porInsertar as $fila) {
                Release::firstOrCreate(
                    ['version' => $fila['version']],
                    [
                        'release_date' => $fila['release_date'],
                        'origin'       => 'backfill_git_tags',
                        'created_by'   => $creadoPor,
                    ]
                );
            }
        });

        $this->info(count($porInsertar) . ' fila(s) creada(s) en `releases` (origin=backfill_git_tags).');

        return self::SUCCESS;
    }

    /** @return string[] tags "V*" locales, sin filtrar por patrón (el upsert por version ya es seguro) */
    private function tagsLocales(): array
    {
        $p = Process::fromShellCommandline("git tag -l 'V*'", base_path(), $this->env(), null, 30);
        $p->run();

        return array_values(array_filter(array_map('trim', explode("\n", $p->getOutput()))));
    }

    private function fechaDelTag(string $tag): ?string
    {
        $p = Process::fromShellCommandline('git log -1 --format=%aI ' . escapeshellarg($tag), base_path(), $this->env(), null, 30);
        $p->run();

        $salida = trim($p->getOutput());
        if (!$p->isSuccessful() || $salida === '') {
            return null;
        }

        return Carbon::parse($salida)->toDateString();
    }

    private function env(): array
    {
        return [
            'PATH'   => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'   => '/root',
            'LC_ALL' => 'C',
            'LANG'   => 'C',
        ];
    }
}
