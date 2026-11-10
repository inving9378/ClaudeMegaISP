<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoCajaInspection;
use Illuminate\Support\Facades\DB;

/**
 * MR-34 regla 2 (item #1296) — SOLO SIMULACIÓN. Nunca escribe en TalentoLedgerEntry.
 * Señal: la última inspección validada por supervisor de cada caja en el periodo
 * (talento_caja_inspections.power_measured). Idempotente por (regla, caja, periodo).
 */
class CajaNivel2SimulacionService
{
    public const REGLA = 'caja_nivel2';
    public const TABLA = 'talento_caja_nivel2_simulaciones';

    public function habilitada(): bool
    {
        return (bool) config('talento.caja_nivel2.enabled', false);
    }

    public function califica(float $dbm): bool
    {
        $u = (float) config('talento.caja_nivel2.umbral_dbm', -23.0);
        return config('talento.caja_nivel2.estricto', true) ? $dbm > $u : $dbm >= $u;
    }

    /** @param array{period_start:string,period_end:string} $week
     *  @return array{evaluadas:int,nuevas:int,ya_existian:int,monto_total:float} */
    public function simular(array $week, bool $forzar = false): array
    {
        $r = ['evaluadas' => 0, 'nuevas' => 0, 'ya_existian' => 0, 'monto_total' => 0.0];
        if (!$forzar && !$this->habilitada()) {
            return $r;
        }

        $ini = substr((string) $week['period_start'], 0, 10);
        $fin = substr((string) $week['period_end'], 0, 10);
        $monto = (float) config('talento.caja_nivel2.monto', 30);

        $insp = TalentoCajaInspection::whereNotNull('power_measured')
            ->where('supervisor_validated', true)
            ->whereBetween('created_at', [$week['period_start'], $week['period_end']])
            ->orderBy('created_at')->orderBy('id')
            ->get()->groupBy('caja_ref')->map->last();

        foreach ($insp as $i) {
            $r['evaluadas']++;
            if (!$this->califica((float) $i->power_measured)) {
                continue;
            }
            $existe = DB::table(self::TABLA)->where([
                'regla' => self::REGLA, 'caja_ref' => $i->caja_ref, 'periodo_inicio' => $ini,
            ])->exists();
            if ($existe) {
                $r['ya_existian']++;
                continue;
            }
            DB::table(self::TABLA)->insert([
                'regla' => self::REGLA, 'caja_ref' => $i->caja_ref,
                'periodo_inicio' => $ini, 'periodo_fin' => $fin,
                'inspection_id' => $i->id, 'colaborador_id' => $i->inspected_by,
                'power_dbm' => $i->power_measured,
                'umbral_dbm' => config('talento.caja_nivel2.umbral_dbm', -23.0),
                'estricto' => (bool) config('talento.caja_nivel2.estricto', true),
                'monto_simulado' => $monto, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $r['nuevas']++;
            $r['monto_total'] += $monto;
        }
        return $r;
    }

    /** Reporte "qué habría pagado" de un periodo. */
    public function reporte(string $periodoInicio)
    {
        return DB::table(self::TABLA)->where('regla', self::REGLA)
            ->where('periodo_inicio', $periodoInicio)->orderBy('caja_ref')->get();
    }
}
