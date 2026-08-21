<?php

return [
    /*
     * Fase 1: sólo 'paper' (simulado). El código debe lanzar excepción si
     * detecta 'live' — no existe ruta de ejecución con dinero real todavía
     * (ítem roadmap #596, sección 2 guardrail 6).
     */
    'modo' => env('INVERSIONES_MODO', 'paper'),

    /*
     * Kill switch global: si es true, RiskEngineService rechaza toda orden
     * sin excepción y el scheduler no corre ningún comando del módulo.
     */
    'kill_switch' => (bool) env('INVERSIONES_KILL_SWITCH', false),

    /*
     * Techo absoluto de exposición del sistema (perímetro de capital,
     * sección 3). Ninguna clase del módulo puede escribir este valor:
     * sólo se cambia editando el .env a mano.
     */
    'capital_asignado' => (float) env('INVERSIONES_CAPITAL_ASIGNADO', 10000),

    'comision_pct' => (float) env('INVERSIONES_COMISION_PCT', 0.0000),
    'slippage_pct' => (float) env('INVERSIONES_SLIPPAGE_PCT', 0.0500),
    'moneda_base'  => env('INVERSIONES_MONEDA_BASE', 'USD'),
    'isr_tasa'     => (float) env('INVERSIONES_ISR_TASA', 0.10),

    'quant' => [
        'url'   => env('QUANT_URL', 'http://127.0.0.1:8788'),
        'token' => env('QUANT_TOKEN'),
    ],

    'thomas' => [
        'token' => env('THOMAS_TOKEN'),
    ],

    'alpaca' => [
        'key'       => env('ALPACA_KEY'),
        'secret'    => env('ALPACA_SECRET'),
        'base_url'  => env('ALPACA_BASE_URL', 'https://paper-api.alpaca.markets'),
        'data_url'  => env('ALPACA_DATA_URL', 'https://data.alpaca.markets'),
    ],
];
