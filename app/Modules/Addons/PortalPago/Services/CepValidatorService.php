<?php

namespace App\Modules\Addons\PortalPago\Services;

use App\Models\ClientInvoice;
use App\Modules\Addons\PortalPago\Models\PortalPagoAccount;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentLink;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentReport;
use App\Modules\Addons\PortalPago\Services\Cep\BanxicoCepDriver;
use App\Modules\Addons\PortalPago\Services\Cep\CepConciliationException;
use App\Modules\Addons\PortalPago\Services\Cep\CepQuery;
use App\Modules\Addons\PortalPago\Services\Cep\CepValidationResult;
use App\Modules\Addons\PortalPago\Services\Cep\CepValidatorDriver;
use App\Modules\Addons\PortalPago\Services\Cep\ManualCepDriver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orquesta la validación CEP de un reporte de pago.
 *
 *   1. Guards de conciliación (idempotencia por clave + factura ya pagada).
 *   2. Selección de driver según PAGOS_CEP_MODE (banxico|manual|hybrid).
 *   3. Match antifraude de 3 condiciones para marcar validado.
 *   4. Persiste el resultado en el reporte (estado + cep_*).
 *
 * NO mueve dinero. La conciliación efectiva (crear Payment → cadena observer de
 * reactivación + InvoicePaid) se dispara en el flujo del portal (Paso 3) cuando
 * este servicio devuelve un resultado validado.
 */
class CepValidatorService
{
    /**
     * Verifica el reporte: corre guards, valida contra el driver y persiste.
     * Devuelve el resultado final (validated|discrepancy|inconclusive).
     *
     * @throws CepConciliationException si un guard de negocio lo impide.
     */
    public function validateReport(PortalPagoPaymentReport $report): CepValidationResult
    {
        $link    = $report->paymentLink()->firstOrFail();
        $account = $link->account()->firstOrFail();

        // 1. Guards ANTES de cualquier conciliación.
        $this->assertConciliable($report, $link);

        // 2. Driver según modo.
        $driver = $this->driverForMode();
        $query  = $this->buildQuery($report, $link, $account);

        $result = $driver->validate($query);

        // 3. Si el driver encontró un CEP liquidado, aplicar match antifraude.
        if ($result->isFound()) {
            $result = $this->decide($link, $account, $result);
        }

        // 4. Persistir en el reporte.
        $this->persist($report, $result, $driver->name());

        return $result;
    }

    /**
     * Guards de conciliación. Lanza CepConciliationException si el reporte no
     * debe conciliarse. Un reporte rechazado/discrepancia SÍ puede reintentarse
     * con la misma clave (por eso clave_rastreo no es UNIQUE en BD).
     */
    public function assertConciliable(PortalPagoPaymentReport $report, ?PortalPagoPaymentLink $link = null): void
    {
        $link ??= $report->paymentLink()->firstOrFail();

        // Liga ya conciliada.
        if ($link->estado === PortalPagoPaymentLink::ESTADO_CONCILIADO) {
            throw new CepConciliationException('Esta liga de pago ya fue conciliada.');
        }

        // Factura legacy ya pagada (estado deja de empezar con "Pagar").
        $invoice = ClientInvoice::find($link->document_id);
        if ($invoice && ! Str::startsWith((string) $invoice->estado, 'Pagar')) {
            throw new CepConciliationException('La factura ya está pagada; no se puede conciliar de nuevo.');
        }

        // Idempotencia estricta: misma clave de rastreo ya VALIDADA para esta liga.
        $yaValidada = PortalPagoPaymentReport::query()
            ->where('payment_link_id', $link->id)
            ->where('clave_rastreo', $report->clave_rastreo)
            ->where('estado', PortalPagoPaymentReport::ESTADO_VALIDADO)
            ->where('id', '!=', $report->id)
            ->exists();

        if ($yaValidada) {
            throw new CepConciliationException('Esta clave de rastreo ya fue conciliada.');
        }
    }

