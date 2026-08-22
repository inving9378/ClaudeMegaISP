<?php

/*
 * Item roadmap #1020 (sub-item 4/5 de #1012) — clasificación de tablas por criticidad para
 * la ventana de reversibilidad (los umbrales numéricos viven en la tabla de BD
 * `release_reversibility_thresholds`, editable sin deploy; esto solo dice a qué balde cae
 * cada tabla). Por patrón de nombre, no lista exhaustiva — evita mantener un catálogo de
 * ~300 tablas a mano. Ante la duda una tabla cae en 'media' (default), nunca se asume 'baja'
 * sin estar declarada explícitamente (frontera dura: dinero/permisos/auth nunca deben
 * degradarse a un umbral laxo por un patrón que no matcheó).
 */
return [
    'critica' => [
        'payment', 'pago', 'invoice', 'factura', 'transaction', 'cobranza',
        'permission', 'role', 'password', 'token', 'client_main_information',
        'openpay', 'domiciliacion', 'oauth',
    ],

    'baja' => [
        'migrations', 'cache', 'jobs', 'failed_jobs', 'job_batches', 'sessions',
        'telescope_entries', 'activity_log', 'states', 'municipalities', 'colonies',
        'password_reset', 'notifications',
    ],
];
