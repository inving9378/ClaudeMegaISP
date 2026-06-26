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

];
