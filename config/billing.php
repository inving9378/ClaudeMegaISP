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
    | Correo de facturación (Prefactura, item #497)
    |--------------------------------------------------------------------------
    | Al que la Prefactura remite para pedir la factura fiscal. No hay campo de
    | empresa para él (company_information.email es el de soporte); si se deja
    | vacío, se usa ese. Sin env() a propósito: config:auditar-env.
    */
    'correo_facturacion' => 'facturacion@meganett.com.mx',

    /*
    |--------------------------------------------------------------------------
    | Antigüedad máxima de correos pendientes (app:send-all-emails-command, #482)
    |--------------------------------------------------------------------------
    | Los pendientes más viejos que esto se ignoran (no se envían ni se borran).
    */
    'correos_pendientes_max_dias' => 7,

    /*
    |--------------------------------------------------------------------------
    | Proforma N días antes del corte (FAC-06, item #512)
    |--------------------------------------------------------------------------
    | Apagado = invoice:create-proformas exactamente como siempre (todas el día 25
    | del mes anterior). Encendido = cada cliente el día (corte − N), con N =
    | config_finance_notifications.delay_days de «proforma_invoice», días
    | naturales y sin recuperar cortes ya pasados. Encenderlo y fijar N es
    | decisión de Irving (FAC-06b, #513). Simulación: invoice:proformas-simular.
    */
    'proforma_anticipada' => (bool) env('BILLING_PROFORMA_ANTICIPADA', false),

    /*
    |--------------------------------------------------------------------------
    | CLABE virtual del cliente en la Prefactura (FAC-13, item #521)
    |--------------------------------------------------------------------------
    | Apagado: «Cómo pagar» muestra la cuenta de la empresa + la referencia MEG,
    | como siempre. Encendido: si el cliente tiene una CLABE virtual ACTIVA y con
    | dígito de control válido (payment_clabes), se muestra esa; si no, la de
    | siempre. Encender solo con la certificación de OpenPay (decisión de Irving).
    */
    'clabe_virtual_proforma' => (bool) env('BILLING_CLABE_VIRTUAL_PROFORMA', false),

    /*
    |--------------------------------------------------------------------------
    | Reactivación gradual de envíos automáticos (FAC-08, item #515)
    |--------------------------------------------------------------------------
    | Segunda compuerta DETRÁS de billing_config.envio_pausado (que no cambia):
    | cerrado (default, no sale nada) → piloto (clientes_piloto) → zona (piloto +
    | colonias/municipios) → todos. Límites por corrida y por día; la corrida se
    | detiene al llegar a max_errores_por_corrida. WhatsApp se aprueba aparte.
    | Cada avance de etapa lo decide y aplica Irving. Estado y verificación DNS:
    | php artisan billing:reactivacion-estado
    */
    /*
    |--------------------------------------------------------------------------
    | Documento por WhatsApp (FAC-09, item #516)
    |--------------------------------------------------------------------------
    | API oficial de Meta, plantilla de utilidad con el PDF como encabezado
    | (propuestas en config/whatsapp_templates.php; NO sometidas a Meta). Apagado.
    | Además necesita la aprobación de WhatsApp en la reactivación (FAC-08).
    */
    'whatsapp_documento' => [
        'activo'               => (bool) env('BILLING_WHATSAPP_DOCUMENTO', false),
        'linea'                => env('BILLING_WHATSAPP_DOCUMENTO_LINEA'),   // slug de la línea Meta
        'plantilla_prefactura' => 'prefactura_documento',
        'plantilla_factura'    => 'factura_pagada_documento',
        'idioma'               => 'es_MX',
    ],

    'reactivacion' => [
        'etapa'                   => env('BILLING_REACTIVACION_ETAPA', 'cerrado'),
        'clientes_piloto'         => env('BILLING_REACTIVACION_CLIENTES', ''),
        'colonias'                => env('BILLING_REACTIVACION_COLONIAS', ''),
        'municipios'              => env('BILLING_REACTIVACION_MUNICIPIOS', ''),
        'limite_por_corrida'      => (int) env('BILLING_REACTIVACION_LIMITE_CORRIDA', 20),
        'limite_diario'           => (int) env('BILLING_REACTIVACION_LIMITE_DIARIO', 100),
        'max_errores_por_corrida' => (int) env('BILLING_REACTIVACION_MAX_ERRORES', 3),
        'whatsapp'                => (bool) env('BILLING_REACTIVACION_WHATSAPP', false),
    ],

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
