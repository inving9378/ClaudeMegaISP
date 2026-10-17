<?php

namespace App\Services\Finance\Billing;

use App\Models\BillingDocumentoEvidencia as Evidencia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * FAC-05 (item #510): guarda los bytes EXACTOS de cada PDF de cobro que se manda, con su sha256 y
 * metadatos mínimos, en el disco privado de facturación (billing.pdf_disk, bajo
 * billing.pdf_path_prefix/enviados/AAAA/MM). Se copia al enviar porque el PDF de trabajo
 * (recibo_{id}.pdf, prefactura_…) se sobrescribe al regenerarse.
 *
 * Estados según evidencia: generado (antes de mandar) → encolado (se fue a la cola) → aceptado (el
 * servidor de correo lo recibió: lo marca el listener de MessageSent por la cabecera CABECERA) |
 * fallido (el envío lanzó error). No hay «entregado»: no existe acuse del destinatario.
 *
 * Nunca debe impedir un envío: si no puede guardar la evidencia, lo registra en el log y sigue.
 */
class EvidenciaDocumentoService
{
    public const CABECERA = 'X-MegaISP-Evidencia';

    /**
     * @param array{data: string, name: string, mime?: string} $adjunto
     * @param array{origen: string, documento: string, billing_notification_id?: ?int, client_id?: ?int,
     *              invoice_id?: ?int, payment_id?: ?int, destinatario?: ?string, asunto?: ?string} $meta
     */
    public function registrar(array $adjunto, array $meta, string $estado = Evidencia::ESTADO_GENERADO): ?Evidencia
    {
        try {
            $bytes = (string) $adjunto['data'];
            $nombre = (string) $adjunto['name'];
            $archivo = rtrim((string) config('billing.pdf_path_prefix', 'billing/pdf'), '/')
                . '/enviados/' . now()->format('Y/m') . '/' . Str::uuid() . '-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $nombre);

            Storage::disk(config('billing.pdf_disk', 'local'))->put($archivo, $bytes);

            return Evidencia::create(array_merge([
                'canal'          => 'correo',
                'archivo'        => $archivo,
                'nombre_adjunto' => $nombre,
                'sha256'         => hash('sha256', $bytes),
                'bytes'          => strlen($bytes),
                'estado'         => $estado,
                'generado_at'    => now(),
                'encolado_at'    => $estado === Evidencia::ESTADO_ENCOLADO ? now() : null,
            ], array_intersect_key($meta, array_flip([
                'origen', 'documento', 'billing_notification_id', 'client_id', 'invoice_id', 'payment_id', 'destinatario', 'asunto',
            ]))));
        } catch (\Throwable $e) {
            Log::warning('[Evidencia] No se pudo guardar la evidencia del PDF (el envío sigue): ' . $e->getMessage());

            return null;
        }
    }

    /** Valor de la cabecera que liga el correo con sus evidencias. @param int[] $ids */
    public static function valorCabecera(array $ids): string
    {
        return implode(',', array_map('intval', $ids));
    }

    /** @return int[] */
    public static function idsDeCabecera(?string $valor): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $valor))));
    }

    /** @param int[] $ids */
    public function marcarFallido(array $ids, string $error): void
    {
        if ($ids) {
            Evidencia::whereIn('id', $ids)->whereIn('estado', [Evidencia::ESTADO_GENERADO, Evidencia::ESTADO_ENCOLADO])
                ->update(['estado' => Evidencia::ESTADO_FALLIDO, 'error' => mb_substr($error, 0, 2000)]);
        }
    }

    /** Recalcula el sha256 del archivo guardado; null = el archivo no está. */
    public function verificar(Evidencia $e): ?bool
    {
        $disco = Storage::disk(config('billing.pdf_disk', 'local'));
        if (! $disco->exists($e->archivo)) {
            return null;
        }

        return hash('sha256', (string) $disco->get($e->archivo)) === $e->sha256;
    }
}
