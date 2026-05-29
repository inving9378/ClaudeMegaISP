<?php

namespace App\Modules\Addons\CobranzaBlaster\Services;

use App\Modules\Addons\CobranzaBlaster\Jobs\BlastCampanaJob;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CobranzaCampanaService
{
    public function cargarMorosos(CobranzaCampana $campana): int
    {
        $morosos = DB::table('clients as c')
            ->join('client_main_information as cmi', 'cmi.client_id', '=', 'c.id')
            ->join('invoices as i', 'i.client_id', '=', 'c.id')
            ->where('i.due_date', '<', now())
            ->where('i.pending_balance', '>', 0)
            ->whereNotIn('i.status', ['paid', 'cancelled'])
            ->where('c.status', 'active')
            ->whereNotNull('cmi.phone')
            ->whereNotExists(function ($q) use ($campana) {
                $q->from('cobranza_llamadas')
                    ->whereColumn('cobranza_llamadas.client_id', 'c.id')
                    ->where('cobranza_llamadas.campana_id', $campana->id)
                    ->whereNotIn('cobranza_llamadas.estado', ['pagada', 'excluida']);
            })
            ->select(
                'c.id as client_id',
                'cmi.phone',
                'cmi.name',
                DB::raw('SUM(i.pending_balance) as monto_vencido')
            )
            ->groupBy('c.id', 'cmi.phone', 'cmi.name')
            ->get();

        $insertados = 0;

        foreach ($morosos as $moroso) {
            CobranzaLlamada::create([
                'campana_id'      => $campana->id,
                'client_id'       => $moroso->client_id,
                'telefono'        => $moroso->phone,
                'monto_vencido'   => $moroso->monto_vencido,
                'estado'          => 'pendiente',
                'proximo_intento_at' => null,
            ]);
            $insertados++;
        }

        Log::info("CobranzaCampanaService: {$insertados} morosos cargados en campaña #{$campana->id}");

        return $insertados;
    }

    public function activarCampana(CobranzaCampana $campana): void
    {
        $insertados = $this->cargarMorosos($campana);
        $campana->update(['estado' => 'activa']);
        BlastCampanaJob::dispatch($campana->id);

        Log::info("Campaña #{$campana->id} activada con {$insertados} llamadas cargadas.");
    }

    public function pausarCampana(CobranzaCampana $campana): void
    {
        $campana->update(['estado' => 'pausada']);
    }

    public function completarCampana(CobranzaCampana $campana): void
    {
        $campana->update(['estado' => 'completada']);
    }

    public function estadisticas(CobranzaCampana $campana): array
    {
        $conteos = $campana->llamadas()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        $total = array_sum($conteos);

        return [
            'total'           => $total,
            'pendiente'       => $conteos['pendiente']   ?? 0,
            'marcando'        => $conteos['marcando']    ?? 0,
            'contestada'      => $conteos['contestada']  ?? 0,
            'no_contesto'     => $conteos['no_contesto'] ?? 0,
            'ocupado'         => $conteos['ocupado']     ?? 0,
            'fallida'         => $conteos['fallida']     ?? 0,
            'pagada'          => $conteos['pagada']      ?? 0,
            'excluida'        => $conteos['excluida']    ?? 0,
            'porcentaje_completado' => $campana->porcentaje_completado,
        ];
    }
}
