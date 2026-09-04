<?php

namespace App\Console\Commands\Active;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnóstico read-only de paridad entre `invoices` (moderna) y `client_invoices` (legacy).
 * Item #723 (Fase 4 de la unificación #632), q3 respondida por Irving: comando de diff antes de
 * mover ningún lector. No aplica ningún cambio — solo reporta.
 *
 * `client_invoices.payment_date` es varchar `d/m/Y` (no se puede whereYear/whereMonth directo,
 * ver docs/deuda-facturas-invoices-item-159-plan.md), así que el filtro por mes se hace con LIKE
 * sobre el sufijo `/mm/aaaa`.
 */
class FinanzasParidadInvoicesCommand extends Command
{
    protected $signature = 'finanzas:paridad-invoices
                            {--meses=1 : Cuántos meses hacia atrás comparar, incluyendo el actual}';

    protected $description = 'Compara invoices vs client_invoices (montos/conteos pagados por mes) — solo lectura, sin aplicar cambios';

    private const ESTADOS_PAGADO_LEGACY = ['Pagado', 'Pagar (del saldo de la cuenta)'];

    public function handle(): int
    {
        $meses = max(1, (int) $this->option('meses'));

        $rows = [];
        $huboDivergencia = false;

        for ($i = 0; $i < $meses; $i++) {
            $periodo = Carbon::now()->subMonthsNoOverflow($i);
            $anio    = $periodo->year;
            $mes     = $periodo->month;
            $label   = ucfirst($periodo->locale('es')->translatedFormat('F Y'));

            $invTotal = (float) DB::table('invoices')
                ->where('status', 'paid')
                ->whereYear('payment_date', $anio)
                ->whereMonth('payment_date', $mes)
                ->whereNull('deleted_at')
                ->sum('total');
            $invCount = (int) DB::table('invoices')
                ->where('status', 'paid')
                ->whereYear('payment_date', $anio)
                ->whereMonth('payment_date', $mes)
                ->whereNull('deleted_at')
                ->count();

            $patronMesAnio = sprintf('%%/%02d/%d', $mes, $anio);
            $ciTotal = (float) DB::table('client_invoices')
                ->whereIn('estado', self::ESTADOS_PAGADO_LEGACY)
                ->where('payment_date', 'like', $patronMesAnio)
                ->sum('total');
            $ciCount = (int) DB::table('client_invoices')
                ->whereIn('estado', self::ESTADOS_PAGADO_LEGACY)
                ->where('payment_date', 'like', $patronMesAnio)
                ->count();

            $diffTotal = $invTotal - $ciTotal;
            $coincide  = abs($diffTotal) < 0.01;
            if (! $coincide) {
                $huboDivergencia = true;
            }

            $rows[] = [
                $label,
                number_format($invTotal, 2),
                $invCount,
                number_format($ciTotal, 2),
                $ciCount,
                number_format($diffTotal, 2),
                $coincide ? 'OK' : 'DIVERGE',
            ];
        }

        $this->table(
            ['Periodo', 'invoices $', 'invoices #', 'client_invoices $', 'client_invoices #', 'Diferencia $', 'Estado'],
            $rows
        );

        if ($huboDivergencia) {
            $this->newLine();
            $this->warn('Hay divergencia entre invoices y client_invoices en al menos un período.');
            $this->line('Es lo esperado hoy: PaymentApplicationService::applyPayment (motor central de pagos —');
            $this->line('mostrador, WhatsApp F4, webhooks SPEI/OpenPay) solo escribe en client_invoices, nunca');
            $this->line('en invoices. Sin las Fases 1-3 de #632 (#719/#721/#722) en main, invoices no es un');
            $this->line('espejo confiable — no usarla como fuente primaria de lectores financieros todavía.');

            return self::FAILURE;
        }

        $this->info('Sin divergencia detectada en el rango comparado.');

        return self::SUCCESS;
    }
}
