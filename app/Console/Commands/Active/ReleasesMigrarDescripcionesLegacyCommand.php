<?php

namespace App\Console\Commands\Active;

use App\Models\ReleaseDescription;
use App\Services\Deploy\DeploymentService;
use Illuminate\Console\Command;

/**
 * #9991208 — Migra las descripciones LEGACY del generador por IA que se guardaron con
 * nl2br(e($markdown)) (`### …<br />`, `&quot;`…) a markdown crudo, aplicando LA MISMA
 * DeploymentService::normalizeReleaseNotes() que ya usa el paso github_release (html_entity_decode
 * + strip_tags, conservando los saltos de línea) — no un normalizador nuevo. Idempotente: solo toca
 * filas `formato = markdown` que aún contengan `<br`; las del editor (`formato = html`) no se tocan.
 */
class ReleasesMigrarDescripcionesLegacyCommand extends Command
{
    protected $signature = 'releases:migrar-descripciones-legacy {--dry-run : solo muestra qué se cambiaría}';

    protected $description = '#9991208 — convierte las descripciones markdown guardadas con nl2br(e()) a markdown crudo (idempotente, --dry-run).';

    public function handle(DeploymentService $deploy): int
    {
        $filas = ReleaseDescription::query()
            ->where('formato', ReleaseDescription::FORMATO_MARKDOWN)
            ->where(fn ($q) => $q->where('description', 'like', '%<br%')->orWhere('description', 'like', '%&quot;%')->orWhere('description', 'like', '%&#039;%'))
            ->orderBy('id')
            ->get();
        if ($filas->isEmpty()) {
            $this->info('Nada que migrar: ninguna descripción markdown conserva <br /> o entidades.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $tabla = [];
        $n = 0;
        foreach ($filas as $d) {
            $limpio = $deploy->normalizeReleaseNotes((string) $d->description);
            $tabla[] = [$d->id, $d->release_id, strlen($d->description), strlen($limpio), mb_strimwidth(str_replace("\n", '⏎', $limpio), 0, 70, '…')];
            if (! $dry && $limpio !== '') {
                $d->description = $limpio;
                $d->save();
                $n++;
            }
        }
        $this->table(['id', 'release_id', 'bytes antes', 'bytes después', 'inicio del markdown limpio'], $tabla);
        $this->info($dry ? 'Dry-run: no se escribió nada.' : "{$n} descripción(es) migrada(s).");

        return self::SUCCESS;
    }
}
