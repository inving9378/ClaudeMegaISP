<?php

return [
    /*
    | Modo de operación del adaptador de Marketing hacia el gateway único de
    | WhatsApp (WhatsAppAgent). Item roadmap #203 — consolidación por fases,
    | Fase A (preparación técnica en DEV, aprobada para ejecución autónoma).
    |
    | - legacy  (default): el listener no hace nada (no-op total). El bot de
    |   ventas de Marketing sigue operando 100% por su webhook propio
    |   (`/webhooks/marketing/evolution` → ProcessIncomingMessageJob).
    | - shadow: el listener observa el evento SOLO para comparar (logging),
    |   nunca responde al cliente. Requiere aprobación aparte (#203-B).
    | - unified: reservado para el cutover supervisado (#203-C). NO
    |   implementado todavía a propósito.
    |
    | Nota importante: aunque este modo cambie, el listener solo recibe
    | eventos si la instancia de Evolution de ventas está registrada en
    | `whatsapp_instances` y webhookeando al gateway único
    | (`/whatsapp/webhook/{slug}`). Hoy esa instancia apunta a
    | `/webhooks/marketing/evolution` (Marketing) — por lo tanto, en la
    | práctica, este listener permanece inerte hasta que se reconfigure el
    | webhook real, lo cual queda fuera de alcance de esta fase.
    */
    'mode' => env('WHATSAPP_MARKETING_GATEWAY_MODE', 'legacy'),
];
