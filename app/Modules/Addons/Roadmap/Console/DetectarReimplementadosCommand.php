<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * #9990346 — detector READ-ONLY de items cuyo trabajo fue re-implementado desde cero por otro item,
 * porque la rama del original nunca entró a `main`. Solo informa: no cierra items, no mergea, no
 * crea items automáticamente y no borra ramas. La confirmación siempre es humana (comando
 * `git diff main...<rama>` incluido en cada fila para verificar a mano).
 *
 * Universo = el mismo que ya usa #9990061 (auditoría de completados sin mergear):
 *   estado_aprobacion = 'completado' AND branch IS NOT NULL AND merge_commit IS NULL AND archivado_at IS NULL
 *
 * Señales (de más a menos fuertes; cualquiera de las dos primeras basta para "duplicado probable"):
 *   1. Parentesco declarado — otro item con `origen_item_id` = este id, completado y ya mergeado.
 *   2. Parentesco en el título — otro item completado+mergeado cuyo título menciona "#<id>".
 *   3. Parentesco sólo en git — un commit en `main` menciona "#<id>" en su mensaje (no puede ser un
 *      commit propio de este item: su rama nunca mergeó, así que sus commits no viven en main).
 *   4. Solapamiento de archivos — la rama toca archivos que `main` también tocó después de que la
 *      rama divergió. Señal débil por diseño (moda de ejecución concurrente = mucho archivo
 *      compartido sin relación real); nunca sube a "duplicado probable" por sí sola.
 */
class DetectarReimplementadosCommand extends Command
{
    protected $signature = 'circuito:detectar-reimplementados';

    protected $description = '#9990346 — detecta, solo lectura, items completados sin mergear cuyo trabajo parece re-implementado por otro item.';

    /** Archivos que casi cualquier commit toca por convención (bitácora) — ruido, no señal. */
    private const ARCHIVOS_RUIDO = ['docs/bitacora-sesiones.md', 'CLAUDE.md'];

    public function handle(RoadmapCircuitoService $circuito): int
    {
        $universo = RoadmapItem::query()
            ->where('estado_aprobacion', 'completado')
            ->whereNotNull('branch')
            ->whereNull('merge_commit')
            ->whereNull('archivado_at')
            ->orderBy('id')
            ->get(['id', 'title', 'branch']);

        $this->line('');
        $this->line('<options=bold>DETECTOR DE RE-IMPLEMENTACIÓN SILENCIOSA (#9990346)</>');
        $this->line("  universo (completado, con rama, sin merge_commit, no archivado): {$universo->count()}");

        $resultados = [];
        foreach ($universo as $item) {
            $resultados[] = $this->evaluar($item, $circuito);
        }

        $duplicados = array_values(array_filter($resultados, fn ($r) => $r['veredicto'] === 'duplicado_probable'));
        $sospechas = array_values(array_filter($resultados, fn ($r) => $r['veredicto'] === 'sospecha'));
        $sinSenal = array_values(array_filter($resultados, fn ($r) => $r['veredicto'] === 'sin_senal'));

        $this->line('');
        $this->line(sprintf(
            '  duplicado probable: %d  ·  sospecha: %d  ·  sin señal: %d',
            count($duplicados),
            count($sospechas),
            count($sinSenal)
        ));

        if ($duplicados !== []) {
            $this->line('');
            $this->line('<options=bold;fg=red>DUPLICADO PROBABLE</>');
            $this->table(
                ['Item', 'Título', 'Señal', 'Reemplazado por', 'Verificar a mano'],
                array_map(fn ($r) => [
                    $r['id'],
                    mb_strimwidth((string) $r['title'], 0, 50, '…'),
                    implode(', ', $r['senales']),
                    $r['candidato'],
                    "git diff main...{$r['branch']}",
                ], $duplicados)
            );
        }

        if ($sospechas !== []) {
            $this->line('');
            $this->line('<options=bold;fg=yellow>SOSPECHA</> (señal débil — revisar a mano antes de actuar)');
            $this->table(
                ['Item', 'Título', 'Señal', 'Candidato/detalle', 'Verificar a mano'],
                array_map(fn ($r) => [
                    $r['id'],
                    mb_strimwidth((string) $r['title'], 0, 50, '…'),
                    implode(', ', $r['senales']),
                    $r['candidato'],
                    "git diff main...{$r['branch']}",
                ], $sospechas)
            );
        }

        $ruta = 'docs/auditorias/detectar-reimplementados.md';
        @mkdir(base_path('docs/auditorias'), 0755, true);
        file_put_contents(base_path($ruta), $this->reporte($resultados, $duplicados, $sospechas, $sinSenal));
        $this->line('');
        $this->info("Reporte escrito en {$ruta} (se pisa en cada corrida con la medición más reciente).");
        $this->line('Recordatorio: este comando NO cierra, mergea, ni borra nada — solo informa. La confirmación es humana.');

        return self::SUCCESS;
    }

