<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\ThomasService;
use Illuminate\Console\Command;

/**
 * #566 — DESTRABE: Thomas resuelve lo que hoy se queda esperando a Irving.
 *
 * Ordena el trabajo por lo que cada item ESPERA DE VERDAD (`ThomasService::pendienteReal`), que es
 * la pieza que faltaba. El bucle de #117 (13 vueltas) y las 9 aprobaciones de #19 no eran falta de
 * permiso: era que **aprobar se trataba como responder**. Un item cuya única pendiente era el
 * merge volvía a `aprobado_irving` con cada clic, el pool lo re-despachaba, el worker veía que no
 * había nada que hacer y lo re-escalaba. Aprobar más fuerte nunca lo iba a mover.
 *
 * Por eso aquí cada item se enruta a lo que le falta:
 *   merge       → auto-merge si es reversible y no toca prod (E1)
 *   ejecucion   → auto-decisión si hay default/patrón/reversibilidad (E2)
 *   respuesta   → al consolidado, con la recomendación de Thomas (E4)
 *   dependencia → se deja, con su motivo
 *
 * Dry-run por default. Cap y kill-switch en `config/circuito.php → thomas.automerge` y
 * `thomas.mecanico`; el kill-switch global sigue siendo `circuito_pausado`.
 */
class DestrabarCommand extends Command
{
    protected $signature = 'circuito:destrabar-bandeja
        {--apply : escribe los cambios (sin esto solo muestra el plan)}
        {--limit=120 : tope de items a revisar}
        {--cap= : sobreescribe el cap de auto-merges de ESTA corrida (el de config rige el régimen normal)}';

    protected $description = 'Thomas destraba: auto-mergea lo verificado, decide lo reversible y consolida lo estratégico (#566).';

    public function handle(ThomasService $thomas): int
    {
        $aplicar = (bool) $this->option('apply');
        // El cap de config rige el régimen normal (un ciclo cada minuto). El override existe para
        // el destrabe INICIAL, donde hay una pila histórica que drenar de una: son merges que ya
        // debieron pasar hace semanas, no ritmo nuevo.
        $capMerge = $this->option('cap') !== null
            ? max(1, (int) $this->option('cap'))
            : (int) config('circuito.thomas.automerge.cap_por_ciclo', 5);

        // Todo lo que está esperando algo de Irving, incluidos los parqueados y los del anti-bucle:
        // el punto del destrabe es justamente mirar esas bolsas.
        $items = RoadmapItem::whereNull('archivado_at')
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
            ->where(fn ($q) => $q
                ->whereIn('estado_aprobacion', ['requiere_irving', 'pendiente_revision', 'aprobado_irving'])
                ->orWhere('esperando_merge_irving', true)
                ->orWhere('bloqueado_por_bucle', true))
            ->where('status', '!=', 'done')
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $mergeados = [];
        $decididos = [];
        $consolida = [];
        $retenidos = [];   // motivo => [ids]
        $capAlcanzado = false;

        foreach ($items as $item) {
            $p = $thomas->pendienteReal($item);

            switch ($p['pendiente']) {
                case 'merge':
                    if (count($mergeados) >= $capMerge) {
                        $capAlcanzado = true;
                        $retenidos['Cap de auto-merges por ciclo alcanzado (vuelve el próximo ciclo)'][] = $item->id;
                        break;
                    }
                    $e = $aplicar ? $thomas->autoMergear($item) : ['ok' => $thomas->elegibleAutoMerge($item)['elegible'],
                        'motivo' => $thomas->elegibleAutoMerge($item)['motivo']];
                    if ($e['ok']) {
                        $mergeados[] = $item->id;
                        $this->line(($aplicar ? '' : 'DRY ') . "→ MERGE     #{$item->id}  " . $this->corto($item));
                    } else {
                        $retenidos[$this->agrupar($e['motivo'])][] = $item->id;
                    }
                    break;

                case 'ejecucion':
                    $r = $aplicar
                        ? $thomas->aprobarMecanico($item)
                        : ['aprobado' => $thomas->clasificarMecanico($item)['mecanico'],
                            'motivo' => $thomas->clasificarMecanico($item)['motivo']];
                    if ($r['aprobado']) {
                        $decididos[] = $item->id;
                        $this->line(($aplicar ? '' : 'DRY ') . "→ DECIDIDO  #{$item->id}  " . $this->corto($item));
                    } else {
                        $retenidos[$this->agrupar($r['motivo'])][] = $item->id;
                    }
                    break;

                case 'respuesta':
                    $consolida[] = $item;
                    $this->line(($aplicar ? '' : 'DRY ') . "→ CONSOLIDA #{$item->id}  " . $this->corto($item));
                    break;

                default:
                    $retenidos[$this->agrupar($p['detalle'])][] = $item->id;
            }
        }

        // ── Reporte ──────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('════ DESTRABE ════');
        $this->line('Revisados: ' . $items->count());
        $this->line('Auto-mergeados: ' . count($mergeados) . ($capAlcanzado ? " (cap {$capMerge} alcanzado)" : ''));
        $this->line('Auto-decididos: ' . count($decididos));
        $this->line('Al consolidado de Irving: ' . count($consolida));
        $this->line('Retenidos: ' . array_sum(array_map('count', $retenidos)));

        if ($retenidos) {
            $this->newLine();
            $this->comment('Retenidos, agrupados por motivo:');
            foreach ($retenidos as $motivo => $ids) {
                sort($ids);
                $this->line("  · {$motivo}");
                $this->line('      ' . count($ids) . ': #' . implode(' #', array_slice($ids, 0, 25)));
            }
        }

        if (! $aplicar) {
            $this->newLine();
            $this->comment('DRY — nada escrito. Corre con --apply.');
        }

        return self::SUCCESS;
    }

    private function corto(RoadmapItem $i): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', (string) $i->title), 0, 56, '…');
    }

    private function agrupar(string $motivo): string
    {
        return match (true) {
            str_contains($motivo, 'frontera dura')        => 'Frontera dura (prod / borrar datos / dinero / credenciales)',
            str_contains($motivo, 'ruta sensible')        => 'La rama toca rutas sensibles (prod/deploy)',
            str_contains($motivo, 'git revert')           => 'Migración destructiva: no se deshace con git revert',
            str_contains($motivo, 'negocio/producto')     => 'Decisión de negocio o producto',
            str_contains($motivo, 'decisión de diseño')   => 'Nivel C — decisión de diseño de Irving',
            str_contains($motivo, 'Sin nivel')            => 'Sin triar (falta nivel de riesgo)',
            str_contains($motivo, 'señal mecánica')       => 'Sin señal mecánica reconocible (ante la duda, tuyo)',
            str_contains($motivo, 'Depende de')           => 'Espera a otro item',
            str_contains($motivo, 'no trae cambios')      => 'Rama vacía: no hay nada que integrar',
            str_contains($motivo, 'Tope diario')          => 'Tope diario del carril mecánico alcanzado',
            str_contains($motivo, 'Cap de auto-merges')   => 'Cap de auto-merges por ciclo alcanzado',
            default                                       => $motivo,
        };
    }
}
