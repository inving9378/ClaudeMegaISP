<?php

/*
|--------------------------------------------------------------------------
| Monitoreo de clientes dedicados — tráfico por VLAN (item #988)
|--------------------------------------------------------------------------
| Lo usa metricas:colectar. El ping NO vive aquí: sigue en mikrotik:sync-ping.
*/

return [

    // Interruptor maestro. Apagado por default: con false el schedule de metricas:colectar
    // ni se registra (mismo patrón que pagos.recurrentes_cron_enabled). Prenderlo en PROD
    // es decisión de Irving, después del dry-run del runbook (Fase 6).
    'activo' => (bool) env('METRICAS_ACTIVO', false),

    // De dónde salen las métricas: 'mikrotik' (contadores de la interfaz VLAN y ping desde el
    // router) o 'simulado' (solo DEV; bloqueado en producción, ver bases_produccion).
    'driver' => env('METRICAS_DRIVER', 'mikrotik'),

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

    // Tiempo máximo por router (después de esto, las IPs que falten quedan «omitido») y por
    // corrida completa (los routers que falten quedan «omitido»).
    'presupuesto_router_segundos'  => (int) env('METRICAS_PRESUPUESTO_ROUTER', 40),
    'presupuesto_corrida_segundos' => (int) env('METRICAS_PRESUPUESTO_CORRIDA', 50),

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

    // Cuánto se conserva: lecturas por minuto y promedios de 5 min. Estadísticas solo lo muestra;
    // la purga y los promedios los aplica la Fase 5.
    'retencion' => [
        'minuto_dias'         => 30,
        'promedio_5min_meses' => 13,
    ],

    // Bases de datos que son producción. El driver simulado y su seeder se niegan a correr
    // contra ellas aunque APP_ENV diga otra cosa (en PROD APP_ENV puede venir como local).
    'bases_produccion' => ['meganet_prod_claude', 'meganet_prod'],

];