    private function evaluar(RoadmapItem $item, RoadmapCircuitoService $circuito): array
    {
        $senales = [];
        $candidatos = [];

        // Señal 1 — parentesco declarado (origen_item_id).
        $hijo = RoadmapItem::query()
            ->where('origen_item_id', $item->id)
            ->where('estado_aprobacion', 'completado')
            ->whereNotNull('merge_commit')
            ->orderBy('id')
            ->first(['id', 'title', 'merge_commit']);
        if ($hijo) {
            $senales[] = 'origen_item_id';
            $candidatos[] = "#{$hijo->id} ({$hijo->merge_commit})";
        }

        // Señal 2 — parentesco en el título de otro item ya mergeado.
        $porTitulo = RoadmapItem::query()
            ->where('id', '!=', $item->id)
            ->where('title', 'like', '%#' . $item->id . '%')
            ->where('estado_aprobacion', 'completado')
            ->whereNotNull('merge_commit')
            ->get(['id', 'title', 'merge_commit'])
            ->filter(fn ($c) => preg_match('/#' . preg_quote((string) $item->id, '/') . '(?!\d)/', (string) $c->title) === 1);
        foreach ($porTitulo as $c) {
            if ($hijo && (int) $c->id === (int) $hijo->id) {
                continue; // ya contado por señal 1
            }
            $senales[] = 'titulo';
            $candidatos[] = "#{$c->id} ({$c->merge_commit})";
        }

        // Señal 3 — parentesco sólo en git: un commit en main menciona "#<id>" (no puede ser propio,
        // esta rama nunca mergeó). Filtra el caso trivial de que la propia fusión sí haya ocurrido
        // sin quedar registrada en merge_commit (drift de BD, no re-implementación — fuera de alcance).
        $commit = $this->buscarCommitEnMain($item->id);
        if ($commit !== null) {
            $senales[] = 'git';
            $candidatos[] = "commit {$commit['hash']}: " . mb_strimwidth($commit['subject'], 0, 60, '…');
        }

        // Señal 4 — solapamiento de archivos (débil).
        $archivosComunes = $this->solapamientoArchivos($item, $circuito);
        if ($archivosComunes !== []) {
            $senales[] = 'archivos';
            $candidatos[] = 'archivos en común: ' . implode(', ', array_slice($archivosComunes, 0, 5))
                . (count($archivosComunes) > 5 ? '…' : '');
        }

        $senales = array_values(array_unique($senales));
        $fuerte = array_intersect($senales, ['origen_item_id', 'titulo']) !== [];
        $veredicto = $fuerte ? 'duplicado_probable' : ($senales !== [] ? 'sospecha' : 'sin_senal');

        return [
            'id' => $item->id,
            'title' => $item->title,
            'branch' => $item->branch,
            'senales' => $senales,
            'candidato' => $candidatos !== [] ? implode(' | ', $candidatos) : '—',
            'veredicto' => $veredicto,
        ];
    }

