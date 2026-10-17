<?php

namespace App\Services\Finance\Billing;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FAC-11 (item #519): indicadores de facturación para el bloque de Finanzas del Dashboard. Solo
 * agregados (sin datos personales), solo lectura, caché de 10 min. Fórmulas, periodo, denominadores
 * y fuentes documentados en docs/manual/indicadores-facturacion.md. Donde no hay una fuente
 * confiable se muestra «Sin datos» — nunca se inventa ni se equipara «enviado» con «entregado».
 *
 * Periodo: [desde, hasta) sobre fechas de creación (created_at) salvo donde se indica.
 */
class IndicadoresFacturacionService
{
    public const SIN_DATOS = 'Sin datos';

    /** @return array<int, array{nombre: string, valor: string, detalle: string}> */
    public function calcular(Carbon $desde, Carbon $hasta): array
    {
        $clave = 'fac11:indicadores:' . $desde->toDateString() . ':' . $hasta->toDateString();

        return Cache::remember($clave, 600, fn () => $this->sinCache($desde, $hasta));
    }

    public function sinCache(Carbon $desde, Carbon $hasta): array
    {
        $d = $desde->toDateTimeString();
        $h = $hasta->toDateTimeString();

        $proformas = DB::table('invoices')->where('type', 'proforma')->where('created_at', '>=', $d)->where('created_at', '<', $h);
        $totalProformas = (clone $proformas)->count();

        // 1) Correo aceptado por el servidor (NO es entrega): evidencias FAC-05, sin interceptadas.
        $aceptadas = null;
        $hayEvidencias = Schema::hasTable('billing_documento_evidencias')
            && DB::table('billing_documento_evidencias')->where('created_at', '>=', $d)->where('created_at', '<', $h)->exists();
        if ($hayEvidencias) {
            $aceptadas = DB::table('billing_documento_evidencias')
                ->where('canal', 'correo')->where('documento', 'prefactura')->where('estado', 'aceptado')->where('interceptado', false)
                ->whereIn('invoice_id', (clone $proformas)->select('id'))
                ->distinct()->count('invoice_id');
        }

        // 2) Días proforma → pago (solo pagadas en el periodo, sin las retroactivas ni pagos anticipados).
        $dias = DB::table('invoices')->where('type', 'proforma')->where('status', 'paid')
            ->whereNotNull('payment_date')->whereNotNull('created_at')
            ->where('payment_date', '>=', $desde->toDateString())->where('payment_date', '<', $hasta->toDateString())
            ->where(fn ($q) => $q->whereNull('payment_method')->orWhere('payment_method', '!=', 'retroactive'))
            ->whereRaw('DATEDIFF(payment_date, created_at) >= 0')
            ->selectRaw('COUNT(*) n, AVG(DATEDIFF(payment_date, created_at)) prom')->first();

        // 3) Pagos identificados por referencia MEG (fuente única: conciliación por WhatsApp).
        $pagos = DB::table('payments')->whereIn('paymentable_type', ['App\\Models\\Client', 'App\\Modules\\Core\\Clientes\\Models\\Client'])
            ->where('created_at', '>=', $d)->where('created_at', '<', $h);
        $totalPagos = (clone $pagos)->count();
        $conMeg = Schema::hasTable('whatsapp_identification_sessions')
            ? (clone $pagos)->whereIn('id', DB::table('whatsapp_identification_sessions')->where('method', 'meg')
                ->where('is_simulation', false)->whereNotNull('applied_payment_id')->select('applied_payment_id'))->count()
            : null;

        // 4) Pagadas sin CFDI de clientes con datos fiscales (RFC capturado).
        $sinCfdi = DB::table('invoices as i')->where('i.status', 'paid')
            ->where('i.payment_date', '>=', $desde->toDateString())->where('i.payment_date', '<', $hasta->toDateString())
            ->whereExists(fn ($q) => $q->from('client_fiscal_data as f')->whereColumn('f.client_id', 'i.client_id')
                ->whereNotNull('f.rfc')->where('f.rfc', '!=', ''))
            ->whereNotExists(fn ($q) => $q->from('client_invoice_cfdi as c')->whereColumn('c.client_invoice_id', 'i.client_invoice_id')
                ->whereNull('c.cancelado_at'))
            ->selectRaw('COUNT(*) n, COALESCE(SUM(i.total), 0) monto')->first();

        // 5) Errores: envíos con error y evidencias fallidas. La generación de PDF solo deja log.
        $erroresEnvio = DB::table('billing_notifications')->where('status', 'error')->where('updated_at', '>=', $d)->where('updated_at', '<', $h)->count();
        $evidenciasFallidas = Schema::hasTable('billing_documento_evidencias')
            ? DB::table('billing_documento_evidencias')->where('estado', 'fallido')->where('created_at', '>=', $d)->where('created_at', '<', $h)->count()
            : 0;

        return [
            ['nombre' => 'Proformas generadas', 'valor' => (string) $totalProformas, 'detalle' => 'invoices tipo proforma creadas en el periodo'],
            ['nombre' => '% proformas entregadas', 'valor' => self::SIN_DATOS, 'detalle' => 'no hay acuse de entrega del destinatario'],
            ['nombre' => '% proformas aceptadas por el servidor de correo',
                'valor' => $aceptadas === null || $totalProformas === 0 ? self::SIN_DATOS : self::porcentaje($aceptadas, $totalProformas),
                'detalle' => $aceptadas === null ? 'sin evidencias de envío en el periodo (FAC-05)' : "{$aceptadas} de {$totalProformas}; aceptado ≠ entregado"],
            ['nombre' => 'Días promedio proforma → pago',
                'valor' => (int) ($dias->n ?? 0) === 0 ? self::SIN_DATOS : number_format((float) $dias->prom, 1),
                'detalle' => (int) ($dias->n ?? 0) . ' proformas pagadas en el periodo (sin retroactivas ni pagos anticipados)'],
            ['nombre' => '% pagos identificados por referencia MEG (WhatsApp)',
                'valor' => $conMeg === null || $totalPagos === 0 ? self::SIN_DATOS : self::porcentaje($conMeg, $totalPagos),
                'detalle' => $conMeg === null ? 'sin tabla de identificación' : "{$conMeg} de {$totalPagos} pagos de clientes; solo la conciliación por WhatsApp registra el método"],
            ['nombre' => 'Pagadas sin CFDI de clientes con datos fiscales',
                'valor' => (int) $sinCfdi->n . ' ($' . number_format((float) $sinCfdi->monto, 2) . ')',
                'detalle' => 'pagadas en el periodo, cliente con RFC capturado, sin CFDI vigente ligado'],
            ['nombre' => 'Errores de envío',
                'valor' => (string) ($erroresEnvio + $evidenciasFallidas),
                'detalle' => "{$erroresEnvio} notificaciones con error + {$evidenciasFallidas} evidencias fallidas; errores de generación de PDF: solo en el log"],
        ];
    }

    public static function porcentaje(int $parte, int $total): string
    {
        return $total > 0 ? number_format(100 * $parte / $total, 1) . '%' : self::SIN_DATOS;
    }
}
