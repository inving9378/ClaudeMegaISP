<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Addons\WhatsAppAgent\Contracts\WhatsAppProviderInterface;
use App\Services\Core\UsesApiIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Driver de la API OFICIAL de WhatsApp (Meta WhatsApp Business Platform /
 * Cloud API) -- la alternativa configurable a Evolution API (ver
 * WhatsAppProviderInterface). Credenciales resueltas del Integration Hub
 * (api_integrations, provider='whatsapp-oficial'), mismo mecanismo ya usado
 * por los proveedores de IA.
 *
 * Sin credenciales configuradas, el constructor NO truena -- queda con
 * strings vacíos y cada llamada real fallará con un error claro al intentar
 * pegarle a Meta (fetchInstanceStatus() lo refleja sin tronar).
 */
class MetaCloudApiService implements WhatsAppProviderInterface
{
    use UsesApiIntegration;

    protected string $accessToken;
    protected string $phoneNumberId;
    protected string $apiVersion;
    protected ?string $appSecret;
    protected ?string $verifyToken;

    public function __construct(int $companyId = 1)
    {
        $integration = $this->resolveApiIntegration('whatsapp-oficial', $companyId);

        $this->accessToken   = $integration?->value ?? '';
        $this->phoneNumberId = (string) data_get($integration?->config, 'phone_number_id', '');
        $this->apiVersion    = (string) (data_get($integration?->config, 'api_version') ?: 'v21.0');
        $this->appSecret     = data_get($integration?->config, 'app_secret');
        $this->verifyToken   = data_get($integration?->config, 'verify_token');
    }

    // ── Envío ───────────────────────────────────────────────────────────────────

