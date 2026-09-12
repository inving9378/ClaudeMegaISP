<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ventana de retención de notificaciones de cobro
    |--------------------------------------------------------------------------
    | Horas mínimas que deben pasar antes de enviar un documento por correo.
    | Este valor puede ser mayor pero nunca menor a 12 h (el modelo y el
    | command validan el piso). La fuente de verdad es billing_config en BD;
    | este valor sirve de fallback inicial.
    */
    'notification_window_hours' => env('BILLING_NOTIFICATION_WINDOW_HOURS', 12),

    /*
    |--------------------------------------------------------------------------
    | Directorio de almacenamiento de PDFs generados
    |--------------------------------------------------------------------------
    */
    'pdf_disk'          => 'local',
    'pdf_path_prefix'   => 'billing/pdf',

    /*
    |--------------------------------------------------------------------------
    | Dual-write invoices (roadmap #632/#721)
    |--------------------------------------------------------------------------
    | Cuando está ON, los puntos de cobro que marcan client_invoices.estado=
    | 'Pagado' además reflejan el estado en su fila espejo de `invoices`
    | (creándola si no existe). client_invoices sigue siendo la fuente de
    | verdad; invoices es solo espejo mientras dure la transición. Default
    | OFF — activar es una decisión aparte de Irving (Fase 5 del plan).
    */
    'dual_write_invoices' => env('BILLING_DUAL_WRITE_INVOICES', false),

    /*
    |--------------------------------------------------------------------------
    | Respaldo por WhatsApp cuando el cliente no tiene email (item #9991031)
    |--------------------------------------------------------------------------
    | Con el flag OFF (default), `billing:send-pending-notifications` se
    | comporta EXACTAMENTE igual que hoy: si la notificación no tiene email,
    | marca error y la salta. Con el flag ON, antes de marcar error intenta
    | resolver un teléfono de la ficha (phone/phone2/phone3) y encolar un
    | recordatorio de texto por el gateway ÚNICO de WhatsApp. Kill-switch tipo
    | pagos: mismo patrón que DOMICILIACION_COBRO_LIVE_ENABLED — NO activar en
    | prod sin decisión explícita de Irving.
    */
    'whatsapp_fallback_enabled' => (bool) env('BILLING_WHATSAPP_FALLBACK_ENABLED', false),
];
