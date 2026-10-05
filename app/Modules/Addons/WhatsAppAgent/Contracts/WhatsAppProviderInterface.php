<?php

namespace App\Modules\Addons\WhatsAppAgent\Contracts;

/**
 * Contrato compartido por los dos proveedores de WhatsApp (Evolution API /
 * WhatsApp Business Platform de Meta), para que el envío quede configurable
 * por línea sin que cada uno de los ~15 consumidores reales (Marketing,
 * Payments, Flotas, PortalPago, WarRoom, Roadmap, Referrals) tenga que
 * conocer cuál está activo. Mismo patrón ya usado para proveedores de IA
 * (IAAdaptadorFactory + interfaz común).
 *
 * Diferencias de contrato real entre los dos proveedores (documentadas para
 * quien implemente un driver nuevo, NO se pueden esconder del todo):
 * - `sendText`/`sendMedia` en la API oficial SOLO funcionan dentro de la
 *   ventana de 24h desde el último mensaje del cliente; fuera de eso hay que
 *   usar `sendTemplate` con una plantilla pre-aprobada por Meta. Evolution no
 *   tiene esa restricción -- su `sendTemplate` cae a `sendText` resolviendo
 *   las variables a texto plano.
 * - `sendTyping`/`fetchInstanceStatus`/`setWebhook` son conceptos de
 *   Evolution (sesión de WhatsApp Web pareada por QR) sin equivalente real en
 *   la API oficial -- el driver de Meta los implementa como no-ops seguros
 *   (ver MetaCloudApiService), nunca lanzan excepción.
 */
interface WhatsAppProviderInterface
{
    /** Envía texto libre. Devuelve la respuesta cruda del proveedor (array). */
    public function sendText(string $to, string $text): array;

    /** Envía un archivo multimedia por URL pública. */
    public function sendMedia(string $to, string $mediaUrl, string $mediaType, ?string $caption = null): array;

    /**
     * Envía un mensaje por PLANTILLA pre-aprobada (obligatorio en la API
     * oficial fuera de la ventana de 24h; en Evolution es un sendText con
     * las variables ya resueltas, sin plantilla real de por medio).
     *
     * @param array $variables variables posicionales de la plantilla, en orden
     */
    public function sendTemplate(string $to, string $templateName, string $lang, array $variables = []): array;

    /**
     * Descarga el binario de un mensaje multimedia entrante.
     *
     * @param array $rawMessage el nodo crudo del mensaje tal como lo guardó
     *   el router al recibirlo (forma distinta por proveedor: Evolution
     *   necesita {key,message} completos; Meta solo necesita el media id).
     * @return array{base64:string,mimetype:string,fileName:?string}
     */
    public function downloadMedia(array $rawMessage): array;

    /** Marca un mensaje entrante como leído (doble palomita azul). */
    public function markAsRead(string $to, string $messageId): array;

    /** Indicador de "escribiendo..." -- no-op seguro si el proveedor no lo soporta. */
    public function sendTyping(string $to, int $durationMs = 2000): array;

    /** Estado de conexión de la línea -- la API oficial no tiene "desconectado", siempre responde fijo. */
    public function fetchInstanceStatus(): array;

    /** Configura la URL de webhook del lado del proveedor -- no-op si se configura manualmente en el dashboard de Meta. */
    public function setWebhook(string $webhookUrl, array $events = []): array;

    /** Normaliza un teléfono al formato de destinatario que este proveedor espera (JID de Evolution vs. E.164 de Meta). */
    public function normalizeRecipient(string $phone): string;
}
