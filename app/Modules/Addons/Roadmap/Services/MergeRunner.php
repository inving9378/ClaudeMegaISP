<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Runner de MERGE del Circuito (#334 F0-fix). Ejecuta el merge REAL de las ramas de item a `main`.
 *
 * POR QUÉ existe: la Torre corre como www-data (php-fpm), que NO puede escribir `.git` (objetos/refs
 * los creó el ejecutor=meganet, sin group-write para www-data) → un merge desde la Torre fallaba en
 * SILENCIO. Solución: la Torre ENCOLA (RoadmapCircuitoService::enqueueMerge) y ESTE runner, corriendo
 * on-box como meganet en el checkout PRINCIPAL (/var/www/megaisp, donde vive `main`), drena la cola.
 *
 * Garantías (lo que pidió Irving):
 *  - Corre en el checkout principal (donde `main` está checado) — resuelto por base_path().
 *  - SERIALIZADO: flock('merge.lock') → un merge a la vez, aunque lo llamen varios pickers.
 *  - Verificación de REGRESIÓN antes de aplicar: merge en 2 fases (--no-commit → verifica → commit).
 *  - Fallo (conflicto/regresión/permiso) → ABORTA, deja main intacto, escala el item a requiere_irving
 *    y GUARDA el error (mergeResult) para que la Torre lo MUESTRE. Nunca silencioso.
 *  - Kill switch: los merges se drenan siempre (una decisión ya tomada); el pause solo frena al ejecutor.
 */
class MergeRunner
{
    private const LOCK = '/home/meganet/circuito/merge.lock';

    public function __construct(private RoadmapCircuitoService $svc)
    {
    }

    /**
     * Drena la cola de merge (serializado por flock). Devuelve la lista de resultados.
     * Si el lock está ocupado (otro drain corriendo) devuelve [] sin bloquear.
     */
    public function drain(): array
    {
        @mkdir(dirname(self::LOCK), 0775, true);
        $lock = @fopen(self::LOCK, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return []; // ya hay un drain en curso
        }

        $out = [];
        try {
            while (($req = $this->svc->dequeueMerge()) !== null) {
                $itemId = (int) ($req['item_id'] ?? 0);
                if ($itemId <= 0) {
                    continue;
                }
                $item = RoadmapItem::find($itemId);
                if (! $item) {
                    $this->svc->recordMergeResult($itemId, $this->fail('Item no encontrado.', false));
                    continue;
                }
                $res = $this->performMerge($item, $req);
                // Escala a la bandeja de Irving si el merge falló y es escalable (conflicto/regresión).
                if (! empty($res['escalado'])) {
                    $item->estado_aprobacion = 'requiere_irving';
                    $log = $item->log ?: [];
                    $log[] = ['ts' => now()->toIso8601String(), 'por' => 'merge-runner', 'evento' => 'merge_escalado',
                        'branch' => $item->branch, 'motivo' => mb_substr((string) ($res['salida'] ?? ''), 0, 300)];
                    $item->log = $log;
                    $item->save();
                    Log::channel('roadmap_externo')->warning('merge-escalado', ['item' => $itemId, 'motivo' => $res['salida'] ?? '']);
                }
                $this->svc->recordMergeResult($itemId, $res);
                $out[] = ['item_id' => $itemId] + $res;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $out;
    }

    /**
     * Merge REAL de UNA rama a main (checkout principal, como meganet). 2 fases con verificación.
     * NUNCA hace push ni toca prod.
     */
    public function performMerge(RoadmapItem $item, array $req = []): array
    {
        $branch = (string) ($item->branch ?? '');
        if ($branch === '') {
            return $this->fail("El item #{$item->id} no tiene rama registrada.", true);
        }

        // ¿Existe la rama localmente?
        if (! $this->git(['rev-parse', '--verify', $branch])->isSuccessful()) {
            return $this->fail("La rama {$branch} no existe localmente.", true);
        }

        // Ya integrada (rama es ancestro de main) → no-op idempotente.
        if ($this->git(['merge-base', '--is-ancestor', $branch, 'main'])->isSuccessful()) {
            $sha = trim($this->git(['rev-parse', 'HEAD'])->getOutput());
            $this->markMerged($item, $item->merge_commit ?: $sha, $branch);

            return ['estado' => 'ok', 'ok' => true, 'merge_commit' => $item->merge_commit ?: $sha,
                'salida' => "La rama {$branch} ya estaba integrada en main.", 'escalado' => false, 'at' => time()];
        }

        // El árbol principal debe estar limpio (solo tracked); untracked no afecta al merge.
        if (trim($this->git(['status', '--porcelain', '--untracked-files=no'])->getOutput()) !== '') {
            return $this->fail('El checkout principal tiene cambios sin commitear; no se puede mergear con seguridad.', true);
        }

        // Asegura estar en main (donde vive el working tree de dev).
        if (! $this->git(['checkout', 'main'])->isSuccessful()) {
            return $this->fail('No se pudo cambiar a main en el checkout principal.', true);
        }

        // FASE 1: merge staged SIN commitear → detecta conflictos sin dejar rastro.
        $merge = $this->git(['merge', '--no-ff', '--no-commit', $branch]);
        if (! $merge->isSuccessful()) {
            $salida = trim($merge->getErrorOutput() . "\n" . $merge->getOutput());
            $this->git(['merge', '--abort']);

            return $this->fail("Conflicto al mergear {$branch} → abortado, main intacto.\n" . $salida, true);
        }

        // FASE 2: verificación de regresión sobre el árbol ya fusionado (aún sin commit).
        $reg = $this->regression();
        if (! $reg['ok']) {
            $this->git(['merge', '--abort']);

            return $this->fail("Regresión al integrar {$branch}: {$reg['detalle']}\nMerge abortado, main intacto.", true);
        }

        // FASE 3: finaliza el merge (commit).
        $commit = $this->git(['commit', '--no-edit', '-m', "Integra circuito #{$item->id} ({$branch}) a main"]);
        if (! $commit->isSuccessful()) {
            $this->git(['merge', '--abort']);

            return $this->fail("No se pudo commitear el merge de {$branch}.\n" . trim($commit->getErrorOutput()), true);
        }

        $sha = trim($this->git(['rev-parse', 'HEAD'])->getOutput());
        $this->markMerged($item, $sha, $branch);

        Log::channel('roadmap_externo')->info('merge-ok', ['item' => $item->id, 'branch' => $branch, 'merge_commit' => $sha,
            'trigger' => $req['trigger'] ?? '?']);

        return ['estado' => 'ok', 'ok' => true, 'merge_commit' => $sha,
            'salida' => "Integrada a dev (merge {$sha}). Regresión OK.", 'escalado' => false, 'at' => time()];
    }

    /** Verificación de regresión ligera sobre el árbol fusionado (sin correr la suite destructiva). */
    private function regression(): array
    {
        // (a) php -l de los .php cambiados por el merge (staged).
        $changed = array_filter(preg_split('/\R/', trim(
            $this->git(['diff', '--cached', '--name-only'])->getOutput()
        )));
        foreach ($changed as $f) {
            if (! str_ends_with($f, '.php')) {
                continue;
            }
            $path = base_path($f);
            if (! is_file($path)) {
                continue; // borrado por el merge
            }
            $lint = new Process(['php', '-l', $path], base_path());
            $lint->run();
            if (! $lint->isSuccessful()) {
                return ['ok' => false, 'detalle' => "php -l falló en {$f}: " . trim($lint->getErrorOutput() . $lint->getOutput())];
            }
        }

        // (b) el framework bootea con el código fusionado (caza fatales de carga).
        $boot = new Process(['php', 'artisan', '--version'], base_path());
        $boot->setTimeout(60);
        $boot->run();
        if (! $boot->isSuccessful()) {
            return ['ok' => false, 'detalle' => 'php artisan no bootea con el código fusionado: ' . trim($boot->getErrorOutput())];
        }

        return ['ok' => true, 'detalle' => 'OK'];
    }

    /** Marca el item como integrado. */
    private function markMerged(RoadmapItem $item, string $sha, string $branch): void
    {
        $item->merge_commit = $sha;
        if ($item->status !== 'done') {
            $item->status = 'done';
        }
        $item->estado_aprobacion = 'completado';
        $log = $item->log ?: [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => 'merge-runner', 'evento' => 'merge_a_main',
            'branch' => $branch, 'merge_commit' => $sha];
        $item->log = $log;
        $item->save();
    }

    /** Construye un resultado de FALLO; $escalate marca que el item debe ir a la bandeja de Irving. */
    private function fail(string $salida, bool $escalate): array
    {
        return ['estado' => 'error', 'ok' => false, 'merge_commit' => null,
            'salida' => $salida, 'escalado' => $escalate, 'at' => time()];
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->setTimeout(120);
        $p->run();

        return $p;
    }
}
