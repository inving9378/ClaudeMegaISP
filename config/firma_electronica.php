<?php

/**
 * Firma electrónica (item #565). Las credenciales de un proveedor real viven en el
 * Hub (api_integrations), nunca aquí ni en el repo.
 */
return [
    'proveedor' => env('FIRMA_PROVEEDOR', 'sandbox'),
    'webhook_secreto' => env('FIRMA_WEBHOOK_SECRETO', ''),
    'token_ttl_horas' => (int) env('FIRMA_TOKEN_TTL_HORAS', 72),
    'otp_ttl_minutos' => (int) env('FIRMA_OTP_TTL_MIN', 10),
    'otp_max_intentos' => (int) env('FIRMA_OTP_MAX_INTENTOS', 5),
    // Interruptor del correo al firmante (Hub Correo). Apagado por defecto.
    'correo_activo' => (bool) env('FIRMA_CORREO_ACTIVO', false),
];
