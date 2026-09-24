<?php

namespace App\Modules\Addons\VoIP\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MegaVoz Fase 5 — KPIs de la cola, calculados sobre `voip_queue_log`
 * (la copia importada del `queue_log` nativo de Asterisk).
 *
 * Vocabulario de eventos y campos (Asterisk app_queue, no inventado aquí):
 *   ENTERQUEUE                          → entra una llamada a la cola
 *   CONNECT   |holdtime|bridged|ring_ms → se conectó con un agente (data1=espera en seg)
 *   COMPLETEAGENT  |holdtime|talktime   → terminó, colgó el agente (data2=plática en seg)
 *   COMPLETECALLER |holdtime|talktime   → terminó, colgó quien llamaba (data2=plática en seg)
 *   ABANDON   |pos|origpos|waittime     → colgó sin que lo atendieran (data3=espera en seg)
 *
 * "Llamadas al respaldo UCM" (KPI del plan original) NO aplica con la regla
 * de oro revisada — el asistente siempre contesta primero dentro de esta
 * misma cola, no hay un salto a un UCM externo que medir.
 */
class MegaVozKpiService
{
    public function resumen(string $queuename, Carbon $desde, Carbon $hasta): array
    {
        $base = fn () => DB::table('voip_queue_log')
            ->where('queuename', $queuename)
            ->whereBetween('ts', [$desde, $hasta]);

        $entradas    = (clone $base())->where('event', 'ENTERQUEUE')->count();
        $atendidas   = (clone $base())->where('event', 'CONNECT')->count();
        $abandonadas = (clone $base())->where('event', 'ABANDON')->count();

        $esperaConectadas = (clone $base())->where('event', 'CONNECT')
            ->selectRaw('AVG(CAST(data1 AS UNSIGNED)) as v')->value('v');
        $esperaAbandonadas = (clone $base())->where('event', 'ABANDON')
            ->selectRaw('AVG(CAST(data3 AS UNSIGNED)) as v')->value('v');

        $duracionPromedio = (clone $base())->whereIn('event', ['COMPLETEAGENT', 'COMPLETECALLER'])
            ->selectRaw('AVG(CAST(data2 AS UNSIGNED)) as v')->value('v');

        return [
            'entradas'                  => $entradas,
            'atendidas'                 => $atendidas,
            'abandonadas'               => $abandonadas,
            'porcentaje_abandono'       => $entradas > 0 ? round($abandonadas / $entradas * 100, 1) : 0,
            'espera_promedio_seg'       => $this->promedioPonderado($esperaConectadas, $atendidas, $esperaAbandonadas, $abandonadas),
            'duracion_promedio_seg'     => $duracionPromedio !== null ? round((float) $duracionPromedio) : null,
        ];
    }

    public function porAgente(string $queuename, Carbon $desde, Carbon $hasta): array
    {
        $atendidas = DB::table('voip_queue_log')
            ->where('queuename', $queuename)
            ->where('event', 'CONNECT')
            ->whereBetween('ts', [$desde, $hasta])
            ->whereNotNull('agent')
            ->select('agent')
            ->selectRaw('COUNT(*) as llamadas')
            ->groupBy('agent')
            ->get()
            ->keyBy('agent');

        $tiempos = DB::table('voip_queue_log')
            ->where('queuename', $queuename)
            ->whereIn('event', ['COMPLETEAGENT', 'COMPLETECALLER'])
            ->whereBetween('ts', [$desde, $hasta])
            ->whereNotNull('agent')
            ->select('agent')
            ->selectRaw('SUM(CAST(data2 AS UNSIGNED)) as segundos_total')
            ->groupBy('agent')
            ->get()
            ->keyBy('agent');

        $agentes = $atendidas->keys()->merge($tiempos->keys())->unique();

        return $agentes->map(function ($agente) use ($atendidas, $tiempos) {
            $llamadas = (int) ($atendidas[$agente]->llamadas ?? 0);
            $segundos = (int) ($tiempos[$agente]->segundos_total ?? 0);
            return [
                'agent'                => $agente,
                'llamadas_atendidas'   => $llamadas,
                'segundos_total'       => $segundos,
                'segundos_promedio'    => $llamadas > 0 ? round($segundos / $llamadas) : 0,
            ];
        })->sortByDesc('llamadas_atendidas')->values()->all();
    }

    private function promedioPonderado(?float $a, int $pesoA, ?float $b, int $pesoB): ?int
    {
        $totalPeso = $pesoA + $pesoB;
        if ($totalPeso === 0) {
            return null;
        }
        $suma = ($a ?? 0) * $pesoA + ($b ?? 0) * $pesoB;
        return (int) round($suma / $totalPeso);
    }
}
