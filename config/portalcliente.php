<?php

return [
    // Dominio dedicado del Portal Cliente (item roadmap #144). En DEV se prueba
    // vía /etc/hosts (sin DNS público); en PROD será el registro A real.
    'domain' => env('PORTAL_CLIENTE_DOMAIN', 'portal.meganet.mx'),
];
