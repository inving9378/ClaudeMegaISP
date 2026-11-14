<?php

namespace App\Modules\Addons\Marketing\Services\Dashboard;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cálculos del Dashboard de Marketing (Fase 1, §2.4 de docs/marketing/fase-1-paquetes.md).
 *
 * - Todo se calcula para UNA empresa (la activa; la pasa el controlador). Además del scope de empresa de
 *   los modelos se filtra explícito por `company_id`, y las filas con borrado lógico no cuentan.
 * - Dinero: cadenas con 2 decimales, aritmética con bcmath (nunca float). Redondeo half-up (alejándose
 *   de cero en negativos) a 2 decimales para dinero y a 1 decimal para porcentajes.
 * - Un indicador sin dato es null (en pantalla «—»), nunca un 0 inventado.
 */
class DashboardMarketingService
{
    public const ZONA = 'America/Mexico_City';

    public const MONEDA = 'MXN';

    /** Estados de campaña cuyo gasto cuenta para el mes (§2.4: activas, pausadas y terminadas). */
    private const ESTADOS_CON_GASTO = ['active', 'paused', 'finished'];

    /** Etapas del embudo, siempre las 5 y en este orden. */
    private const ETAPAS = [
        'new'         => 'Nuevo',
        'contacted'   => 'Contactado',
        'qualified'   => 'Calificado',
        'negotiating' => 'En negociación',
        'won'         => 'Ganado',
    ];

    public const TIPOS = [
        'meta_ads'    => 'Meta Ads',
        'google_ads'  => 'Google Ads',
        'organic'     => 'Orgánica',
        'email_blast' => 'Correo masivo',
        'sms_blast'   => 'SMS masivo',
        'voice_blast' => 'Llamadas',
        'mixed'       => 'Mixta',
        'manual'      => 'Manual',
    ];

    // ── Resumen ───────────────────────────────────────────────────────────────

    /**
     * @param  string  $mes  AAAA-MM ya validado
     */
    public function resumen(int $empresaId, string $mes): array
    {
        $desde = CarbonImmutable::createFromFormat('Y-m-d H:i:s', "{$mes}-01 00:00:00", self::ZONA);
        $hasta = $desde->endOfMonth()->startOfSecond();
        $desdeAnterior = $desde->subMonthNoOverflow();
        $hastaAnterior = $desdeAnterior->endOfMonth()->startOfSecond();

        $estados = $this->leadsPorEstado($empresaId, $desde, $hasta);
        $estadosAnterior = $this->leadsPorEstado($empresaId, $desdeAnterior, $hastaAnterior);

        $leads = array_sum($estados);
        $leadsAnterior = array_sum($estadosAnterior);
        $ganados = $estados['won'] ?? 0;
        $ganadosAnterior = $estadosAnterior['won'] ?? 0;

        $conversion = $leads > 0 ? $this->porcentaje((string) $ganados, (string) $leads) : null;
        $conversionAnterior = $leadsAnterior > 0 ? $this->porcentaje((string) $ganadosAnterior, (string) $leadsAnterior) : null;

        $gasto = $this->gasto($empresaId, $desde, $hasta);
        $gastoAnterior = $this->gasto($empresaId, $desdeAnterior, $hastaAnterior);

        $costo = $this->costoPorLead($gasto['valor'], $leads);
        $costoAnterior = $this->costoPorLead($gastoAnterior['valor'], $leadsAnterior);

        $etapas = [];
        foreach (self::ETAPAS as $clave => $etiqueta) {
            $etapas[] = ['clave' => $clave, 'etiqueta' => $etiqueta, 'total' => $estados[$clave] ?? 0];
        }

        return [
            'ok'         => true,
            'empresa_id' => $empresaId,
            'periodo'    => [
                'mes'          => $mes,
                'desde'        => $desde->toIso8601String(),
                'hasta'        => $hasta->toIso8601String(),
                'mes_anterior' => $desdeAnterior->format('Y-m'),
            ],
            'kpis' => [
                'leads_mes' => [
                    'valor'     => $leads,
                    'anterior'  => $leadsAnterior,
                    'variacion' => $this->numero($this->variacion((string) $leads, (string) $leadsAnterior)),
                ],
                'conversion' => [
                    'valor'     => $this->numero($conversion),
                    'anterior'  => $this->numero($conversionAnterior),
                    'variacion' => $this->numero($this->variacion($conversion, $conversionAnterior)),
                    'ganados'   => $ganados,
                ],
                'gasto_mxn' => [
                    'valor'                => $gasto['valor'],
                    'anterior'             => $gastoAnterior['valor'],
                    'variacion'            => $this->numero($this->variacion($gasto['valor'], $gastoAnterior['valor'])),
                    // Mientras no exista la lectura de Meta (Fase 5) el gasto sale de las campañas (F1-5).
                    'fuente'               => 'campanas',
                    'campanas_otra_moneda' => $gasto['otra_moneda'],
                ],
                'costo_por_lead_mxn' => [
                    'valor'     => $costo,
                    'anterior'  => $costoAnterior,
                    'variacion' => $this->numero($this->variacion($costo, $costoAnterior)),
                ],
            ],
            'embudo' => [
                'etapas'     => $etapas,
                'perdidos'   => $estados['lost'] ?? 0,
                'archivados' => $estados['archived'] ?? 0,
                'total'      => $leads,
            ],
            'generado_at' => CarbonImmutable::now(self::ZONA)->toIso8601String(),
        ];
    }

    // ── Campañas activas ─────────────────────────────────────────────────────

