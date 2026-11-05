<?php

/*
|--------------------------------------------------------------------------
| Monitoreo de clientes dedicados — tráfico por VLAN (item #988)
|--------------------------------------------------------------------------
| Lo usa metricas:colectar. El ping NO vive aquí: sigue en mikrotik:sync-ping.
*/

return [

    // Override opcional. El interruptor es «Monitoreo de dedicados» en Configuración (encendido de
    // inicio); METRICAS_ACTIVO solo se define para forzarlo en el .env (ver AjusteMonitoreo).
    'activo' => env('METRICAS_ACTIVO'),

    // De dónde salen las métricas: 'mikrotik' (contadores de la interfaz VLAN y ping desde el
    // router) o 'simulado' (solo DEV; bloqueado en producción). Sin definir: el real en producción o
    // con laboratorio, el simulado en DEV sin laboratorio (EntornoMetricas::driverPorDefecto).
    'driver' => env('METRICAS_DRIVER'),

    // Router de laboratorio: el único al que se conecta el colector fuera de producción. Sin esto, a
    // ninguno. Viven en .env; aquí para que se lean también con la configuración en caché.
    'laboratorio' => [
        'host'     => env('MIKROTIK_DEV_IP'),
        'usuario'  => env('MIKROTIK_DEV_LOGIN'),
        'password' => env('MIKROTIK_DEV_PASSWORD'),
        'puerto'   => env('MIKROTIK_DEV_PORT'),
    ],

    // Ping desde el MikroTik a cada IP del cliente: pocos paquetes con intervalo corto para que
    // todo quepa en el ciclo de 60 s (formato de intervalo de RouterOS).
    'ping' => [
        'paquetes'  => (int) env('METRICAS_PING_PAQUETES', 3),
        'intervalo' => env('METRICAS_PING_INTERVALO', '00:00:00.200'),
    ],

    // Conexión a la API del MikroTik: timeout corto, para que un router que no contesta no se
    // coma el minuto.
    'mikrotik' => [
        'timeout_segundos' => (int) env('METRICAS_MIKROTIK_TIMEOUT', 5),
    ],

    // «Probar lectura» de la ventana Datos de red: tiempo máximo y segundos entre las dos lecturas de
    // contadores con las que calcula la bajada y la subida.
    'probar' => [
        'presupuesto_segundos' => 15,
        'muestra_segundos'     => 5,
    ],

    // Tiempo máximo por router (después de esto, las IPs que falten quedan «omitido») y por
    // corrida completa (los routers que falten quedan «omitido»).
    'presupuesto_router_segundos'  => (int) env('METRICAS_PRESUPUESTO_ROUTER', 40),
    'presupuesto_corrida_segundos' => (int) env('METRICAS_PRESUPUESTO_CORRIDA', 50),

    // Solo el driver simulado de DEV: para ver en pantalla un router que contesta otra identidad
    // (título => identidad) y una VLAN que no existe o está repetida.
    'simulado' => [
        'identidades'        => ['Router de pruebas B' => 'router-b-sitio', 'Router de pruebas C' => 'router-c-cambiado'],
        'vlan_no_encontrada' => 4094,
        'vlan_repetida'      => 4093,
    ],

    // Si entre dos lecturas pasan más de estos segundos, el hueco no se promedia: la lectura
    // nueva queda como base y ese minuto sale sin dato (estado hueco_largo).
    'hueco_max_segundos' => (int) env('METRICAS_HUECO_MAX_SEGUNDOS', 300),

    // Una diferencia que dé más que esto no es tráfico real (contador corrupto o cambiado de
    // interfaz): el minuto sale sin dato (estado lectura_invalida). 40 Gbps por default.
    'bps_maximo' => (int) env('METRICAS_BPS_MAXIMO', 40_000_000_000),

    // Bloque más grande que se acepta en «Datos de red del dedicado» (las IPs a monitorear son
    // solo del bloque y la ventana las lista todas).
    'bloque_prefijo_minimo' => 24,

    // En Estadísticas, si la última lectura de un servicio es más vieja que esto, el estado es
    // «Sin datos» (el colector no tiene lectura reciente; no es una caída).
    'sin_datos_segundos' => 180,

    // Eventos del «Histórico del servicio». Pérdida alta: pérdida de los equipos que SÍ responden
    // (uno que no responde ya es «Degradado» o «Sin respuesta»). Latencia alta: más de N veces el
    // promedio de las 24 h anteriores y más del mínimo en ms (las latencias son sub-milisegundo).
    'eventos' => [
        'perdida_alta_pct'        => 20,
        'perdida_alta_minutos'    => 5,
        'latencia_alta_factor'    => 10,
        'latencia_alta_minimo_ms' => 2,
    ],

    // Cuánto se conserva (lo aplica metricas:purgar, a diario): detalle por minuto y ping por IP,
    // promedios de 5 min y latidos del colector.
    'retencion' => [
        'minuto_dias'         => 30,
        'promedio_5min_meses' => 13,
        'latidos_dias'        => 30,
    ],

    // Bases de datos que son producción, aunque APP_ENV diga otra cosa (ver EntornoMetricas): con
    // ellas el colector va al router registrado y el driver simulado se niega a correr.
    'bases_produccion' => ['meganet_prod_claude', 'meganet_prod'],

];
