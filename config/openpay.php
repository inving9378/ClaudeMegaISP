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
];
