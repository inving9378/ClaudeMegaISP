<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\Process;

/**
 * #747 (sub-item de #279, pregunta q2, opción elegida) — Auditoría retroactiva de merges que
 * pudieron entrar sin aprobación FRESCA de Irving. 100% READ-ONLY: no toca `estado_aprobacion`,
 * no toca git (ni checkout ni merge ni commit), no revierte nada. La opción de revertir
 * automáticamente fue explícitamente DESCARTADA por Irving al aprobar #279 — este comando solo
 * reporta, para que él decida caso por caso.
 *
 * Candidato sospechoso = el ÚLTIMO commit que trajo la rama mergeada (padre 2 del merge commit,
 * porque `MergeRunner::merge()` SIEMPRE usa `git merge --no-ff`, ver Services/MergeRunner.php:135)
 * es POSTERIOR a la fecha de aprobación del item + margen. Eso significa que, entre que Irving
 * aprobó y que el merge realmente ocurrió, la rama siguió recibiendo commits que Irving nunca vio.
 *
 * fecha_aprobacion = `revisado_at`; si un item viejo no lo tiene poblado, se busca en su `log[]`
 * la última entrada con `decision === 'aprobar'` (o `estado` que contenga "aprobado") como
 * fallback. Sin ninguna de las dos → "no verificable" (no se puede auditar a ciegas).
 *
 * Alcance temporal (#279 q1, opción "ventana reciente" — 7-14 días): por default el REPORTE
 * (consola + archivo) solo muestra candidatos cuya fecha_aprobacion cae dentro de `--dias` (14).
 * La DETECCIÓN corre sobre TODOS los items con `merge_commit` (así lo pide el spec del item, punto
 * 2) — la ventana solo acota qué tan atrás se reporta, para no generar ruido de merges viejos ya
 * irrelevantes. `--todo` desactiva el filtro y muestra el histórico completo (opción 1 de q1).
 *
 *   php artisan circuito:auditar-merges-post-aprobacion                    # ventana 14 días
 *   php artisan circuito:auditar-merges-post-aprobacion --dias=7           # ventana 7 días
 *   php artisan circuito:auditar-merges-post-aprobacion --todo             # histórico completo
 *   php artisan circuito:auditar-merges-post-aprobacion --margen-horas=2   # tolera 2h de margen
 */
class AuditarMergesPostAprobacionCommand extends Command
{
    protected $signature = 'circuito:auditar-merges-post-aprobacion
        {--margen-horas=0 : Horas de tolerancia entre aprobación y último commit mergeado antes de marcar sospechoso}
        {--dias=14 : Ventana de días (sobre fecha_aprobacion) que se reporta; ignorado con --todo}
        {--todo : Reporta el histórico completo, sin ventana de días}';

    protected $description = '#747 — audita, solo lectura, qué merges de Jarvis pudieron entrar sin aprobación fresca de Irving.';

