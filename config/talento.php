<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sesión de la app móvil (item #693)
    |--------------------------------------------------------------------------
    | token_ttl_minutes: vigencia del token Sanctum 'talento-mobile' (por token, NO global:
    | MegaFamilia/catálogo siguen sin vencimiento). Vencido => 401 y la app vuelve al login.
    | login_max_attempts / login_decay_seconds: límite de intentos fallidos por usuario+IP.
    */
    'mobile' => [
        'token_ttl_minutes'  => (int) env('TALENTO_MOBILE_TOKEN_TTL_MINUTES', 60 * 24 * 30),
        'login_max_attempts' => (int) env('TALENTO_MOBILE_LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_seconds' => (int) env('TALENTO_MOBILE_LOGIN_DECAY_SECONDS', 300),
    ],

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
    | ⚠️ 'cutover' DEBE ser un sábado a las 18:00 (día de pago / corte).
    |    Go-live confirmado por Irving: sábado 2026-07-11 18:00. La semana en curso
    |    (que incluye el sábado 2026-07-04) se paga completa con el método LEGACY;
    |    la ventana NUEVA arranca limpia el 11 sin partir ninguna semana.
    |    Override por entorno: TALENTO_PAYWEEK_CUTOVER="YYYY-MM-DD HH:MM:SS" (un sábado 18:00).
    */

    'pay_week' => [
        'cutover'       => env('TALENTO_PAYWEEK_CUTOVER', '2026-07-11 18:00:00'),
        'cutoff_hour'   => (int) env('TALENTO_PAYWEEK_CUTOFF_HOUR', 18),
        'cutoff_minute' => (int) env('TALENTO_PAYWEEK_CUTOFF_MINUTE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Puente Vendedores→Talento — comisiones espejo (item #9990605/#9990609)
    |--------------------------------------------------------------------------
    | Kill switch del puente. Fase 1a (#9990609) crea la tabla talento_comisiones_espejo
    | pero NO lee esta clave todavía en ningún lado -- es puramente inerte. La Fase 1b
    | será quien la consulte antes de escribir/leer comisiones espejo. Default OFF a
    | propósito (decisión Irving #9990605/q3): activar es una decisión explícita aparte.
    */
    'vendedores_espejo_enabled' => env('TALENTO_VENDEDORES_ESPEJO_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Middleware global de bloqueo proporcional por documento tipo firma (item #9990804)
    |--------------------------------------------------------------------------
    | Kill switch. Default OFF a propósito (decisión Irving, q4): se activa gradual y se
    | puede apagar sin deploy si algo se comporta mal. Cuando está en true,
    | App\Modules\Addons\Talento\Middleware\BloqueoDocumentoPendienteMiddleware
    | intercepta TODAS las escrituras (POST/PUT/PATCH/DELETE) del guard web: si el
    | colaborador autenticado tiene un talento_employee_documents.status='pendiente' de un
    | template tipo='firma' cuyo modulos_bloqueados intersecta el módulo de la ruta actual,
    | responde 423 en vez de dejar pasar la petición. Bypass de emergencia: permiso Spatie
    | 'talento.bypass_bloqueo_firma' (solo super-administrator + DESARROLLADOR).
    */
    'bloqueo_firma_pendiente_enabled' => env('TALENTO_BLOQUEO_FIRMA_PENDIENTE_ENABLED', false),

    /*
    | Catálogo módulo->prefijos de ruta que consume el middleware de arriba para resolver
    | modulos_bloqueados (cuando NO es el sentinel '*' = TODO lo operativo). Un mismo prefijo
    | puede repetirse en varios módulos si conceptualmente pertenece a ambos.
    */
    'modulos_operativos' => [
        'prospectos'  => ['/crm'],
        'ventas'      => ['/sellers', '/vendedores'],
        'comisiones'  => ['/sellers', '/vendedores'],
    ],


    /*
    | Órdenes atrasadas (item #1158, pedido de Irving 2026-11-03): una orden pendiente o en curso
    | cuyo día ya pasó no se queda atrás. «Mi día» (web y app) la sigue mostrando arriba, en rojo,
    | hasta que se termine o se reprograme con un motivo. Apagarlo devuelve «Mi día» a solo lo de hoy.
    */
    'ordenes_atrasadas_en_mi_dia' => env('TALENTO_ORDENES_ATRASADAS_EN_MI_DIA', true),

];
