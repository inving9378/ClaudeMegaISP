<?php

/*
|--------------------------------------------------------------------------
| Red en solo lectura — routers MikroTik y OLTs
|--------------------------------------------------------------------------
| Con datos de producción en dev, un cambio hecho en dev llegaría a los routers y OLTs reales y
| afectaría a clientes. Con el candado puesto, dev solo consulta: MikroTik acepta print, monitor
| y ping; SmartOLT solo GET; la OLT Huawei solo lo que ya deja pasar su ReadOnlyGuard. Lo aplica
| App\Support\Red\SoloLectura.
*/

return [

    // Sin definir = automático: puesto fuera de producción y quitado en producción (basta una de
    // las señales de SoloLectura::esProduccion). true/false lo fuerza. Para probar escrituras contra
    // el router de laboratorio (MIKROTIK_DEV_*), ponerlo en false.
    'solo_lectura' => env('RED_SOLO_LECTURA'),

    // Fuera de producción las conexiones a MikroTik se desvían al router de laboratorio
    // (RouterConnection::destinoConexion) y el colector de métricas no toca routers reales. Con esto
    // en true, y solo si el candado está puesto, dev se conecta a los routers reales de la base para
    // leerlos (p. ej. monitorear dedicados con la base de producción cargada).
    'routers_reales_en_dev' => filter_var(env('RED_ROUTERS_REALES_EN_DEV', false), FILTER_VALIDATE_BOOLEAN),

];