    public function handle(): int
    {
        $margenHoras = max(0, (float) $this->option('margen-horas'));
        $dias = max(1, (int) $this->option('dias'));
        $todo = (bool) $this->option('todo');

        $items = RoadmapItem::query()
            ->whereNotNull('merge_commit')
            ->orderBy('id')
            ->get(['id', 'title', 'merge_commit', 'revisado_at', 'log']);

        $this->line('');
        $this->line('<options=bold>AUDITORÍA RETROACTIVA — merges sin aprobación fresca de Irving (#747)</>');
        $this->line("  items con merge_commit: {$items->count()}  ·  margen: {$margenHoras}h  ·  ventana reporte: " . ($todo ? 'histórico completo' : "últimos {$dias} días"));

        $resultados = [];
        foreach ($items as $item) {
            $resultados[] = $this->evaluar($item, $margenHoras);
        }

        $desde = $todo ? null : now()->subDays($dias);
        $enVentana = $todo
            ? $resultados
            : array_values(array_filter($resultados, function ($r) use ($desde) {
                return $r['fecha_aprobacion'] !== null && $r['fecha_aprobacion']->gte($desde);
            }));

        $sospechosos = array_values(array_filter($enVentana, fn ($r) => $r['veredicto'] === 'sospechoso'));
        $noVerificables = array_values(array_filter($enVentana, fn ($r) => $r['veredicto'] === 'no_verificable'));
        $ok = array_values(array_filter($enVentana, fn ($r) => $r['veredicto'] === 'ok'));

        $this->line('');
        $this->line(sprintf(
            '  en ventana: %d  ·  sospechosos: %d  ·  no verificables: %d  ·  ok: %d',
            count($enVentana),
            count($sospechosos),
            count($noVerificables),
            count($ok)
        ));

        if ($sospechosos !== []) {
            $this->line('');
            $this->line('<options=bold;fg=yellow>CANDIDATOS SOSPECHOSOS</>');
            $this->table(
                ['Item', 'Título', 'Aprobado', 'Último commit', 'Diff (h)', 'Rama'],
                array_map(fn ($r) => [
                    $r['id'],
                    mb_strimwidth((string) $r['title'], 0, 60, '…'),
                    $r['fecha_aprobacion']?->toDateTimeString() ?? '—',
                    $r['fecha_ultimo_commit']?->toDateTimeString() ?? '—',
                    $r['diff_horas'] !== null ? round($r['diff_horas'], 1) : '—',
                    $r['merge_commit'],
                ], $sospechosos)
            );
        }

        if ($noVerificables !== []) {
            $this->line('');
            $this->line('<options=bold>NO VERIFICABLES</> (no se pudo confirmar automáticamente — revisar a mano)');
            $this->table(
                ['Item', 'Título', 'Motivo'],
                array_map(fn ($r) => [$r['id'], mb_strimwidth((string) $r['title'], 0, 60, '…'), $r['motivo']], $noVerificables)
            );
        }

        $ruta = 'docs/auditorias/auditoria-merges-post-aprobacion.md';
        @mkdir(base_path('docs/auditorias'), 0755, true);
        file_put_contents(base_path($ruta), $this->reporte($margenHoras, $todo, $dias, $enVentana, $sospechosos, $noVerificables, $items->count()));
        $this->line('');
        $this->info("Reporte escrito en {$ruta} (se pisa en cada corrida con la medición más reciente).");

        return self::SUCCESS;
    }

    /**
     * Evalúa un item ya mergeado. Nunca escribe nada — solo lee el modelo (ya cargado en memoria)
     * y corre `git log` (read-only) contra el repo compartido del worktree.
     */
    private function evaluar(RoadmapItem $item, float $margenHoras): array
    {
        $base = [
            'id' => $item->id,
            'title' => $item->title,
            'merge_commit' => $item->merge_commit,
            'fecha_aprobacion' => null,
            'fecha_ultimo_commit' => null,
            'diff_horas' => null,
            'veredicto' => 'no_verificable',
            'motivo' => '',
        ];

        $fechaAprobacion = $item->revisado_at instanceof Carbon ? $item->revisado_at : $this->fallbackFechaAprobacion($item);
        $base['fecha_aprobacion'] = $fechaAprobacion;

        if ($fechaAprobacion === null) {
            $base['motivo'] = 'Sin revisado_at y sin entrada de aprobación en log[] — no hay ancla temporal.';

            return $base;
        }

        $sha = (string) $item->merge_commit;
        if (! preg_match('/^[0-9a-f]{6,40}$/i', $sha)) {
            $base['motivo'] = 'merge_commit no tiene forma de SHA válido.';

            return $base;
        }

        // Padre 2 del merge commit = tip de la rama integrada (merge SIEMPRE --no-ff, MergeRunner.php:135).
        $p = $this->git(['log', '-1', '--format=%aI', $sha . '^2']);
        if (! $p->isSuccessful()) {
            $base['motivo'] = 'No se pudo leer el padre 2 del merge commit (ref inválida, podada, o no es un merge real de 2 padres).';

            return $base;
        }

        $raw = trim($p->getOutput());
        try {
            $fechaUltimoCommit = Carbon::parse($raw);
        } catch (\Throwable $e) {
            $base['motivo'] = "Fecha de git ilegible: '{$raw}'.";

            return $base;
        }

        $base['fecha_ultimo_commit'] = $fechaUltimoCommit;
        $diffHoras = $fechaAprobacion->diffInRealMinutes($fechaUltimoCommit, false) / 60;
        $base['diff_horas'] = $diffHoras;
        $base['veredicto'] = $diffHoras > $margenHoras ? 'sospechoso' : 'ok';
        $base['motivo'] = $base['veredicto'] === 'sospechoso'
            ? 'El último commit de la rama es posterior a la aprobación (+ margen).'
            : 'Último commit de la rama no es posterior a la aprobación (dentro de margen).';

        return $base;
    }

