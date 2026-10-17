<?php

namespace Tests\Feature\Facturacion;

use App\Models\Invoice;
use App\Services\Finance\Billing\PrefacturaPdfService;

/** PDF de la plantilla simulado: registra las llamadas y puede fallar para ciertas facturas. */
class PrefacturaPdfServiceFalso extends PrefacturaPdfService
{
    public array $llamadas = [];

    public array $fallaPara = [];

    public function __construct()
    {
    }

    public function generar(Invoice $invoice, ?string $estado = null): array
    {
        // Como el servicio real: sin estado explícito, se calcula de la factura.
        $estado ??= \App\Services\Finance\Billing\PrefacturaDataProvider::estadoDe($invoice);
        $this->llamadas[] = [$invoice->id, $estado];
        if (in_array($invoice->id, $this->fallaPara, true)) {
            return ['status' => 'fail', 'keys' => ['data.simulada']];
        }

        return [
            'status'   => 'ok',
            'pdf'      => '%PDF-1.4 sintético',
            'filename' => ($estado === 'pagado' ? 'Factura-' : 'Prefactura-') . $invoice->number . '.pdf',
            'estado'   => $estado,
        ];
    }
}
