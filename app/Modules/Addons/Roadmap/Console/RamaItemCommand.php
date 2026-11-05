<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Crea la rama dedicada de un item del Circuito (circuito/item-<id>-<slug>) desde main
 * y la registra en el item (campo branch). El ejecutor trabaja el cambio ahí, aislado
 * de la rama desplegada. NUNCA hace push ni toca prod.
 */
class RamaItemCommand extends Command
{
    protected $signature = 'circuito:rama {id : ID del item} {--sid= : SID del worker que corre la vuelta (distingue su propio claim de otra terminal)}';

    protected $description = 'Crea/checkout la rama del item (circuito/item-<id>-<slug>) desde main y la registra en el item.';

    public function handle(): int
    {
        $item = RoadmapItem::find($this->argument('id'));
        if (! $item) {
            $this->error('Item no encontrado.');
            return self::FAILURE;
        }

        // #341 reconciliado (#432): la rama se crea DESPUÉS de que el scheduler marcó el item
        // `en_progreso` y le selló su `worker_sid` (orden claim→rama). Por eso `en_progreso` por sí
        // solo NO es colisión — es el flujo normal del propio worker. Solo colisiona un humano o
        // OTRA terminal. El SID llega por --sid o por el env CIRCUITO_SID que exporta vuelta.sh.
        $sid = $this->option('sid') ?: (getenv('CIRCUITO_SID') ?: null);
        if ($motivo = $this->motivoColision($item, $sid)) {
            $this->error("El item #{$item->id} no se puede ramificar: {$motivo} (candado #341).");
            return self::FAILURE;
        }

        $slug          = Str::slug(Str::limit($item->title, 40, ''));
        $branchDeseada = "circuito/item-{$item->id}-{$slug}";

        // #9990843 — la rama del item se busca por ID (patrón circuito/item-<id>-*), NO por el
        // slug recién calculado del título actual. El título puede diferir entre vueltas (renombre,
        // o simplemente un corte distinto de Str::limit(40) según el texto vigente ese día) y antes
        // eso hacía que, si el nombre exacto no coincidía, se creara una rama NUEVA desde el tip de
        // main — abandonando en silencio la rama con el trabajo real. Pasó de verdad en #9990718:
        // la rama con 22 commits reales (provisionador VoIP probado) quedó huérfana y el
        // merge-runner terminó integrando una rama de 1 commit (doc-only) que ganó el registro de
        // `item->branch` en una visita posterior. Toda rama circuito/item-<id>-* ya existente CUENTA
        // como "la rama del item" sin importar su slug.
        $existentes = $this->ramasDelItem((int) $item->id);

        // 2026-10-26 — los ids de items se reusaron (reinicio del autoincremento), así que una rama
        // circuito/item-<id>-* puede ser de OTRO trabajo viejo con el mismo número. Reusarla metía
        // ese trabajo ajeno al merge del item nuevo (pasó con #585 y #939, y frenó 7 items de UNI-T).
        // Una rama cuyos commits propios son TODOS anteriores al alta del item no es suya: se ignora
        // (no se borra) y el item recibe una rama nueva desde main.
        $heredadas = array_values(array_filter($existentes, fn ($r) => $this->esRamaHeredada($r, $item)));
        if ($heredadas !== []) {
            $this->registrarRamasHeredadas($item, $heredadas);
            $existentes = array_values(array_diff($existentes, $heredadas));
            if (in_array($branchDeseada, $heredadas, true)) {
                $branchDeseada .= '-' . now()->format('ymdHi');
            }
        }

        if (count($existentes) > 1) {
            $elegida = $this->elegirRamaConMasTrabajo($existentes);
            $this->registrarRamasMultiples($item, $existentes, $elegida);
            $this->warn("El item #{$item->id} tiene " . count($existentes) . ' ramas circuito/item-'
                . "{$item->id}-* (anomalía, #9990843) — se usa la de más trabajo: {$elegida}.");

            return $this->reusarRama($item, $elegida);
        }

        if (count($existentes) === 1) {
            return $this->reusarRama($item, $existentes[0]);
        }

        // Ninguna rama existe todavía para este id: crear desde main con el nombre del slug actual.
        if (trim($this->git(['status', '--porcelain', '--untracked-files=no'])->getOutput()) !== '') {
            $this->error('El árbol de trabajo tiene cambios sin commitear; commitea o descarta antes de ramificar.');
            return self::FAILURE;
        }

        // Rama desde el ref `main` SIN checar main. En el modelo de worktree aislado (#334
        // Fase 0) `main` vive checado en el checkout principal (/var/www/megaisp) y git PROHÍBE
        // checarlo en dos worktrees a la vez. `checkout -b X main` crea la rama desde el tip de
        // main y la checa, sin tocar main → funciona tanto en el worktree del ejecutor como en
        // el checkout principal. (Antes: `checkout main` + `checkout -b X`, que rompía en worktree.)
        if (! $this->git(['checkout', '-b', $branchDeseada, 'main'])->isSuccessful()) {
            $this->error("No se pudo crear la rama {$branchDeseada} desde main.");
            return self::FAILURE;
        }

        $this->registrar($item, $branchDeseada);
        $this->info("Rama {$branchDeseada} creada desde main y registrada en el item #{$item->id}.");
        return self::SUCCESS;
    }

