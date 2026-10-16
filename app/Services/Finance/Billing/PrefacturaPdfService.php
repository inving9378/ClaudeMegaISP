<?php

namespace App\Services\Finance\Billing;

use App\Models\Invoice;
use App\Modules\Core\Clientes\Services\ContractClientService;
use App\Services\DocumentTemplateService;
use App\Support\ContractPdfTemplate;

/**
 * PDF de la Prefactura de UNA factura con la plantilla «Prefactura.» + PrefacturaDataProvider
 * (item #480). Genera en memoria: no escribe archivos, no toca billing_notifications ni la
 * pausa de envíos. Documento informativo sin valor fiscal.
 */
class PrefacturaPdfService
{
    public function __construct(
        private PrefacturaDataProvider $provider,
        private DocumentTemplateService $plantillas,
    ) {
    }

    /**
     * HTML final, o la lista de variables que la plantilla pide y no se pudieron llenar.
     *
     * @return array{status: 'ok', html: string, estado: string}|array{status: 'fail', keys: string[]}
     */
    public function html(Invoice $invoice, ?string $estado = null): array
    {
        $html = (new PrefacturaPlantillaUpdater())->plantilla()?->html;
        if (! $html) {
            return ['status' => 'fail', 'keys' => ['No existe la plantilla «' . PrefacturaPlantillaUpdater::NOMBRE . '».']];
        }

        $client = $invoice->client;
        if (! $client) {
            return ['status' => 'fail', 'keys' => ['La factura no tiene un cliente válido.']];
        }

        $estado ??= PrefacturaDataProvider::estadoDe($invoice);
        $variables = $this->provider->paraFactura($invoice, $estado);
        $data = array_merge((new ContractClientService())->getDataClient($client), $variables['data']);

        $v = $this->plantillas->validateAndReplaceTemplate($html, $data, 'Client');
        if ($v['status'] === 'fail') {
            return ['status' => 'fail', 'keys' => array_values(array_unique($v['keys']))];
        }

        $html = str_replace(
            array_map(fn ($k) => '${' . $k . '}', array_keys($variables['dinamicas'])),
            array_values($variables['dinamicas']),
            $v['html']
        );

        // Lo que la plantilla pida y nadie haya llenado (p. ej. otra data_dinamic) se reporta en
        // vez de salir impreso como «${…}» en el PDF.
        if (preg_match_all('/\$\{([^}]+)\}/', $html, $m)) {
            return ['status' => 'fail', 'keys' => array_values(array_unique($m[1]))];
        }

        return ['status' => 'ok', 'html' => $html, 'estado' => $estado];
    }

    /**
     * @return array{status: 'ok', pdf: string, filename: string, estado: string}|array{status: 'fail', keys: string[]}
     */
    public function generar(Invoice $invoice, ?string $estado = null): array
    {
        $r = $this->html($invoice, $estado);
        if ($r['status'] !== 'ok') {
            return $r;
        }

        $pdf = app('dompdf.wrapper');
        // La plantilla usa Helvetica (no se incrusta); si algún texto cae en DejaVu, que se
        // incruste solo lo usado y no la fuente completa (~400 KB cada una).
        $pdf->setOption('isFontSubsettingEnabled', true);
        $bytes = $pdf->loadHTML(ContractPdfTemplate::wrap($r['html']))->setPaper('letter', 'portrait')->output();

        $folio = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($invoice->number ?: $invoice->id));

        return [
            'status'   => 'ok',
            'pdf'      => $bytes,
            'filename' => 'Prefactura-' . $folio . '-' . now()->format('Ymd') . '.pdf',
            'estado'   => $r['estado'],
        ];
    }
}
