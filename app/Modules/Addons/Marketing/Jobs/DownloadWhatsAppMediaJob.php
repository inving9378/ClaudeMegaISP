<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\Message;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * FASE 1 (conciliación de pagos por IA) — Ladrillo 2.
 *
 * Descarga AISLADA del binario de un mensaje multimedia entrante de WhatsApp.
 * Se dispara desde ProcessIncomingMessageJob SOLO para imágenes/documentos,
 * en cola aparte del pipeline de ventas. Un fallo aquí NO afecta la
 * persistencia del mensaje ni la respuesta del bot (ya ocurrieron antes).
 *
 * Idempotente: si el Message ya tiene media_downloaded_at, no vuelve a bajar.
 * Guarda con el mismo patrón que los comprobantes de mostrador: disco 'local'
 * (privado), bajo private/payments/whatsapp/comprobantes/.
 */
class DownloadWhatsAppMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];

    /** Tipos de mensaje de los que sí bajamos binario (comprobantes). */
    private const DOWNLOADABLE_TYPES = ['image', 'document'];

    /** Mimes aceptados (mismo criterio que la captura de mostrador + pdf). */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    /** Tope de tamaño del binario descifrado: 8 MB (igual que ManualPaymentController). */
    private const MAX_BYTES = 8 * 1024 * 1024;

    private const STORAGE_DIR = 'private/payments/whatsapp/comprobantes';

    /**
     * Compresión MODERADA de la copia archivada. Verificado con el
     * PaymentReceiptExtractor (IA) sobre comprobantes SPEI sintéticos: a q80 y
     * cap 1600px la clave de rastreo (27 chars) y el monto se leen idénticos al
     * original (confianza 'alta'). No es compresión agresiva: preserva el texto.
     * Solo aplica a imágenes raster; el PDF se archiva tal cual.
     */
    private const ARCHIVE_QUALITY   = 80;
    private const ARCHIVE_MAX_WIDTH  = 1600;

    public function __construct(public int $messageId) {}

    public function handle(EvolutionApiService $evolution): void
    {
        $message = Message::find($this->messageId);

        // Mensaje borrado entre el dispatch y la ejecución → nada que hacer.
        if ($message === null) {
            return;
        }

        // Guardia de tipo: solo imágenes/documentos.
        if (!in_array($message->content_type, self::DOWNLOADABLE_TYPES, true)) {
            return;
        }

        // Idempotencia: ya descargado → salir sin re-pedir a Evolution.
        if ($message->media_downloaded_at !== null || !empty($message->media_path)) {
            return;
        }

        $data = is_array($message->metadata) ? $message->metadata : [];
        if (empty($data['key']) || empty($data['message'])) {
            Log::channel('evolution')->warning('DownloadWhatsAppMedia: metadata sin key/message', [
                'message_id' => $message->id,
            ]);
            return;
        }

        // 1) Pedir el binario descifrado a Evolution.
        $res    = $evolution->getBase64FromMediaMessage($data);
        $base64 = $res['base64'] ?? null;
        if (empty($base64)) {
            Log::channel('evolution')->warning('DownloadWhatsAppMedia: respuesta sin base64', [
                'message_id' => $message->id,
                'keys'       => array_keys($res),
            ]);
            return;
        }

        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            Log::channel('evolution')->warning('DownloadWhatsAppMedia: base64 inválido', [
                'message_id' => $message->id,
            ]);
            return;
        }

        // 2) Validar tamaño.
        $bytes = strlen($binary);
        if ($bytes > self::MAX_BYTES) {
            Log::channel('evolution')->warning('DownloadWhatsAppMedia: binario excede el tope', [
                'message_id' => $message->id,
                'bytes'      => $bytes,
            ]);
            return;
        }

        // 3) Validar mime (el reportado por Evolution, y de segundo el real por firma).
        $mime = $res['mimetype'] ?? $this->sniffMime($binary);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            Log::channel('evolution')->warning('DownloadWhatsAppMedia: mime no permitido', [
                'message_id' => $message->id,
                'mime'       => $mime,
            ]);
            return;
        }

        // 4) Comprimir MODERADAMENTE la copia que se archiva (ver constantes).
        //    Verificado: la IA lee la versión comprimida igual que el original.
        [$storeBytes, $storeMime, $ext] = $this->compressForArchive($binary, $mime);

        // 5) Guardar con el patrón de comprobantes (disco local privado).
        $safeId   = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $message->external_message_id) ?: (string) $message->id;
        $filename = self::STORAGE_DIR . '/' . $safeId . '_' . Str::random(8) . '.' . $ext;

        Storage::disk('local')->put($filename, $storeBytes);

        // 6) Persistir el path + sello de descarga (guardia de idempotencia).
        $message->forceFill([
            'media_path'          => $filename,
            'media_downloaded_at' => now(),
        ])->save();

        Log::channel('evolution')->info('DownloadWhatsAppMedia: binario guardado', [
            'message_id'     => $message->id,
            'bytes_original' => $bytes,
            'bytes_guardado' => strlen($storeBytes),
            'mime'           => $storeMime,
        ]);
    }

    /**
     * Compresión moderada de la copia archivada. Devuelve [bytes, mime, ext].
     *
     * - PDF: se archiva tal cual (GD no procesa PDF).
     * - Imagen raster: cap de ancho a ARCHIVE_MAX_WIDTH (sin agrandar) + JPEG
     *   calidad ARCHIVE_QUALITY. Preserva el texto (probado con la IA).
     * - Guarda anti-inflado: WhatsApp ya comprime del lado servidor, así que
     *   re-encodear una imagen pequeña ya optimizada puede AGRANDARLA. Si el
     *   resultado no es más chico que el original, se archiva el original.
     * - Si Intervention/GD falla (imagen corrupta, etc.): se archiva el original
     *   sin comprimir — nunca perdemos el comprobante por un fallo de compresión.
     */
    private function compressForArchive(string $binary, string $mime): array
    {
        if ($mime === 'application/pdf') {
            return [$binary, $mime, 'pdf'];
        }

        try {
            $img = Image::make($binary);
            if ($img->width() > self::ARCHIVE_MAX_WIDTH) {
                $img->resize(self::ARCHIVE_MAX_WIDTH, null, function ($c) {
                    $c->aspectRatio();
                    $c->upsize();
                });
            }
            $jpg = (string) $img->encode('jpg', self::ARCHIVE_QUALITY);
            $img->destroy();

            // Anti-inflado: si comprimir no reduce (imagen ya optimizada por
            // WhatsApp), conservar el original.
            if (strlen($jpg) >= strlen($binary)) {
                return [$binary, $mime, $this->extensionForMime($mime)];
            }

            return [$jpg, 'image/jpeg', 'jpg'];
        } catch (\Throwable $e) {
            Log::channel('evolution')->warning('DownloadWhatsAppMedia: compresión falló, se archiva original', [
                'error' => $e->getMessage(),
            ]);
            return [$binary, $mime, $this->extensionForMime($mime)];
        }
    }

    private function sniffMime(string $binary): ?string
    {
        $magic = bin2hex(substr($binary, 0, 4));
        if (str_starts_with($magic, 'ffd8ff'))         return 'image/jpeg';
        if (str_starts_with($magic, '89504e47'))       return 'image/png';
        if (str_starts_with($magic, '25504446'))       return 'application/pdf'; // %PDF
        return null;
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'application/pdf' => 'pdf',
            default           => 'jpg',
        };
    }
}