    /**
     * Match antifraude: solo VALIDADO si las 3 condiciones cuadran.
     */
    private function decide(PortalPagoPaymentLink $link, PortalPagoAccount $account, CepValidationResult $found): CepValidationResult
    {
        $montoOk = $found->cepMonto !== null
            && $this->montosIguales($found->cepMonto, (string) $link->monto_esperado);

        // La CLABE del CEP debe pertenecer a una de NUESTRAS cuentas activas y
        // ser exactamente la cuenta de esta liga.
        $clabeOk = $found->cepClabeBeneficiaria !== null
            && $found->cepClabeBeneficiaria === $account->clabe
            && $account->activa
            && PortalPagoAccount::activas()->where('clabe', $found->cepClabeBeneficiaria)->exists();

        $estadoOk = strtoupper(trim((string) $found->cepEstado)) === 'LIQUIDADO';

        if ($montoOk && $clabeOk && $estadoOk) {
            return CepValidationResult::validated($found);
        }

        $motivos = [];
        if (! $montoOk)  { $motivos[] = 'monto'; }
        if (! $clabeOk)  { $motivos[] = 'CLABE beneficiaria'; }
        if (! $estadoOk) { $motivos[] = 'estado'; }

        return CepValidationResult::discrepancy(
            $found,
            'CEP encontrado pero no cuadra en: ' . implode(', ', $motivos) . '.'
        );
    }

    /**
     * Mapea el resultado a estado del reporte y persiste los campos CEP.
     */
    private function persist(PortalPagoPaymentReport $report, CepValidationResult $result, string $driverName): void
    {
        $estado = match (true) {
            $result->isValidated()   => PortalPagoPaymentReport::ESTADO_VALIDADO,
            $result->isDiscrepancy() => PortalPagoPaymentReport::ESTADO_DISCREPANCIA,
            $result->isNotFound()    => PortalPagoPaymentReport::ESTADO_DISCREPANCIA,
            default                  => PortalPagoPaymentReport::ESTADO_PENDIENTE, // inconclusive
        };

        $raw = $result->toArray();
        $raw['driver'] = $driverName;

        $report->update([
            'cep_validado'  => $result->isValidated(),
            'cep_resultado' => $raw,
            'estado'        => $estado,
        ]);

        Log::info('PortalPago CEP: reporte validado', [
            'report_id'     => $report->id,
            'payment_link'  => $report->payment_link_id,
            'driver'        => $driverName,
            'result_state'  => $result->state,
            'report_estado' => $estado,
        ]);
    }

    /**
     * Driver según PAGOS_CEP_MODE. 'hybrid' usa Banxico: si resulta inconcluso,
     * el propio inconclusive ya enruta a revisión manual (pendiente_validacion).
     */
    public function driverForMode(?string $mode = null): CepValidatorDriver
    {
        $mode ??= (string) config('pagos.cep_mode', 'hybrid');

        return match ($mode) {
            'manual' => new ManualCepDriver(),
            default  => new BanxicoCepDriver(), // 'banxico' y 'hybrid'
        };
    }

    private function buildQuery(PortalPagoPaymentReport $report, PortalPagoPaymentLink $link, PortalPagoAccount $account): CepQuery
    {
        $fecha = $report->fecha_operacion
            ? $report->fecha_operacion->format('d/m/Y')
            : '';

        return new CepQuery(
            fecha: $fecha,
            claveRastreo: (string) $report->clave_rastreo,
            // TODO catálogo banco→clave Banxico (ver Hoja de Ruta). Hoy se envía
            // tal cual; si no es la clave correcta, Banxico no encuentra → discrepancia.
            bancoEmisor: (string) ($report->banco_emisor ?? ''),
            bancoReceptor: (string) ($account->banco ?? ''),
            cuentaBeneficiaria: (string) $account->clabe,
            monto: number_format((float) $link->monto_esperado, 2, '.', '')
        );
    }

    /** Comparación de montos como decimal (no float). */
    private function montosIguales(string $a, string $b): bool
    {
        $a = number_format((float) $a, 2, '.', '');
        $b = number_format((float) $b, 2, '.', '');

        if (function_exists('bccomp')) {
            return bccomp($a, $b, 2) === 0;
        }
        return $a === $b;
    }
}
