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
];
