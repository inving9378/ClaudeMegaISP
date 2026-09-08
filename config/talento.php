<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Semana de pago (PayWeek)
    |--------------------------------------------------------------------------
    | Punto único de configuración de la ventana de la semana de pago del
    | motor de compensación de Talento. Consumido por App\Modules\Addons\
    | Talento\Support\PayWeek.
    |
    | Ventana NUEVA (instantes >= cutover): Sábado 18:00 -> Sábado 18:00.
    |   Un trabajo con validated_at <= Sáb 18:00 cierra la semana; después -> siguiente.
    | Ventana LEGACY (instantes < cutover): Sábado 00:00 -> Viernes 23:59 (día completo).
    |   Replica el motor histórico para que las semanas ya pagadas sean reproducibles
    |   e INTACTAS (cambio forward-only).
    |
    | ⚠️ 'cutover' DEBE ser un sábado a las 18:00 (día de pago / corte).
    |    Go-live confirmado por Irving: sábado 2026-07-11 18:00. La semana en curso
    |    (que incluye el sábado 2026-07-04) se paga completa con el método LEGACY;
    |    la ventana NUEVA arranca limpia el 11 sin partir ninguna semana.
    |    Override por entorno: TALENTO_PAYWEEK_CUTOVER="YYYY-MM-DD HH:MM:SS" (un sábado 18:00).
    */

    'pay_week' => [
        'cutover'       => env('TALENTO_PAYWEEK_CUTOVER', '2026-07-11 18:00:00'),
        'cutoff_hour'   => (int) env('TALENTO_PAYWEEK_CUTOFF_HOUR', 18),
        'cutoff_minute' => (int) env('TALENTO_PAYWEEK_CUTOFF_MINUTE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Puente Vendedores→Talento — comisiones espejo (item #9990605/#9990609)
    |--------------------------------------------------------------------------
    | Kill switch del puente. Fase 1a (#9990609) crea la tabla talento_comisiones_espejo
    | pero NO lee esta clave todavía en ningún lado -- es puramente inerte. La Fase 1b
    | será quien la consulte antes de escribir/leer comisiones espejo. Default OFF a
    | propósito (decisión Irving #9990605/q3): activar es una decisión explícita aparte.
    */
    'vendedores_espejo_enabled' => env('TALENTO_VENDEDORES_ESPEJO_ENABLED', false),

];