    /** Última entrada de `log[]` que registre una decisión de aprobación, como fallback de fecha_aprobacion. */
    private function fallbackFechaAprobacion(RoadmapItem $item): ?Carbon
    {
        $log = is_array($item->log) ? $item->log : [];
        $mejor = null;
        foreach ($log as $entrada) {
            $esAprobacion = (isset($entrada['decision']) && $entrada['decision'] === 'aprobar')
                || (isset($entrada['estado']) && str_contains(strtolower((string) $entrada['estado']), 'aprobado'));
            if (! $esAprobacion || empty($entrada['ts'])) {
                continue;
            }
            try {
                $ts = Carbon::parse($entrada['ts']);
            } catch (\Throwable $e) {
                continue;
            }
            if ($mejor === null || $ts->gt($mejor)) {
                $mejor = $ts;
            }
        }

        return $mejor;
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->setTimeout(30);
        $p->run();

        return $p;
    }

    private function reporte(float $margenHoras, bool $todo, int $dias, array $enVentana, array $sospechosos, array $noVerificables, int $totalEscaneados): string
    {
        $ahora = now()->toDateTimeString();
        $ventanaTxt = $todo ? 'histórico completo (--todo)' : "últimos {$dias} días (fecha_aprobacion)";
        $enVentanaCount = count($enVentana);

        $filasSospechosos = '';
        foreach ($sospechosos as $r) {
            $titulo = str_replace('|', '\\|', (string) $r['title']);
            $filasSospechosos .= sprintf(
                "| #%d | %s | %s | %s | %s | `%s` |\n",
                $r['id'],
                $titulo,
                $r['fecha_aprobacion']?->toDateTimeString() ?? '—',
                $r['fecha_ultimo_commit']?->toDateTimeString() ?? '—',
                $r['diff_horas'] !== null ? round($r['diff_horas'], 1) : '—',
                $r['merge_commit']
            );
        }
        if ($filasSospechosos === '') {
            $filasSospechosos = "| _(ninguno en la ventana)_ | — | — | — | — | — |\n";
        }

        $filasNoVerificables = '';
        foreach ($noVerificables as $r) {
            $titulo = str_replace('|', '\\|', (string) $r['title']);
            $filasNoVerificables .= sprintf("| #%d | %s | %s |\n", $r['id'], $titulo, $r['motivo']);
        }
        if ($filasNoVerificables === '') {
            $filasNoVerificables = "| _(ninguno en la ventana)_ | — | — |\n";
        }

        return <<<MD
# Auditoría retroactiva — merges de Jarvis sin aprobación fresca de Irving (#747)

> Generado automáticamente por `php artisan circuito:auditar-merges-post-aprobacion` el {$ahora}.
> **READ-ONLY**: no modifica items ni git, solo reporta. Re-ejecutar este comando pisa este
> archivo con la medición más reciente. La reversión automática de merges sospechosos fue
> explícitamente DESCARTADA por Irving al aprobar #279 — cada candidato aquí listado espera su
> revisión manual, uno por uno.

Parámetros: margen = {$margenHoras}h · ventana del reporte = {$ventanaTxt} · items con
merge_commit escaneados en total = {$totalEscaneados} · en ventana = {$enVentanaCount}.

## Candidatos sospechosos

Un candidato es sospechoso cuando el ÚLTIMO commit que trajo la rama mergeada (padre 2 del merge
commit) es posterior a la fecha de aprobación del item + margen — es decir, la rama siguió
recibiendo trabajo después de que Irving la aprobó, y ese trabajo nuevo nunca pasó por su revisión.

| Item | Título | Aprobado | Último commit mergeado | Diff (h) | Merge commit |
|---|---|---|---|---:|---|
{$filasSospechosos}

## No verificables

No se pudo confirmar automáticamente (sin fecha de aprobación, SHA inválido, o el merge commit no
tiene 2 padres reales) — requieren revisión manual directa.

| Item | Título | Motivo |
|---|---|---|
{$filasNoVerificables}
MD;
    }
}
