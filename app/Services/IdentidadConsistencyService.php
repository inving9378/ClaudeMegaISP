<?php

namespace App\Services;

use App\Modules\Addons\Talento\Models\TalentoColaborador;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3c de #9990778 (Identidad unificada) — punto único de verdad para medir
 * qué tan consistente está el bridge `seller_id` (=users.id) → `colaborador_id`
 * en las tablas que ya lo pueblan (ver BackfillColaboradorIdBridgeCommand, Fase 2).
 *
 * Usado por `identidad:verificar-consistencia` (reporte en consola) y por el KPI
 * card del dashboard admin (mismo cálculo, sin duplicar queries).
 */
class IdentidadConsistencyService
{
    /** @var array<int,string> */
    public const TABLAS = ['client_main_information', 'whatsapp_conversations'];

    /**
     * @return array<string,array{
     *   total:int, con_seller_id:int, poblado:int, resoluble_total:int,
     *   cobertura_pct:?float, drift:int, huerfanas:int, huerfanas_resolubles:int,
     *   huerfanas_sin_match: array<int,int>
     * }>
     */
    public function resumen(): array
    {
        $userIdsConColaborador = TalentoColaborador::pluck('user_id')->all();

        $resultado = [];
        foreach (self::TABLAS as $tabla) {
            $resultado[$tabla] = $this->resumenTabla($tabla, $userIdsConColaborador);
        }

        return $resultado;
    }

    private function resumenTabla(string $tabla, array $userIdsConColaborador): array
    {
        $total = DB::table($tabla)->count();
        $conSellerId = DB::table($tabla)->whereNotNull('seller_id')->count();
        $poblado = DB::table($tabla)->whereNotNull('colaborador_id')->count();

        $resolubleTotal = DB::table($tabla)->whereIn('seller_id', $userIdsConColaborador)->count();

        $huerfanasQuery = DB::table($tabla)->whereNotNull('seller_id')->whereNull('colaborador_id');
        $huerfanas = (clone $huerfanasQuery)->count();
        $huerfanasResolubles = (clone $huerfanasQuery)->whereIn('seller_id', $userIdsConColaborador)->count();

        $huerfanasSinMatch = (clone $huerfanasQuery)
            ->whereNotIn('seller_id', $userIdsConColaborador)
            ->select('seller_id', DB::raw('count(*) as total'))
            ->groupBy('seller_id')
            ->orderByDesc('total')
            ->pluck('total', 'seller_id')
            ->all();

        // Drift: colaborador_id poblado que YA NO corresponde al mapeo actual
        // (seller_id sin match hoy, o apuntando a un colaborador distinto).
        $drift = DB::table("{$tabla} as t")
            ->leftJoin('talento_colaboradores as tc', 'tc.user_id', '=', 't.seller_id')
            ->whereNotNull('t.colaborador_id')
            ->where(function ($q) {
                $q->whereNull('tc.id')->orWhereColumn('tc.id', '!=', 't.colaborador_id');
            })
            ->count();

        $coberturaPct = $resolubleTotal > 0
            ? round($poblado / $resolubleTotal * 100, 2)
            : null;

        return [
            'total' => $total,
            'con_seller_id' => $conSellerId,
            'poblado' => $poblado,
            'resoluble_total' => $resolubleTotal,
            'cobertura_pct' => $coberturaPct,
            'drift' => $drift,
            'huerfanas' => $huerfanas,
            'huerfanas_resolubles' => $huerfanasResolubles,
            'huerfanas_sin_match' => $huerfanasSinMatch,
        ];
    }
}
