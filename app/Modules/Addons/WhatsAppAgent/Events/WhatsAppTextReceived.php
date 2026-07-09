<?php

namespace App\Modules\Addons\WhatsAppAgent\Events;

/**
 * Mensaje de TEXTO entrante por el gateway. Consumidores típicos: el bot de IA
 * (IaAutoReplyListener) y, en Fase 2, el AiAgentService de Marketing como
 * listener. Se filtran por función de línea (ventas/atención) cuando aplique.
 */
class WhatsAppTextReceived extends WhatsAppMessageReceived
{
}
