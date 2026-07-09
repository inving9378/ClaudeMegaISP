<?php

namespace App\Modules\Addons\WhatsAppAgent\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento base del gateway único de WhatsApp (WhatsAppAgent).
 *
 * Se dispara para TODO mensaje entrante ya normalizado y persistido en
 * whatsapp_messages. Los consumidores (conciliación, IA y, a futuro, cualquier
 * módulo) se suscriben como listeners SIN tocar el gateway. Los especializados
 * {@see WhatsAppTextReceived} / {@see WhatsAppMediaReceived} heredan de éste.
 *
 * Sólo lleva ESCALARES/ids (serializable a cola): los listeners re-hidratan los
 * modelos por id. `rawMessage` es el nodo `data` del webhook de Evolution
 * (key + message), necesario para la descarga de media.
 */
class WhatsAppMessageReceived
{
    use Dispatchable;

    /**
     * @param string[] $lineFunctions slugs de las funciones asignadas a la línea (p.ej. cobranza, ventas)
     * @param array    $rawMessage    nodo `data` del webhook Evolution (key+message), para descargar media
     */
    public function __construct(
        public int $instanceId,
        public string $instanceSlug,
        public int $conversationId,
        public int $messageId,
        public string $contactNumber,
        public string $type,
        public ?string $text = null,
        public array $lineFunctions = [],
        public array $rawMessage = [],
    ) {
    }

    /** ¿La línea que recibió el mensaje atiende esta función? (p.ej. 'cobranza'). */
    public function hasFunction(string $slug): bool
    {
        return in_array($slug, $this->lineFunctions, true);
    }
}
