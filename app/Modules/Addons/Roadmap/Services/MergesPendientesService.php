<?php

namespace App\Modules\Addons\Roadmap\Services;

use Illuminate\Support\Facades\DB;

/**
 * «Esperan tu merge» en UNA sola regla: items abiertos y no archivados que esperan el merge de
 * Irving y todavía no tienen merge. Ordenados por cuántos items frenados libera cada uno (los que
 * los tienen en `depende_de`). La usan la tarjeta de la Panorama (en vivo), la foto diaria de
 * `circuito:digest` y Jarvis, para que los tres cuenten lo mismo (antes la tarjeta era la foto de
 * las 6:40 y Jarvis leía en vivo con otra consulta).
 */
class MergesPendientesService
{
    private const CERRADOS = ['completado', 'cancelado', 'rechazado'];

    /** @return array{total: int, liberan: int, items: array<int, array{id: int, title: string, desbloquea: int}>} */
    public function listar(?int $tope = null): array
    {
        $desbloquea = [];
        $deps = DB::table('roadmap_items')->whereNotIn('estado_aprobacion', self::CERRADOS)
            ->whereNotNull('depende_de')->pluck('depende_de');
        foreach ($deps as $json) {
            $lista = json_decode((string) $json, true);
            foreach (is_array($lista) ? $lista : [] as $d) {
                $desbloquea[(int) $d] = ($desbloquea[(int) $d] ?? 0) + 1;
            }
        }

        $merges = DB::table('roadmap_items')->whereNotIn('estado_aprobacion', self::CERRADOS)
            ->whereNull('archivado_at')->where('esperando_merge_irving', true)->whereNull('merge_commit')
            ->get(['id', 'title'])
            ->map(fn ($i) => ['id' => (int) $i->id, 'title' => mb_substr((string) $i->title, 0, 60),
                'desbloquea' => $desbloquea[$i->id] ?? 0])
            ->sortBy([['desbloquea', 'desc'], ['id', 'asc']])->values();

        return [
            'total'   => $merges->count(),
            'liberan' => (int) $merges->sum('desbloquea'),
            'items'   => ($tope ? $merges->take($tope) : $merges)->all(),
        ];
    }
}