    /** @return array{hash:string,subject:string}|null */
    private function buscarCommitEnMain(int $itemId): ?array
    {
        $p = $this->git(['log', 'main', '--format=%H%x1f%s', '-F', '--grep=#' . $itemId]);
        if (! $p->isSuccessful()) {
            return null;
        }

        foreach (preg_split('/\R/', trim($p->getOutput())) as $linea) {
            if ($linea === '') {
                continue;
            }
            [$hash, $subject] = array_pad(explode("\x1f", $linea, 2), 2, '');
            if (preg_match('/#' . $itemId . '(?!\d)/', $subject) !== 1) {
                continue;
            }
            // El merge commit del propio item (si aterrizó sin quedar registrado en merge_commit)
            // no cuenta como re-implementación — es el drift que cubre #9990345, no este item.
            if (preg_match('/^Integra circuito #' . $itemId . '(?!\d)/', $subject) === 1) {
                continue;
            }

            return ['hash' => substr($hash, 0, 10), 'subject' => $subject];
        }

        return null;
    }

    /** @return string[] */
    private function solapamientoArchivos(RoadmapItem $item, RoadmapCircuitoService $circuito): array
    {
        $footprint = $circuito->footprintDeRama($item->branch);
        if ($footprint === []) {
            return [];
        }

        $base = $this->git(['merge-base', 'main', $item->branch]);
        if (! $base->isSuccessful()) {
            return [];
        }
        $sha = trim($base->getOutput());

        $tocadosEnMain = $this->git(['log', '--format=', '--name-only', $sha . '..main']);
        if (! $tocadosEnMain->isSuccessful()) {
            return [];
        }
        $tocados = array_flip(array_values(array_filter(preg_split('/\R/', trim($tocadosEnMain->getOutput())))));

        $comunes = array_values(array_filter($footprint, function ($archivo) use ($tocados) {
            return isset($tocados[$archivo]) && ! in_array($archivo, self::ARCHIVOS_RUIDO, true);
        }));

        return $comunes;
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->setTimeout(30);
        $p->run();

        return $p;
    }

    private function reporte(array $resultados, array $duplicados, array $sospechas, array $sinSenal): string
    {
        $ahora = now()->toDateTimeString();

        $filas = function (array $items): string {
            if ($items === []) {
                return "| _(ninguno)_ | — | — | — |\n";
            }
            $out = '';
            foreach ($items as $r) {
                $titulo = str_replace('|', '\\|', (string) $r['title']);
                $out .= sprintf(
                    "| #%d | %s | %s | %s |\n",
                    $r['id'],
                    mb_strimwidth($titulo, 0, 70, '…'),
                    implode(', ', $r['senales']) ?: '—',
                    str_replace('|', '\\|', $r['candidato'])
                );
            }

            return $out;
        };

        return <<<MD
# Detección de re-implementación silenciosa (#9990346)

> Generado automáticamente por `php artisan circuito:detectar-reimplementados` el {$ahora}.
> **READ-ONLY**: no cierra items, no mergea, no crea items, no borra ramas. Cada fila trae su
> `git diff main...<rama>` para verificar a mano — la confirmación siempre es humana.

Universo escaneado (completado, con rama propia, sin `merge_commit`, no archivado): {$this->contar($resultados)}.

## Duplicado probable

Señal fuerte: otro item ya completado y mergeado declara parentesco (`origen_item_id`) o lo
menciona en su título.

| Item | Título | Señal | Reemplazado por |
|---|---|---|---|
{$filas($duplicados)}

## Sospecha

Señal débil (solo mención en un commit de main, o solapamiento de archivos) — no es suficiente
para concluir duplicado sin mirar el diff.

| Item | Título | Señal | Detalle |
|---|---|---|---|
{$filas($sospechas)}

## Sin señal

{$this->contar($sinSenal)} item(s) del universo sin ninguna de las 4 señales — no significa que
estén bien, sólo que este detector no encontró indicio de re-implementación.
MD;
    }

    private function contar(array $r): int
    {
        return count($r);
    }
}
