<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\ThomasService;
use Illuminate\Console\Command;

/**
 * #566 — RE-TRIAJE de la bandeja con el carril mecánico.
 *
 * Pasa la política nueva sobre todo lo que está esperando decisión y devuelve a la cola lo que
 * resulte mecánico + reversible + no-prod. Lo demás se queda con Irving, pero AGRUPADO POR MOTIVO,
 * que es lo que permite despacharlo rápido en vez de leer 40 items sueltos.
 *
 * Dry-run por default: hay que pedir `--apply` para escribir.
 *
 * Lo que NO toca, aunque parezca elegible (y por qué):
 *  - `bloqueado_por_bucle`: el anti-bucle los sacó del pool tras 3 escalaciones idénticas sin
 *    cambio material. Re-aprobarlos sin que nada haya cambiado los devuelve al mismo bucle —
 *    exactamente lo que ese guard existe para impedir (#117 dio 13 vueltas; #99, dieciséis).
 *  - Items CON RAMA: ya hay trabajo empezado; re-encolarlos arriesga que otra terminal lo pise.
 *  - `esperando_merge_irving`: el trabajo está HECHO y espera el merge, no una decisión.
 */
class RetriarBandejaCommand extends Command
{
    protected $signature = 'circuito:retriar-bandeja
        {--apply : escribe los cambios (sin esto solo muestra el plan)}
        {--limit=200 : tope de items a revisar}
        {--incluir-bucle : incluir también los bloqueado_por_bucle (por default se respetan)}';

    protected $description = 'Re-tría la bandeja con el carril mecánico: lo mecánico vuelve a la cola, lo demás queda agrupado por motivo.';

    public function handle(ThomasService $thomas): int
    {
        $aplicar = (bool) $this->option('apply');

        $q = RoadmapItem::whereNull('archivado_at')
            ->whereIn('estado_aprobacion', ['requiere_irving', 'pendiente_revision'])
            ->where('status', '!=', 'done')
            ->whereNull('branch')
            ->where(fn ($x) => $x->whereNull('esperando_merge_irving')->orWhere('esperando_merge_irving', false));

        if (! $this->option('incluir-bucle')) {
            $q->where(fn ($x) => $x->whereNull('bloqueado_por_bucle')->orWhere('bloqueado_por_bucle', false));
        }

        $items = $q->orderBy('id')->limit((int) $this->option('limit'))->get();

        if ($items->isEmpty()) {
            $this->info('No hay items re-triables en la bandeja.');

            return self::SUCCESS;
        }

        $aCola      = [];
        $sePosponen = [];   // motivo => [ids]

        foreach ($items as $item) {
            if ($aplicar) {
                $r = $thomas->aprobarMecanico($item);
                $ok = $r['aprobado'];
                $motivo = $r['motivo'];
                $estado = $r['estado'];
            } else {
                $c = $thomas->clasificarMecanico($item);
                $ok = $c['mecanico'];
                $motivo = $c['motivo'];
                $estado = $item->nivel_riesgo === 'A' ? 'aprobado_claude' : 'aprobado_revisor';
            }

            if ($ok) {
                $aCola[] = ['id' => (int) $item->id, 'estado' => $estado, 'title' => (string) $item->title];
                $this->line(($aplicar ? '' : 'DRY ') . "→ COLA  #{$item->id} ({$estado})  "
                    . mb_strimwidth(preg_replace('/\s+/', ' ', (string) $item->title), 0, 56, '…'));
            } else {
                $sePosponen[$this->agrupar($motivo)][] = (int) $item->id;
            }
        }

        // ── Reporte ──────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('════ RESULTADO DEL RE-TRIAJE ════');
        $this->line('Revisados: ' . $items->count());
        $this->line('A la cola: ' . count($aCola));
        $this->line('Se quedan con Irving: ' . array_sum(array_map('count', $sePosponen)));

        if ($sePosponen) {
            $this->newLine();
            $this->comment('Se quedan contigo, agrupados por motivo:');
            foreach ($sePosponen as $motivo => $ids) {
                sort($ids);
                $this->line("  · {$motivo}");
                $this->line('      ' . count($ids) . ' item(s): #' . implode(' #', $ids));
            }
        }

        if (! $aplicar) {
            $this->newLine();
            $this->comment('DRY — nada escrito. Corre con --apply para mover a la cola.');
        }

        return self::SUCCESS;
    }

    /**
     * Colapsa el motivo detallado a una categoría legible, para que el reporte se lea de un
     * vistazo en vez de mostrar 40 frases casi iguales.
     */
    private function agrupar(string $motivo): string
    {
        return match (true) {
            str_contains($motivo, 'frontera dura')       => 'Frontera dura (prod / borrar datos / dinero / credenciales)',
            str_contains($motivo, 'negocio/producto')    => 'Decisión de negocio o producto',
            str_contains($motivo, 'decisión de diseño')  => 'Nivel C — decisión de diseño de Irving',
            str_contains($motivo, 'Sin nivel')           => 'Sin triar (falta nivel de riesgo)',
            str_contains($motivo, '[BLOCKED-')           => 'Rotulado [BLOCKED-]/[PARKED-]',
            str_contains($motivo, 'Tope diario')         => 'Tope diario del carril mecánico alcanzado',
            str_contains($motivo, 'señal mecánica')      => 'Sin señal mecánica reconocible (ante la duda, tuyo)',
            default                                      => $motivo,
        };
    }
}
