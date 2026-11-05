<?php

namespace App\Services\Finance\Timbrado;

use App\Models\ClientFiscalData;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Adaptador PAC Facturama (API Web, CFDI 4.0). Proveedor elegido por Irving (#117).
 * Apagado por defecto: solo se enlaza si FACTURAMA_ENABLED=true y hay credenciales
 * (ver AppServiceProvider). Por defecto apunta al sandbox.
 */
class FacturamaTimbradoService implements TimbradoServiceInterface
{
    public function emitirFactura(Payment $payment, ClientFiscalData $fiscalData): array
    {
        $this->exigirDisponible();

        // El pago incluye IVA 16 %: se desglosa a subtotal + impuesto.
        $total    = round((float) $payment->amount, 2);
        $subtotal = round($total / 1.16, 2);

        $body = [
            'Receiver' => [
                'Name'          => $fiscalData->razon_social,
                'Rfc'           => $fiscalData->rfc,
                'CfdiUse'       => $fiscalData->uso_cfdi ?: 'G03',
                'FiscalRegime'  => $fiscalData->regimen_fiscal,
                'TaxZipCode'    => $fiscalData->codigo_postal_fiscal,
            ],
            'CfdiType'      => 'I',
            'NameId'        => '1',
            'ExpeditionPlace' => config('services.facturama.expedition_zip'),
            'PaymentForm'   => $fiscalData->forma_pago_cfdi ?: '03',
            'PaymentMethod' => $fiscalData->metodo_pago_cfdi ?: 'PUE',
            'Currency'      => 'MXN',
            'Items'         => [[
                'ProductCode' => '81161700',
                'Description' => 'Servicio de internet',
                'UnitCode'    => 'E48',
                'Unit'        => 'Servicio',
                'Quantity'    => 1,
                'UnitPrice'   => $subtotal,
                'Subtotal'    => $subtotal,
                'TaxObject'   => '02',
                'Taxes'       => [[
                    'Name' => 'IVA', 'Rate' => 0.16, 'IsRetention' => false,
                    'Base' => $subtotal, 'Total' => round($total - $subtotal, 2),
                ]],
                'Total'       => $total,
            ]],
        ];

        $res = $this->http()->post('3/cfdis', $body);
        if (!$res->successful()) {
            throw new \RuntimeException('Facturama rechazó el timbrado: ' . $res->body());
        }

        $cfdi = $res->json();
        $id   = $cfdi['Id'] ?? null;
        $uuid = $cfdi['Complement']['TaxStamp']['Uuid'] ?? null;
        if (!$id || !$uuid) {
            throw new \RuntimeException('Respuesta de Facturama sin Id/UUID.');
        }

        $dir = "cfdi/{$uuid}";
        $xml = $this->http()->get("cfdi/xml/issued/{$id}")->json('Content');
        $pdf = $this->http()->get("cfdi/pdf/issued/{$id}")->json('Content');
        Storage::disk('local')->put("{$dir}/{$uuid}.xml", base64_decode((string) $xml));
        Storage::disk('local')->put("{$dir}/{$uuid}.pdf", base64_decode((string) $pdf));

        return [
            'uuid'        => $uuid,
            'folio_fiscal' => $uuid,
            'pac_id'      => $id,
            'xml_path'    => "{$dir}/{$uuid}.xml",
            'pdf_path'    => "{$dir}/{$uuid}.pdf",
            'timbrado_at' => now(),
        ];
    }

    public function cancelarFactura(string $uuid, string $motivo = '02'): bool
    {
        $this->exigirDisponible();

        $found = $this->http()->get('cfdi', ['type' => 'issued', 'keyword' => $uuid])->json();
        $id    = $found[0]['Id'] ?? null;
        if (!$id) {
            throw new \RuntimeException("Facturama no encontró el CFDI {$uuid}.");
        }

        return $this->http()->delete("cfdi/{$id}", ['type' => 'issued', 'motive' => $motivo])->successful();
    }

    public function isDisponible(): bool
    {
        return (bool) config('services.facturama.enabled')
            && config('services.facturama.user') !== ''
            && config('services.facturama.password') !== '';
    }

    public function nombreProveedor(): string
    {
        return 'Facturama' . (config('services.facturama.sandbox') ? ' (sandbox)' : '');
    }

    private function exigirDisponible(): void
    {
        if (!$this->isDisponible()) {
            throw new PacNoConfiguradoException();
        }
    }

    private function http()
    {
        $base = config('services.facturama.sandbox') ? 'https://apisandbox.facturama.mx/' : 'https://api.facturama.mx/';

        return Http::baseUrl($base)->timeout(30)->acceptJson()
            ->withBasicAuth(config('services.facturama.user'), config('services.facturama.password'));
    }
}
