<?php

// Item #993 — ping externo desde el borde (sonda fuera de la red de Meganet).
// Apagado por default: sin sonda configurada el comando no hace nada.
return [
    'enabled'    => (bool) env('PING_EXTERNO_ENABLED', false),
    // Endpoint de la sonda: recibe POST {"ips":[...],"count":5} y responde
    // {"results":[{"ip":"x","avg_ms":1.2,"min_ms":1,"max_ms":2,"packet_loss":0}]}
    'probe_url'  => env('PING_EXTERNO_PROBE_URL'),
    'probe_token' => env('PING_EXTERNO_PROBE_TOKEN'),
    'count'      => (int) env('PING_EXTERNO_COUNT', 5),
    'timeout'    => (int) env('PING_EXTERNO_TIMEOUT', 60),
];
