<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gateway ÚNICO de WhatsApp (fachada del transporte).
 *
 * Salida (envío) + descarga de media sobre el EvolutionApiService de
 * WhatsAppAgent. Los módulos consumidores (conciliación, IA, notificaciones,
 * ...) usan ESTE servicio; ninguno debe tener su propia integración Evolution.
 * En Fase 3 los consumidores del sender de Marketing migran aquí.
 */
class WhatsAppGateway
{
    private const DOWNLOADABLE  = ['image', 'document'];
    private const MEDIA_DIR     = 'private/whatsapp/media';
    private const MAX_BYTES     = 12 * 1024 * 1024;
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function __construct(private EvolutionApiService $evolution)
    {
    }

    /**
     * Envía texto por la línea (slug) y registra el mensaje OUT. Reusa sendAndLog
     * (crea whatsapp_messages OUT + despacha el envío por cola). $instanceSlug
     * nulo cae a la instancia activa marcada default_instance (mismo fallback
     * de sendAndLog) — útil para consumidores sin conversación previa.
     */
    public function sendText(?string $instanceSlug, string $to, string $body, array $context = []): WhatsAppMessage
    {
        return $this->evolution->sendAndLog($to, $body, $instanceSlug, $context);
    }

    /**
     * Descarga el binario de un mensaje con media, lo guarda en disco privado y
     * puebla whatsapp_messages.media_*. Idempotente. Devuelve {path, mime, size}
     * o null si no aplica / falla.
     *
     * @param array $rawEvolutionMessage nodo `data` del webhook Evolution (key+message)
     */
    public function downloadMedia(WhatsAppMessage $message, array $rawEvolutionMessage): ?array
    {
        if (!in_array($message->message_type, self::DOWNLOADABLE, true)) {
            return null;
        }

        // Idempotencia: ya descargado.
        if (!empty($message->media_path) && Storage::disk('local')->exists($message->media_path)) {
            return ['path' => $message->media_path, 'mime' => $message->media_mime_type, 'size' => $message->media_size];
        }

        $instance = $message->instance;
        if (!$instance) {
            return null;
        }

        $res    = $this->evolution->getBase64FromMediaMessage($instance, $rawEvolutionMessage);
        $base64 = $res['base64'] ?? null;
        if (empty($base64)) {
            Log::channel('evolution')->warning('WhatsAppGateway: respuesta sin base64', [
                'message_id' => $message->id,
                'keys'       => array_keys($res),
            ]);
            return null;
        }

        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $bytes = strlen($binary);
        if ($bytes > self::MAX_BYTES) {
            Log::channel('evolution')->warning('WhatsAppGateway: media excede el tope', ['message_id' => $message->id, 'bytes' => $bytes]);
            return null;
        }

        // Mime: el reportado por Evolution y, de segundo, el real por firma.
        $mime = $res['mimetype'] ?? $this->sniffMime($binary);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            Log::channel('evolution')->warning('WhatsAppGateway: mime no permitido', ['message_id' => $message->id, 'mime' => $mime]);
            return null;
        }

        $safeId   = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $message->evolution_message_id) ?: (string) $message->id;
        $filename = self::MEDIA_DIR . '/' . $safeId . '_' . Str::random(8) . '.' . $this->extFor($mime);
        Storage::disk('local')->put($filename, $binary);

        $message->forceFill([
            'media_path'          => $filename,
            'media_mime_type'     => $mime,
            'media_size'          => $bytes,
            'media_downloaded_at' => now(),
        ])->save();

        return ['path' => $filename, 'mime' => $mime, 'size' => $bytes];
    }

    private function sniffMime(string $bytes): string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($bytes, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, '%PDF')) {
            return 'application/pdf';
        }
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        return 'application/octet-stream';
    }

    private function extFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'application/pdf' => 'pdf',
            default           => 'bin',
        };
    }
}
