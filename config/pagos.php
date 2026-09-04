<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Portal de Pago Meganet — conciliación SPEI por CEP
    |--------------------------------------------------------------------------
    */

    /**
     * Modo de validación CEP:
     *   - 'banxico' : valida siempre contra el servicio público de Banxico.
     *   - 'manual'  : nunca llama a Banxico; todo reporte queda para revisión.
     *   - 'hybrid'  : intenta Banxico; si no concluye, cae a revisión manual.
     */
    'cep_mode' => env('PAGOS_CEP_MODE', 'hybrid'),

    /**
     * Vigencia (en días) de una liga de pago desde su creación.
     */
    'link_ttl_days' => (int) env('PAGOS_LINK_TTL_DAYS', 7),

    /**
     * Timeouts del BanxicoCepDriver (segundos). El servicio público de Banxico
     * no es una API formal: puede tardar, cambiar o aplicar anti-bot. Por eso
     * los timeouts son cortos y el driver es defensivo (1 reintento, y ante
     * cualquier falla → resultado inconcluso → revisión manual, nunca excepción).
     */
    'cep_timeout_connect' => (int) env('PAGOS_CEP_TIMEOUT_CONNECT', 5),
    'cep_timeout_read'    => (int) env('PAGOS_CEP_TIMEOUT_READ', 10),

    /**
     * Endpoint del formulario de validación de CEP de Banxico (no documentado).
     */
    'cep_endpoint' => env('PAGOS_CEP_ENDPOINT', 'https://www.banxico.org.mx/cep/valida.do'),

    /**
     * Método de pago (method_of_payments.id) con el que se registra la
     * conciliación SPEI. 2 = "Transferencia Bancaria".
     */
    'payment_method_id' => (int) env('PAGOS_PAYMENT_METHOD_ID', 2),

    // Pendiente validación legal — NO implementar cobro de comisión a sub-ISPs sin autorización CNBV
    'medussa_fees_enabled' => (bool) env('PAGOS_MEDUSSA_FEES_ENABLED', false),

    /**
     * Kill-switch del cron de recurrencia asistida (pagos:enviar-recurrentes,
     * ver app/Console/Kernel.php). El comando NO cobra (genera ligas de pago y
     * las loguea para envío manual — no hay auto-débito), pero el schedule
     * diario solo se activa si esto es true. Mismo patrón que
     * domiciliacion.cobro_live_enabled: false por default, --dry-run siempre
     * funciona para simular sin este flag. Flip a true SOLO por decisión
     * explícita de Irving al activar el cron en el servidor de producción (.198).
     */
    'recurrentes_cron_enabled' => (bool) env('PAGOS_RECURRENTES_CRON_ENABLED', false),

    /**
     * Resumen diario de pagos:enviar-recurrentes (ligas generadas/fallidas +
     * montos), enviado por correo y/o WhatsApp. Vacíos por default: sin
     * destinatario configurado, el resumen queda solo en el log dedicado
     * (storage/logs/pagos-recurrentes.log).
     */
    'recurrentes_resumen_email'    => env('PAGOS_RECURRENTES_RESUMEN_EMAIL'),
    'recurrentes_resumen_whatsapp' => env('PAGOS_RECURRENTES_RESUMEN_WHATSAPP'),

];