    /**
     * Checkout + rebase (best-effort) de una rama YA EXISTENTE del item, y la registra.
     * Extraído de `handle()` (#9990843) para poder reusarlo tanto en el caso normal (1 rama)
     * como en la anomalía de >1 rama (se reusa la elegida por `elegirRamaConMasTrabajo()`).
     */
    private function reusarRama(RoadmapItem $item, string $branch): int
    {
        $this->git(['checkout', $branch]);
        if (! $this->git(['merge-base', '--is-ancestor', 'main', $branch])->isSuccessful()) {
            $rebase = $this->git(['rebase', 'main']);
            if ($rebase->isSuccessful()) {
                $this->info("Rama {$branch} rebasada sobre main (#438).");
            } else {
                $this->git(['rebase', '--abort']);
                $this->warn("No se pudo rebasar {$branch} sobre main (posible conflicto); sigue sobre su base anterior.");
            }
        }
        $this->registrar($item, $branch);
        $this->info("Rama {$branch} ya existía; checkout hecho. Registrada en el item #{$item->id}.");
        return self::SUCCESS;
    }

    /**
     * ¿La rama es de un trabajo VIEJO que tuvo el mismo id? Sí cuando tiene commits fuera de main y
     * TODOS son anteriores al alta del item (fecha de autor, que sobrevive a rebase y cherry-pick).
     * Una rama sin commits propios no es heredada (reusarla solo la adelanta a main), y una con al
     * menos un commit posterior al alta ya tiene trabajo de este item (p. ej. B1 de UNI-T, que
     * integró commits más viejos a propósito).
     */
    private function esRamaHeredada(string $rama, RoadmapItem $item): bool
    {
        if (! $item->created_at) {
            return false;
        }
        $p = $this->git(['log', '--format=%at', "main..{$rama}"]);
        if (! $p->isSuccessful()) {
            return false;
        }
        $fechas = array_map('intval', array_filter(explode("\n", trim($p->getOutput()))));

        return $fechas !== [] && max($fechas) < $item->created_at->getTimestamp();
    }

    /** Deja rastro en el log del item de las ramas viejas que se ignoraron (no se borran). */
    private function registrarRamasHeredadas(RoadmapItem $item, array $ramas): void
    {
        $item->log = array_merge($item->log ?: [], [[
            'ts' => now()->toIso8601String(), 'por' => 'circuito:rama', 'evento' => 'ramas_heredadas_ignoradas',
            'ramas' => $ramas,
            'nota' => 'Ramas con el mismo número pero de un trabajo anterior al alta del item: no se reusan ni se borran.',
        ]]);
        if ($item->branch && in_array($item->branch, $ramas, true)) {
            $item->branch = null;
        }
        $item->save();

        $this->warn('Ramas de un trabajo anterior con el mismo número, ignoradas: ' . implode(', ', $ramas));
    }

    /** Ramas locales `circuito/item-<id>-*` existentes (orden de git, típicamente alfabético). */
    private function ramasDelItem(int $id): array
    {
        $p = $this->git(['for-each-ref', '--format=%(refname:short)', "refs/heads/circuito/item-{$id}-*"]);
        if (! $p->isSuccessful()) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", trim($p->getOutput())))));
    }

    /** De varias ramas candidatas, la que tiene MÁS commits por delante de main (más trabajo real). */
    private function elegirRamaConMasTrabajo(array $ramas): string
    {
        $mejor = $ramas[0];
        $mejorCount = -1;
        foreach ($ramas as $rama) {
            $p = $this->git(['rev-list', '--count', "main..{$rama}"]);
            $count = $p->isSuccessful() ? (int) trim($p->getOutput()) : 0;
            if ($count > $mejorCount) {
                $mejorCount = $count;
                $mejor = $rama;
            }
        }

        return $mejor;
    }

    /**
     * Deja rastro de la anomalía (>1 rama para el mismo item) en el log del item + canal de
     * auditoría, con cuántos commits trae cada candidata — para que se pueda revisar a mano si
     * la elegida automáticamente no era la correcta (#9990843).
     */
    private function registrarRamasMultiples(RoadmapItem $item, array $ramas, string $elegida): void
    {
        $detalle = [];
        foreach ($ramas as $rama) {
            $p = $this->git(['rev-list', '--count', "main..{$rama}"]);
            $detalle[$rama] = $p->isSuccessful() ? (int) trim($p->getOutput()) : null;
        }

        $log = $item->log ?: [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => 'circuito:rama', 'evento' => 'multiples_ramas_detectadas',
            'ramas' => $detalle, 'elegida' => $elegida];
        $item->log = $log;
        $item->save();

        \Illuminate\Support\Facades\Log::channel('roadmap_externo')->warning('multiples-ramas-item', [
            'item' => $item->id, 'ramas' => $detalle, 'elegida' => $elegida,
        ]);
    }

    /**
     * #341: ¿hay colisión REAL que impida ramificar? `en_progreso` por sí solo NO lo es (el propio
     * worker lo acaba de reclamar antes de correr la rama). Bloquea solo:
     *   - candado humano (en_desarrollo_humano), o
     *   - en_progreso pero con `worker_sid` de OTRA terminal (distinto al SID que corre la rama).
     * Devuelve el motivo del bloqueo, o null si se puede ramificar. Público para testeo.
     */
    public function motivoColision(RoadmapItem $item, ?string $sid): ?string
    {
        if ((bool) $item->en_desarrollo_humano) {
            return 'lo bloqueó un humano (en_desarrollo_humano)';
        }
        if ($item->estado_aprobacion === 'en_progreso'
            && $sid !== null && $item->worker_sid !== null && $item->worker_sid !== $sid) {
            return "lo trabaja otra terminal ({$item->worker_sid}, no {$sid})";
        }

        return null;
    }

    protected function repoDir(): string
    {
        return base_path();
    }

    private function registrar(RoadmapItem $item, string $branch): void
    {
        $item->branch = $branch;
        $item->save();
    }

    /** Protegido solo como costura de prueba (RamaItemHeredadaTest corre contra un repo temporal). */
    protected function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), $this->repoDir());
        $p->run();
        return $p;
    }
}
