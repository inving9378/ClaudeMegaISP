<?php

namespace App\Modules\Addons\WhatsAppAgent\Events;

/**
 * Mensaje con MEDIA entrante (image / document) por el gateway. Consumidor
 * típico: la conciliación de comprobantes (ConciliationListener), filtrada por
 * la función de línea 'cobranza'. `rawMessage` lleva el mensaje completo de
 * Evolution para poder descargar el binario.
 */
class WhatsAppMediaReceived extends WhatsAppMessageReceived
{
}