    public function sendText(string $to, string $text): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to'                => $this->normalizeRecipient($to),
            'type'              => 'text',
            'text'              => ['body' => $text],
        ]);
    }

    public function sendMedia(string $to, string $mediaUrl, string $mediaType, ?string $caption = null): array
    {
        $type = $this->mapMediaType($mediaType);

        return $this->post([
            'messaging_product' => 'whatsapp',
            'to'                => $this->normalizeRecipient($to),
            'type'              => $type,
            $type               => array_filter([
                'link'    => $mediaUrl,
                'caption' => $caption,
            ]),
        ]);
    }

    /**
     * Plantilla REAL pre-aprobada por Meta (obligatorio fuera de la ventana
     * de 24h). `$templateName` debe coincidir exacto con el nombre aprobado
     * en Meta Business Manager -- ver config/whatsapp_templates.php para el
     * texto de referencia que se sometió a revisión.
     */
    public function sendTemplate(string $to, string $templateName, string $lang, array $variables = []): array
    {
        $components = [];
        if (!empty($variables)) {
            $components[] = [
                'type'       => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $variables),
            ];
        }

        return $this->post([
            'messaging_product' => 'whatsapp',
            'to'                => $this->normalizeRecipient($to),
            'type'              => 'template',
            'template'          => array_filter([
                'name'       => $templateName,
                'language'   => ['code' => $lang],
                'components' => $components,
            ]),
        ]);
    }

    /**
     * FAC-09 (#516): sube un archivo a Meta (POST /{phone}/media) y devuelve su media_id. Así el
     * PDF viaja sin publicar ninguna URL. Meta: los ids subidos vencen a los 30 días; documentos
     * de hasta 100 MB.
     */
    public function uploadMedia(string $contenido, string $nombre, string $mime = 'application/pdf'): string
    {
        $res = Http::withToken($this->accessToken)
            ->timeout(30)
            ->attach('file', $contenido, $nombre, ['Content-Type' => $mime])
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type'              => $mime,
            ]);

        if ($res->status() >= 400 || ! $res->json('id')) {
            Log::channel('evolution')->error('Meta Cloud API media error', ['status' => $res->status(), 'body' => $res->json()]);
            throw new RuntimeException('Meta Cloud API media error ' . $res->status() . ': ' . ($res->json('error.message') ?? ''));
        }

        return (string) $res->json('id');
    }

    /**
     * FAC-09 (#516): plantilla aprobada con encabezado DOCUMENTO (el PDF ya subido con uploadMedia)
     * y variables posicionales del cuerpo.
     */
    public function sendDocumentTemplate(string $to, string $templateName, string $lang, string $mediaId, string $filename, array $variables = []): array
    {
        $components = [[
            'type'       => 'header',
            'parameters' => [['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => $filename]]],
        ]];
        if (! empty($variables)) {
            $components[] = [
                'type'       => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($variables)),
            ];
        }

        return $this->post([
            'messaging_product' => 'whatsapp',
            'to'                => $this->normalizeRecipient($to),
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => $lang],
                'components' => $components,
            ],
        ]);
    }

    public function markAsRead(string $to, string $messageId): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $messageId,
        ]);
    }

    /** Meta no expone indicador de "escribiendo..." -- no-op seguro, nunca lanza excepción. */
    public function sendTyping(string $to, int $durationMs = 2000): array
    {
        return ['skipped' => true, 'reason' => 'La API oficial de Meta no soporta indicador de escritura.'];
    }

    /** Sin concepto de "conectar/desconectar" -- refleja si hay credenciales configuradas en el Hub. */
    public function fetchInstanceStatus(): array
    {
        $listo = $this->phoneNumberId !== '' && $this->accessToken !== '';

        return $listo
            ? ['state' => 'open', 'provider' => 'whatsapp-oficial']
            : [
                'state'    => 'close',
                'provider' => 'whatsapp-oficial',
                'reason'   => 'Faltan credenciales en el Hub (Integraciones > whatsapp-oficial).',
            ];
    }

    /** El webhook de Meta se apunta manualmente en developers.facebook.com -- no hay endpoint de API para esto. */
    public function setWebhook(string $webhookUrl, array $events = []): array
    {
        Log::channel('evolution')->info('MetaCloudApiService::setWebhook es no-op -- URL a configurar manualmente en Meta Business Manager', [
            'webhook_url_sugerida' => $webhookUrl,
        ]);

        return ['skipped' => true, 'reason' => 'Configúralo en developers.facebook.com, no hay API para esto.'];
    }

    /** Acepta tanto un JID de Evolution ("521...@s.whatsapp.net") como un número plano -- Meta solo quiere dígitos con código de país. */
    public function normalizeRecipient(string $phone): string
    {
        $withoutJid = explode('@', $phone)[0];

        return preg_replace('/\D/', '', $withoutJid);
    }

    // ── Descarga de media (2 pasos: media id → URL temporal → bytes) ────────────

    /**
     * @param array $rawMessage debe traer `media_id` (lo pone extractMessages() al
     *   parsear el webhook entrante) -- a diferencia de Evolution, Meta nunca
     *   manda el binario directo, solo un id que hay que resolver.
     */
    public function downloadMedia(array $rawMessage): array
    {
        $mediaId = $rawMessage['media_id'] ?? null;
        if (!$mediaId) {
            throw new RuntimeException('MetaCloudApiService::downloadMedia requiere media_id en $rawMessage.');
        }

        // Paso 1: media id → URL temporal (expira ~5 min) + mime real.
        $meta = Http::withToken($this->accessToken)
            ->timeout(15)
            ->get("https://graph.facebook.com/{$this->apiVersion}/{$mediaId}")
            ->throw()
            ->json();

        $url = $meta['url'] ?? null;
        if (!$url) {
            throw new RuntimeException('Meta no devolvió URL de descarga para el media id ' . $mediaId);
        }

        // Paso 2: los bytes están detrás del MISMO Bearer token (no es una URL pública).
        $res = Http::withToken($this->accessToken)->timeout(30)->get($url)->throw();

        return [
            'base64'   => base64_encode($res->body()),
            'mimetype' => $meta['mime_type'] ?? $res->header('Content-Type') ?? 'application/octet-stream',
            'fileName' => $rawMessage['filename'] ?? null,
        ];
    }

    // ── Webhook: verificación de firma + parseo del payload entrante ────────────

    /** GET de verificación que Meta manda UNA vez al configurar/guardar la URL del webhook. */
    public function verifyHandshake(?string $mode, ?string $token, ?string $challenge): ?string
    {
        if ($mode === 'subscribe' && $token && $this->verifyToken && hash_equals((string) $this->verifyToken, $token)) {
            return $challenge;
        }

        return null;
    }

    /** Verifica la firma HMAC-SHA256 que Meta manda en cada POST (header X-Hub-Signature-256). */
    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (!$this->appSecret || !$signatureHeader) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $this->appSecret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * Extrae los mensajes entrantes de un payload de Meta Cloud API, ya
     * normalizados a la misma forma que el router usa para Evolution:
     * {messageId, contactNumber, contactName, type, body, rawMessage}.
     * Los eventos de `statuses` (entregado/leído de SALIENTES) se ignoran
     * aquí a propósito -- no son mensajes nuevos, el router no los necesita.
     */
    public function extractMessages(array $payload): array
    {
        $out = [];

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value    = data_get($change, 'value', []);
                $contacts = collect(data_get($value, 'contacts', []))->keyBy('wa_id');

                foreach (data_get($value, 'messages', []) as $msg) {
                    $from        = $msg['from'] ?? '';
                    $contactName = data_get($contacts->get($from), 'profile.name');
                    [$type, $body, $mediaId, $filename] = $this->resolveMessageContent($msg);

                    $out[] = [
                        'messageId'     => $msg['id'] ?? null,
                        'contactNumber' => $from,
                        'contactName'   => $contactName,
                        'type'          => $type,
                        'body'          => $body,
                        'rawMessage'    => array_filter([
                            'media_id' => $mediaId,
                            'filename' => $filename,
                            'raw'      => $msg,
                        ]),
                    ];
                }
            }
        }

        return $out;
    }

    /** @return array{0:string,1:?string,2:?string,3:?string} [type, body/caption, media_id, filename] */
    private function resolveMessageContent(array $msg): array
    {
        $type = $msg['type'] ?? 'text';

        return match ($type) {
            'text'     => ['text', $msg['text']['body'] ?? null, null, null],
            'image'    => ['image', $msg['image']['caption'] ?? null, $msg['image']['id'] ?? null, null],
            'document' => ['document', $msg['document']['caption'] ?? null, $msg['document']['id'] ?? null, $msg['document']['filename'] ?? null],
            'audio'    => ['audio', null, $msg['audio']['id'] ?? null, null],
            'video'    => ['video', $msg['video']['caption'] ?? null, $msg['video']['id'] ?? null, null],
            'sticker'  => ['sticker', null, $msg['sticker']['id'] ?? null, null],
            default    => ['text', null, null, null],
        };
    }

    // ── HTTP interno ─────────────────────────────────────────────────────────────

    private function post(array $body): array
    {
        $res = Http::withToken($this->accessToken)
            ->timeout(15)
            ->retry(3, 200, fn ($e) => $e->response?->status() >= 500, throw: false)
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages", $body);

        Log::channel('evolution')->info('Meta Cloud API', [
            'status' => $res->status(),
            'to'     => $body['to'] ?? null,
            'type'   => $body['type'] ?? null,
        ]);

        if ($res->status() >= 400) {
            Log::channel('evolution')->error('Meta Cloud API error', [
                'status' => $res->status(),
                'body'   => $res->json(),
            ]);
            throw new RuntimeException('Meta Cloud API error ' . $res->status() . ': ' . ($res->json('error.message') ?? ''));
        }

        return $res->json() ?? [];
    }

    private function mapMediaType(string $mediaType): string
    {
        return match (true) {
            str_starts_with($mediaType, 'image') => 'image',
            str_starts_with($mediaType, 'video') => 'video',
            str_starts_with($mediaType, 'audio') => 'audio',
            default                              => 'document',
        };
    }
}
