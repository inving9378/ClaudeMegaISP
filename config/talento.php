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
    | ⚠️ 'cutover' DEBE ser un sábado a las 18:00 (día de pago / corte). Confirmar con
    |    Irving la fecha real de go-live antes de liquidar con la ventana nueva.
    */

    'pay_week' => [
        'cutover'       => env('TALENTO_PAYWEEK_CUTOVER', '2026-07-04 18:00:00'),
        'cutoff_hour'   => (int) env('TALENTO_PAYWEEK_CUTOFF_HOUR', 18),
        'cutoff_minute' => (int) env('TALENTO_PAYWEEK_CUTOFF_MINUTE', 0),
    ],

];
