<?php

namespace App\Services\Finance\Billing;

use App\Models\BillingDocumentoEvidencia;
use App\Models\BillingNotification;
use Illuminate\Support\Facades\Storage;

/**
 * Clasificación ÚNICA de los PDFs de facturación (FAC-05 #510 / #511): la usan el inventario
 * (billing:pdfs-inventario, solo lectura) y la limpieza (billing:pdfs-limpiar), para que lo que se
 * borra sea exactamente lo que el inventario propone. Nunca clasifica como candidato una evidencia
 * (enviados/), un recibo (lo descarga la app de MegaFamilia) ni algo en espera.
 */
class ClasificadorPdfsFacturacion
{
    public const EVIDENCIA = 'EXCLUIDO · evidencia de un envío (FAC-05)';
    public const RECIBO = 'EXCLUIDO · recibo (lo descarga la app de MegaFamilia)';
    public const EN_ESPERA = 'EXCLUIDO · notificación en espera';
    public const TICKET_NO_ENVIABLE = 'CANDIDATO · ticket que no se envía';
    public const HUERFANO = 'CANDIDATO · huérfano (ninguna notificación lo usa)';
    public const HUERFANO_RECIENTE = 'EXCLUIDO · huérfano reciente';
    public const CANCELADA = 'CANDIDATO · notificación cancelada o con error';
    public const CANCELADA_RECIENTE = 'EXCLUIDO · cancelada/error reciente';
    public const ENVIADO_CON_COPIA = 'CANDIDATO · ya enviado y con copia en evidencias';
    public const ENVIADO_SIN_COPIA = 'EXCLUIDO · enviado sin copia en evidencias (envíos previos a FAC-05)';

    /**
     * Recorre los PDFs y llama $fn(string $archivo, int $bytes, string $categoria, bool $viejo).
     * $viejo = última modificación anterior a hace $dias días.
     */
    public function recorrer(int $dias, callable $fn): void
    {
        $disco = Storage::disk(config('billing.pdf_disk', 'local'));
        $prefijo = rtrim((string) config('billing.pdf_path_prefix', 'billing/pdf'), '/');
        $limite = now()->subDays(max(0, $dias))->getTimestamp();

        $usos = [];
        BillingNotification::query()->whereNotNull('pdf_path')
            ->select(['id', 'pdf_path', 'status', 'document_type', 'send_by_email'])
            ->orderBy('id')
            ->chunk(5000, function ($filas) use (&$usos) {
                foreach ($filas as $n) {
                    $usos[$n->pdf_path][] = $n;
                }
            });
        $conEvidencia = array_flip(BillingDocumentoEvidencia::query()->whereNotNull('billing_notification_id')
            ->distinct()->pluck('billing_notification_id')->all());

        foreach ($disco->allFiles($prefijo) as $archivo) {
            $viejo = $disco->lastModified($archivo) < $limite;
            $fn($archivo, (int) $disco->size($archivo), $this->categoria($archivo, $prefijo, $usos[$archivo] ?? [], $conEvidencia, $viejo), $viejo);
        }
    }

    private function categoria(string $archivo, string $prefijo, array $notifs, array $conEvidencia, bool $viejo): string
    {
        $tipos = array_unique(array_map(fn ($n) => $n->document_type, $notifs));
        $estados = array_unique(array_map(fn ($n) => $n->status, $notifs));
        $ticketNoEnviable = in_array(BillingNotification::TYPE_TICKET, $tipos, true) && ! collect($notifs)->contains('send_by_email', true);

        return match (true) {
            str_starts_with($archivo, $prefijo . '/enviados/')                        => self::EVIDENCIA,
            in_array(BillingNotification::TYPE_RECIBO, $tipos, true)                   => self::RECIBO,
            in_array(BillingNotification::STATUS_WAITING, $estados, true) && ! $ticketNoEnviable => self::EN_ESPERA,
            $ticketNoEnviable                                                          => self::TICKET_NO_ENVIABLE,
            ! $notifs                                                                  => $viejo ? self::HUERFANO : self::HUERFANO_RECIENTE,
            $estados && ! array_diff($estados, [BillingNotification::STATUS_CANCELLED, BillingNotification::STATUS_ERROR])
                                                                                       => $viejo ? self::CANCELADA : self::CANCELADA_RECIENTE,
            collect($notifs)->every(fn ($n) => isset($conEvidencia[$n->id]))          => self::ENVIADO_CON_COPIA,
            default                                                                    => self::ENVIADO_SIN_COPIA,
        };
    }
}
