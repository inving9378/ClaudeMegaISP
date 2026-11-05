<?php

/*
 * Seguridad V1.64.1 (2026-10-22). Solo se fija esta clave; el resto de la configuración de
 * Ignition sale del paquete (mergeConfigFrom). /_ignition/execute-solution no pide sesión y
 * puede regenerar APP_KEY o correr migraciones. AppServiceProvider ya lo apaga cuando corre el
 * candado de migraciones (APP_ENV=local, item #171); esto lo deja apagado en cualquier entorno
 * salvo que el .env lo pida.
 */
return [
    'enable_runnable_solutions' => env('IGNITION_ENABLE_RUNNABLE_SOLUTIONS', false),
];
