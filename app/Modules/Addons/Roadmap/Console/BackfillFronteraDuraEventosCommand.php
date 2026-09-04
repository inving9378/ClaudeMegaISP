<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\TorreFronteraDuraEvento;
use App\Modules\Addons\Roadmap\Services\FronterasService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Pieza 1a (#764, sub-item de #672) — BACKFILL histórico de `torre_frontera_dura_eventos`.
 *
 * Recorre `roadmap_items.log` buscando los eventos `valvula_contexto`/`valvula_nacimiento` con
 * `veredicto=mencion` — los únicos que `JarvisService::fronteraDuraDeItemDetalle()` (líneas
 * 458-482) usa para ablandar una frontera dura a «requiere Irving» — y los proyecta a filas
 * consultables. `veredicto=accion` NO se copia: ésos nunca ablandan nada, la frontera se aplica
 * tal cual y no hay control adicional que auditar ahí.
 *
 * CATEGORÍA: el evento `valvula_nacimiento` la trae directa (`e['categoria']`, desde el fix de
 * #648). El evento `valvula_contexto` NUNCA la trajo — sólo guarda `termino` — así que se resuelve
 * con el mismo lookup término→categoría que ya usa `FronterasService::aperturasDeValvula()` para
 * este mismo problema (reuso a propósito, «prohibido duplicar»): construido desde `mapa()` (la
 * config VIGENTE de fronteras), e indexando también la categoría por sí misma como término válido
 * — los eventos anteriores al 2026-08-27 guardaban la categoría en el campo `termino` (el bug que
 * `FronterasService.php:461-464` documenta y que #648 corrigió).
 *
 * IDEMPOTENTE: `updateOrCreate` sobre (roadmap_item_id, termino, ocurrido_at) — la misma llave
 * única de la migración — así que re-correrlo no duplica.
 */
class BackfillFronteraDuraEventosCommand extends Command
{
    protected $signature = 'circuito:backfill-frontera-dura-eventos
        {--sid= : tu slot de terminal (wt-K), solo para el log}';

    protected $description = 'Pieza 1a (#764) — backfill de torre_frontera_dura_eventos desde roadmap_items.log (eventos valvula_contexto/valvula_nacimiento con veredicto=mencion).';

    public function handle(FronterasService $fronteras): int
    {
        $terminoACategoria = [];
        foreach ($fronteras->mapa() as $categoria => $cfg) {
            foreach ($cfg['terminos'] as $t) {
                $terminoACategoria[mb_strtolower((string) $t['termino'])] = (string) $categoria;
            }
            // Ver docblock: eventos viejos guardaron la categoría bajo `termino`.
            $terminoACategoria[mb_strtolower((string) $categoria)] = (string) $categoria;
        }

        $itemsConEventos = 0;
        $insertados = 0;
        $yaExistian = 0;
        $sinCategoria = [];

        RoadmapItem::query()
            ->whereNotNull('log')
            ->select(['id', 'log'])
            ->chunkById(200, function ($items) use (
                $terminoACategoria,
                &$itemsConEventos,
                &$insertados,
                &$yaExistian,
                &$sinCategoria
            ) {
                foreach ($items as $item) {
                    $log = is_array($item->log) ? $item->log : [];
                    $tocoEsteItem = false;

                    foreach ($log as $entrada) {
                        if (! is_array($entrada)) {
                            continue;
                        }
                        $evento = $entrada['evento'] ?? null;
                        if ($evento !== 'valvula_contexto' && $evento !== 'valvula_nacimiento') {
                            continue;
                        }
                        if (($entrada['veredicto'] ?? null) !== 'mencion') {
                            continue;
                        }

                        $termino = trim((string) ($entrada['termino'] ?? ''));
                        $ts = (string) ($entrada['ts'] ?? '');
                        if ($termino === '' || $ts === '') {
                            continue; // sin llave para el unique de idempotencia: se salta, no se adivina.
                        }

                        $categoria = $entrada['categoria'] ?? null;
                        if (! is_string($categoria) || $categoria === '') {
                            $categoria = $terminoACategoria[mb_strtolower($termino)] ?? null;
                        }
                        if ($categoria === null) {
                            $sinCategoria[$termino] = ($sinCategoria[$termino] ?? 0) + 1;
                            continue; // no se inventa una categoría: se cuenta y se salta.
                        }

                        $tocoEsteItem = true;
                        $ocurridoAt = Carbon::parse($ts);

                        $existiaAntes = TorreFronteraDuraEvento::query()
                            ->where('roadmap_item_id', $item->id)
                            ->where('termino', $termino)
                            ->where('ocurrido_at', $ocurridoAt)
                            ->exists();

                        TorreFronteraDuraEvento::updateOrCreate(
                            [
                                'roadmap_item_id' => $item->id,
                                'termino'         => $termino,
                                'ocurrido_at'     => $ocurridoAt,
                            ],
                            [
                                'categoria'  => $categoria,
                                'veredicto'  => 'mencion',
                                'razon'      => $entrada['motivo'] ?? null,
                                'origen'     => 'backfill_log',
                                'created_at' => now(),
                            ]
                        );

                        $existiaAntes ? $yaExistian++ : $insertados++;
                    }

                    if ($tocoEsteItem) {
                        $itemsConEventos++;
                    }
                }
            });

        $this->info("Items con al menos un evento de válvula (mención): {$itemsConEventos}");
        $this->info("Filas nuevas insertadas: {$insertados}");
        $this->info("Filas ya existentes (re-corrida idempotente): {$yaExistian}");

        if ($sinCategoria !== []) {
            $this->warn('Términos sin categoría resoluble (saltados, no inventados):');
            foreach ($sinCategoria as $termino => $n) {
                $this->line("  - \"{$termino}\": {$n} evento(s)");
            }
        }

        return self::SUCCESS;
    }
}
