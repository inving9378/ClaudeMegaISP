<?php

return [
    /*
     * ID del comercio (se expone en el navegador junto con public_key — es correcto).
     * La private_key NUNCA debe aparecer en frontend ni en ningún archivo versionado.
     */
    'id'          => env('OPENPAY_ID'),
    'public_key'  => env('OPENPAY_PUBLIC_KEY'),
    'private_key' => env('OPENPAY_PRIVATE_KEY'),

    /*
     * true  → modo sandbox (para desarrollo y pruebas con tarjetas de prueba)
     * false → modo producción (solo activar tras completar certificación OpenPay)
     */
    'sandbox'     => (bool) env('OPENPAY_SANDBOX', false),

    /*
     * Gate de emisión de CLABE virtual (kill-switch global, safe-by-default).
     *
     * false → createClabe() lanza ClabeEmissionDisabledException ANTES de tocar
     *         OpenPay. Evita emitir CLABEs placeholder no enrutables (sandbox/stub)
     *         que colisionan en el índice único de payment_clabes.
     * true  → permite emitir, PERO solo si el provider NO está en sandbox y tiene
     *         credenciales reales (ver OpenPayService::isNonRoutable). Activar solo
     *         tras completar la certificación OpenPay y poner OPENPAY_SANDBOX=false.
     */
    'clabe_emission_enabled' => (bool) env('OPENPAY_CLABE_EMISSION_ENABLED', false),
];
