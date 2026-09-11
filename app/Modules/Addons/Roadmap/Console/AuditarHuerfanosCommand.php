<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Fase 1 de #9990730 (sub-item de seguimiento de #9990719). 100% READ-ONLY sobre git: nunca
 * hace checkout/merge/commit, solo `git show-ref` y `git rev-list --count` contra el repo real
 * (`base_path()`), vía Symfony\Process (nunca shell_exec con interpolación de string).
 *
 * Universo: items `completado` con `branch` pero sin `merge_commit` — la misma consulta que
 * documentó #9990719 (ver `docs/circuito-divergencia-completados-sin-merge-item-9990719-verificacion.md`
 * como fixture de referencia; los 21 "solo_registro" que ese doc midió ya se backfillearon vía
 * #9990731, así que una corrida de hoy no debería reproducir los mismos 23/21/1 — el propio spec
 * del item anticipa el ajuste).
 *
 * Categorías por item:
 *   - rama_inexistente : `git show-ref --verify --quiet refs/heads/<branch>` falla (ya no existe).
 *   - solo_registro     : la rama existe y `git rev-list --count main..<branch>` = 0 (ya integrado,
 *                         solo falta rellenar merge_commit).
 *   - divergencia_real  : la rama existe y tiene commits que main no tiene (`ahead` > 0).
 *
 * Decisión q1 de Irving ya aprobada ("marca con flag requiere_revision + notifica en bandeja"): no
 * existe la columna `requiere_revision` (grep confirmado, 0 resultados) y no se agrega aquí — el
 * propio log del item ya es el flag persistente/auditable (evento `auditoria_huerfano`), y la parte
 * de "notifica en bandeja" queda para el sub-item de Fase 2 (indicador de Torre que lee estos
 * eventos). Este comando solo entrega el reporte + el registro en log, sin scheduler todavía.
 *
 * Dedup: por cada item se compara (categoria, ahead) contra la última entrada `auditoria_huerfano`
 * ya registrada en su `log[]`. Si coincide, el estado no cambió desde la última corrida y NO se
 * vuelve a anotar (evita spamear el log cada vez que se corre el comando sin que nada haya
 * cambiado). Si difiere (o es la primera vez), se agrega una entrada nueva.
 *
 *   php artisan circuito:auditar-huerfanos
 */
class AuditarHuerfanosCommand extends Command
{
    protected $signature = 'circuito:auditar-huerfanos';

    protected $description = 'READ-ONLY: categoriza items completado con branch sin merge_commit (rama_inexistente / solo_registro / divergencia_real) — Fase 1 de #9990730.';

    public function handle(): int
    {
        $items = RoadmapItem::query()
            ->where('estado_aprobacion', 'completado')
            ->whereNotNull('branch')
            ->whereNull('merge_commit')
            ->orderBy('id')
            ->get(['id', 'title', 'branch', 'log']);

        $this->line('');
        $this->line('<options=bold>AUDITORÍA DE HUÉRFANOS — completado con branch sin merge_commit (#9990736)</>');
        $this->line("  universo: {$items->count()} item(s)");

        $conteo = ['rama_inexistente' => 0, 'solo_registro' => 0, 'divergencia_real' => 0];
        $nuevos = 0;

        foreach ($items as $item) {
            [$categoria, $ahead] = $this->clasificar((string) $item->branch);
            $conteo[$categoria]++;

            if ($this->yaRegistrado($item, $categoria, $ahead)) {
                continue;
            }

            $log = is_array($item->log) ? $item->log : [];
            $log[] = [
                'ts'       => now()->toIso8601String(),
                'por'      => 'circuito:auditar-huerfanos',
                'evento'   => 'auditoria_huerfano',
                'categoria' => $categoria,
                'ahead'    => $ahead,
                'branch'   => $item->branch,
            ];
            $item->log = $log;
            $item->save();
            $nuevos++;
        }

        $this->line('');
        $this->line(sprintf(
            '  divergencia_real: %d  ·  solo_registro: %d  ·  rama_inexistente: %d  ·  hallazgos nuevos anotados: %d',
            $conteo['divergencia_real'],
            $conteo['solo_registro'],
            $conteo['rama_inexistente'],
            $nuevos
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: int|null} [categoria, ahead]
     */
    private function clasificar(string $branch): array
    {
        $existe = $this->git(['show-ref', '--verify', '--quiet', "refs/heads/{$branch}"]);
        if (! $existe->isSuccessful()) {
            return ['rama_inexistente', null];
        }

        $count = $this->git(['rev-list', '--count', "main..{$branch}"]);
        $ahead = $count->isSuccessful() ? (int) trim($count->getOutput()) : null;

        if ($ahead === null) {
            // No debería ocurrir con una rama que sí existe, pero por seguridad no se inventa un 0.
            return ['divergencia_real', null];
        }

        return [$ahead === 0 ? 'solo_registro' : 'divergencia_real', $ahead];
    }

    private function yaRegistrado(RoadmapItem $item, string $categoria, ?int $ahead): bool
    {
        $log = is_array($item->log) ? $item->log : [];
        foreach ($log as $entrada) {
            if (($entrada['evento'] ?? null) !== 'auditoria_huerfano') {
                continue;
            }
            if (($entrada['categoria'] ?? null) === $categoria && ($entrada['ahead'] ?? null) === $ahead) {
                return true;
            }
        }

        return false;
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->setTimeout(30);
        $p->run();

        return $p;
    }
}
