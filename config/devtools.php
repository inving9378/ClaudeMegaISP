<?php

return [
    /*
     * URL de ttyd (terminal web) usada por DevToolsController::resolveTtydUrl().
     * Vacío por default: el proxy nginx /ttyd/ sirve ttyd same-origin (evita la
     * restricción cross-origin que impedía acceder a window.term). TTYD_URL en
     * .env permite override (ej. dev remoto en otro host).
     */
    'ttyd_url' => env('TTYD_URL', ''),
];
