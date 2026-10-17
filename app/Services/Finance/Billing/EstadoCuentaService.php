<?php

namespace App\Services\Finance\Billing;

use App\Models\ClientEstadoCuenta;
use App\Models\ClientFiscalData;
use App\Models\Invoice;
use App\Modules\Core\Configuracion\Models\CompanyInformation;
use App\Support\LogoPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Estado de cuenta del cliente (FR-02 #536): documento informativo SIN valor fiscal con el saldo,
 * las facturas pendientes y los últimos pagos. El «final» se genera al pasar a Cancelado; la Ronda 2
 * (#540) usará el mismo servicio para el mensual de bloqueados e inactivos.
 *
 * Generar NO envía: no crea filas en billing_notifications. Nunca timbra nada.
 */
class EstadoCuentaService
{
    /** Estados de factura que siguen debiendo algo. */
    private const PENDIENTES = [Invoice::STATUS_DRAFT, Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE];

    /** Estado de cuenta final al pasar a Cancelado. Idempotente por cliente y día del cambio. */
    public function generarFinal(int $clientId, string $origen = 'sistema', ?int $usuarioId = null, ?Carbon $fecha = null): ClientEstadoCuenta
    {
        return $this->generar($clientId, ClientEstadoCuenta::TIPO_FINAL, ($fecha ?? Carbon::now())->toDateString(), $origen, $usuarioId);
    }

    public function generar(int $clientId, string $tipo, string $clave, string $origen = 'sistema', ?int $usuarioId = null): ClientEstadoCuenta
    {
        $existente = ClientEstadoCuenta::where('client_id', $clientId)->where('tipo', $tipo)->where('clave', $clave)->first();
        if ($existente && $existente->pdf_path && ! $existente->error) {
            return $existente;
        }

        $datos = $this->datos($clientId, $tipo);
        $registro = $existente ?? new ClientEstadoCuenta(['client_id' => $clientId, 'tipo' => $tipo, 'clave' => $clave]);
        $registro->fill([
            'estado_cliente'      => $datos['estado_cliente'],
            'saldo'               => $datos['saldo'],
            'adeudo'              => $datos['adeudo'],
            'facturas_pendientes' => count($datos['facturas']),
            'origen'              => $origen,
            'generado_por'        => $usuarioId,
            'error'               => null,
        ]);

        try {
            $pdf = Pdf::loadView('billing.estado_cuenta', $datos)->setPaper('letter', 'portrait')
                ->setOption('isFontSubsettingEnabled', true)->output();
            $ruta = config('billing.pdf_path_prefix', 'billing/pdf') . "/estados_cuenta/estado_cuenta_{$clientId}_{$tipo}_{$clave}.pdf";
            Storage::disk(config('billing.pdf_disk', 'local'))->put($ruta, $pdf);
            $registro->pdf_path = $ruta;
            $registro->pdf_sha256 = hash('sha256', $pdf);
        } catch (\Throwable $e) {
            Log::error("[EstadoCuenta] No se generó el PDF ({$tipo}) del cliente {$clientId}: " . $e->getMessage());
            $registro->error = mb_substr($e->getMessage(), 0, 2000);
        }

        $registro->save();

        return $registro;
    }

    /** Datos del documento (solo lectura). */
    public function datos(int $clientId, string $tipo = ClientEstadoCuenta::TIPO_FINAL): array
    {
        $info = DB::table('client_main_information')->where('client_id', $clientId)->first();
        $saldo = (float) (DB::table('balances')->where('balanceable_id', $clientId)
            ->where('balanceable_type', 'like', '%Client')->value('amount') ?? 0);
        $fiscal = ClientFiscalData::deCliente($clientId);
        $nombre = trim(implode(' ', array_filter([$info->name ?? '', $info->father_last_name ?? '', $info->mother_last_name ?? ''])));

        $facturas = Invoice::where('client_id', $clientId)
            ->whereIn('status', self::PENDIENTES)
            ->where('pending_balance', '>', 0)
            ->orderBy('due_date')->orderBy('id')
            ->get(['id', 'number', 'folio', 'period', 'due_date', 'total', 'pending_balance'])
            ->map(fn ($f) => [
                'folio'   => (string) ($f->folio ?: $f->number ?: $f->id),
                'periodo' => (string) ($f->period ?? ''),
                'vence'   => self::fecha($f->due_date),
                'total'   => (float) $f->total,
                'saldo'   => (float) $f->pending_balance,
            ])->all();

        $pagos = DB::table('payments as p')
            ->leftJoin('method_of_payments as m', 'm.id', '=', 'p.payment_method_id')
            ->where('p.paymentable_id', $clientId)
            ->where('p.paymentable_type', 'like', '%Client')
            ->whereNull('p.deleted_at')
            ->orderByDesc('p.id')->limit(5)
            ->get(['p.date', 'p.amount', 'm.type as metodo'])
            ->map(fn ($p) => ['fecha' => (string) $p->date, 'monto' => (float) $p->amount, 'metodo' => (string) ($p->metodo ?? '')])
            ->all();

        $empresa = CompanyInformation::first();

        return [
            'tipo'            => $tipo,
            'titulo'          => $tipo === ClientEstadoCuenta::TIPO_FINAL ? 'ESTADO DE CUENTA FINAL' : 'ESTADO DE CUENTA',
            'subtitulo'       => $tipo === ClientEstadoCuenta::TIPO_FINAL ? 'Baja definitiva del servicio' : '',
            'fecha'           => Carbon::now()->format('d/m/Y'),
            'cliente_numero'  => $clientId,
            'cliente_nombre'  => $fiscal && $fiscal->requiereFacturaFiscal() && $fiscal->razon_social ? $fiscal->razon_social : ($nombre ?: "Cliente #{$clientId}"),
            'cliente_correo'  => (string) ($info->email ?? ''),
            'estado_cliente'  => (string) ($info->estado ?? ''),
            'saldo'           => $saldo,
            'adeudo'          => $saldo < 0 ? round(-$saldo, 2) : 0.0,
            'a_favor'         => $saldo > 0 ? round($saldo, 2) : 0.0,
            'facturas'        => $facturas,
            'pagos'           => $pagos,
            'referencia'      => (string) (DB::table('client_payment_references')->where('client_id', $clientId)->value('reference') ?? ''),
            'empresa_nombre'  => (string) ($empresa->company_name ?? ''),
            'empresa_rfc'     => (string) ($empresa->rfc ?? ''),
            'empresa_tel'     => (string) ($empresa->atention_client_phone ?? ''),
            'empresa_correo'  => (string) ($empresa->email ?? ''),
            'banco'           => (string) ($empresa->bank_name ?? ''),
            'cuenta'          => (string) ($empresa->bank_account ?? ''),
            'logo'            => ($empresa && $empresa->url_logo) ? LogoPdf::dataUri() : '',
        ];
    }

    private static function fecha($valor): string
    {
        if (! $valor) {
            return '';
        }
        try {
            return Carbon::parse($valor)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $valor;
        }
    }
}