    public function campanasActivas(int $empresaId, int $limite): array
    {
        $base = Campaign::query()
            ->where('company_id', $empresaId)
            ->where('status', 'active');

        $total = (clone $base)->count();

        $campanas = (clone $base)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();

        $leadsPorCampana = $campanas->isEmpty() ? collect() : DB::table('marketing_attributions as a')
            ->join('marketing_leads as l', 'l.id', '=', 'a.lead_id')
            ->whereIn('a.campaign_id', $campanas->pluck('id')->all())
            ->where('l.company_id', $empresaId)
            ->whereNull('l.deleted_at')
            ->groupBy('a.campaign_id')
            ->selectRaw('a.campaign_id, COUNT(DISTINCT a.lead_id) AS total')
            ->pluck('total', 'campaign_id');

        $data = $campanas->map(function (Campaign $c) use ($leadsPorCampana) {
            $presupuesto = $this->dinero($c->getRawOriginal('budget'));
            $gastado = $this->dinero($c->getRawOriginal('spent'));
            $moneda = strtoupper(trim((string) $c->currency));

            return [
                'id'                 => (int) $c->id,
                'nombre'             => $c->name,
                'tipo'               => $c->type,
                'tipo_etiqueta'      => self::TIPOS[$c->type] ?? $c->type,
                'estado'             => $c->status,
                'moneda'             => $moneda,
                'moneda_distinta'    => $moneda !== self::MONEDA,
                'presupuesto'        => $presupuesto,
                'gastado'            => $gastado,
                'avance_presupuesto' => bccomp($presupuesto, '0', 2) === 0
                    ? null
                    : $this->numero($this->porcentaje($gastado, $presupuesto)),
                'inicio'             => $c->start_date?->format('Y-m-d'),
                'fin'                => $c->end_date?->format('Y-m-d'),
                'leads'              => (int) ($leadsPorCampana[$c->id] ?? 0),
            ];
        })->values()->all();

        return ['ok' => true, 'total' => $total, 'data' => $data];
    }

    // ── Piezas ───────────────────────────────────────────────────────────────

    /** @return array<string, int> conteo de leads del periodo por estado */
    private function leadsPorEstado(int $empresaId, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return Lead::query()
            ->where('company_id', $empresaId)
            ->whereBetween('captured_at', [$desde->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s')])
            ->toBase()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Gasto MXN del periodo: suma de `spent` de las campañas activas/pausadas/terminadas cuya vigencia toca
     * el periodo. Las de otra moneda no se suman (se cuentan). Sin ninguna campaña MXN que califique → null.
     *
     * @return array{valor: ?string, otra_moneda: int}
     */
    private function gasto(int $empresaId, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = Campaign::query()
            ->where('company_id', $empresaId)
            ->whereIn('status', self::ESTADOS_CON_GASTO)
            ->where('start_date', '<=', $hasta->format('Y-m-d'))
            ->where(function ($q) use ($desde) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $desde->format('Y-m-d'));
            })
            ->toBase()
            ->get(['currency', 'spent']);

        $suma = null;
        $otraMoneda = 0;

        foreach ($filas as $fila) {
            if (strtoupper(trim((string) $fila->currency)) !== self::MONEDA) {
                $otraMoneda++;
                continue;
            }
            $suma = bcadd($suma ?? '0', $this->dinero($fila->spent), 2);
        }

        return ['valor' => $suma, 'otra_moneda' => $otraMoneda];
    }

    private function costoPorLead(?string $gasto, int $leads): ?string
    {
        if ($gasto === null || $leads === 0 || bccomp($gasto, '0', 2) === 0) {
            return null;
        }

        return $this->redondear(bcdiv($gasto, (string) $leads, 6), 2);
    }

    /** (actual − anterior) / anterior × 100, 1 decimal; null si falta alguno o el anterior es 0. */
    private function variacion(?string $actual, ?string $anterior): ?string
    {
        if ($actual === null || $anterior === null || bccomp($anterior, '0', 6) === 0) {
            return null;
        }

        return $this->redondear(bcdiv(bcmul(bcsub($actual, $anterior, 6), '100', 6), $anterior, 6), 1);
    }

    /** parte / total × 100, 1 decimal (cadena). */
    private function porcentaje(string $parte, string $total): string
    {
        return $this->redondear(bcdiv(bcmul($parte, '100', 6), $total, 6), 1);
    }

    /** Redondeo half-up (alejándose de cero en negativos) a $decimales; la entrada trae más decimales. */
    private function redondear(string $valor, int $decimales): string
    {
        $medio = '0.' . str_repeat('0', $decimales) . '5';

        $resultado = str_starts_with($valor, '-')
            ? bcsub($valor, $medio, $decimales)
            : bcadd($valor, $medio, $decimales);

        // bcmath puede dejar «-0.0»; se normaliza a «0.0».
        return bccomp($resultado, '0', $decimales) === 0 ? bcadd('0', '0', $decimales) : $resultado;
    }

    /** Normaliza un DECIMAL de la base a cadena con 2 decimales (sin pasar por float). */
    private function dinero(mixed $valor): string
    {
        $texto = trim((string) ($valor ?? '0'));

        return preg_match('/^-?\d+(\.\d+)?\z/', $texto) ? bcadd($texto, '0', 2) : '0.00';
    }

    /**
     * Porcentajes y variaciones salen como número JSON con 1 decimal (no son dinero). La conversión a
     * número se hace solo al final, desde la cadena ya redondeada.
     */
    private function numero(?string $valor): ?float
    {
        return $valor === null ? null : (float) $valor;
    }
}
