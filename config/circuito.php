<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Intervalo del cron del Circuito (espejo, NO controla el cron real)
    |--------------------------------------------------------------------------
    |
    | Espejo del crontab del ejecutor on-box (cada 30 min: la línea "cada-30" que corre
    | vuelta.sh). La Torre lo usa SOLO para estimar "próxima vuelta". Cambiarlo en el cron
    | real NO lo actualiza
    | automáticamente: si ajustas el crontab, ajusta también este valor (o CIRCUITO_INTERVAL_MIN).
    | Minutos, divisor de 60 (30, 20, 15, 10…).
    |
    */
    'interval_min' => (int) env('CIRCUITO_INTERVAL_MIN', 30),

    /*
    |--------------------------------------------------------------------------
    | Salvaguarda de pausa olvidada (#343)
    |--------------------------------------------------------------------------
    | Horas de pausa seguidas tras las que la Torre pinta el banner "¿reanudar?". Puramente
    | informativo — NUNCA reanuda solo (el kill switch #342 sigue siendo solo-humano).
    */
    'pausa_aviso_horas' => (float) env('CIRCUITO_PAUSA_AVISO_HORAS', 3),

    /*
    |--------------------------------------------------------------------------
    | Agente REVISOR (#338)
    |--------------------------------------------------------------------------
    |
    | Revisor adversarial que autoriza los B técnicos seguros (aprobado_revisor) para que el
    | circuito no se frene esperando a Irving en lo rutinario. El flag on/off vive en la tabla
    | `settings` (circuito_revisor, default OFF); aquí va el ALCANCE conservador y el modelo.
    |
    | `alcance.denylist`: si el título/módulo/plan del item menciona alguno de estos términos,
    | queda FUERA de alcance y se ESCALA sin gastar IA (frontera dura: dinero/seguridad/prod/
    | destructivo/negocio). Arranque estrecho: ante la duda, agrega términos, no los quites.
    |
    */
    'revisor' => [
        // MODELO ESCALONADO (#338): B rutinario → Sonnet; B difícil/borderline/baja-confianza → Opus
        // (2ª opinión); C → Opus arma un BRIEF de decisión para Irving. Opus SOLO en lo difícil/C
        // (con N=6, Opus en todo B quemaría límites).
        'model'         => env('CIRCUITO_REVISOR_MODEL', 'claude-sonnet-4-6'),      // rutina (compat)
        'model_routine' => env('CIRCUITO_REVISOR_MODEL', 'claude-sonnet-4-6'),
        'model_hard'    => env('CIRCUITO_REVISOR_MODEL_HARD', 'claude-opus-4-7'),
        'max_tokens'    => (int) env('CIRCUITO_REVISOR_MAX_TOKENS', 700),
        'brief_tokens'  => (int) env('CIRCUITO_REVISOR_BRIEF_TOKENS', 1100),
        // Perfil vivo de decisiones/preferencias de Irving, inlineado al prompt (menos falsos
        // positivos → menos ruido en su bandeja). Editable por Irving; sin secretos.
        'perfil_path'   => base_path('docs/perfil-decisiones-irving.md'),
        'alcance'    => [
            // FRONTERA DURA (si el título/módulo/plan menciona esto → escala SIN gastar IA).
            // Afinada (#338): se quitaron términos demasiado amplios que escalaban FALSOS POSITIVOS
            // ('rol ', 'roles', 'auth', 'banco', 'prod' bare) — la sensibilidad real la cubren
            // términos específicos (permiso/permisos/spatie, credencial/bcrypt, producción/deploy…).
            'denylist' => [
                // dinero / cobros
                'dinero', 'pago', 'cobro', 'factura', 'facturación', 'saldo', 'precio', 'tarifa',
                'openpay', 'spei', 'clabe', 'cargo', 'nómina', 'comisión',
                // seguridad / permisos / auth (específicos, no substrings que peguen de más)
                // NOTA: 'login' y 'token' REMOVIDOS — falsos positivos mecánicos por substring
                // ('login' vive en `login_user`, campo de identidad; 'token' pega CSRF/sesión/API
                // rutinarios). La frontera real de auth la cubren permiso/permisos/spatie/password/
                // credencial/seguridad/idor/bcrypt + el prompt del revisor (que distingue registrar
                // un permiso nuevo —rutina— de cambiar permisos/roles existentes —Irving—).
                'permiso', 'permisos', 'spatie', 'password',
                'contraseña', 'credencial', 'secret', 'seguridad', 'idor', 'bcrypt',
                // producción / despliegue
                'producción', 'deploy', 'despliegue', 'remote:deploy', '.env',
                // datos destructivos
                'migrate:fresh', 'drop ', 'truncate', 'delete from', 'borrado masivo', 'destructiv',
                // negocio / estrategia / arquitectura
                'negocio', 'estrategia', 'arquitectura', 'multi-tenant', 'tenant',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ejecución en PARALELO (#334 Fase 1)
    |--------------------------------------------------------------------------
    | N = cuántas sesiones/worktrees corren a la vez. Runtime en `settings`
    | (circuito_paralelismo); aquí el default. Box de dev = 4 cores/17GB → N=6 seguro
    | (con semáforo de builds). `max_builds` = builds npm simultáneos máx (CPU de 4 cores).
    */
    'paralelismo'      => (int) env('CIRCUITO_PARALELISMO', 6),
    'max_builds'       => (int) env('CIRCUITO_MAX_BUILDS', 3),

    /*
    | Nombres por default de los workers del equipo (wt-1..wt-N). Persisten y son
    | RENOMBRABLES por Irving (override en `settings` → circuito_worker_nombres). Dan un
    | ROSTER legible: "trabajado por Ada" en vez de "wt-3".
    |
    | #430: mapa fijo wt-1=Maya, wt-2=Leo, wt-3=Sofía, wt-4=Iván, wt-5=Nora, wt-6=Beto.
    | El avatar de cada slot vive en public/images/circuito/{sid}.png (wt-1.png…wt-6.png);
    | el NOMBRE es editable, el avatar va por SLOT (no por nombre) para que renombrar no rompa la cara.
    */
    'worker_nombres'   => ['Maya', 'Leo', 'Sofía', 'Iván', 'Nora', 'Beto'],

    /*
    |--------------------------------------------------------------------------
    | AUTOPILOT (#507 sub-paso 2)
    |--------------------------------------------------------------------------
    |
    | Capa que corre DESPUÉS del brief del Revisor y ANTES de la bandeja: si el brief trae una
    | opción `recomendada` con datos estructurados suficientes (sub-paso 1), la toma sola y manda el
    | item a la cola ejecutable, en vez de esperar a Irving. Solo le consulta lo indispensable.
    |
    | La decisión se registra en el `log` del item con `decidido_por='autopilot'` (confianza,
    | reversibilidad y motivo incluidos) → trazable y reversible como cualquier decisión humana.
    |
    | KILL SWITCH: es el MISMO de siempre (`circuito_pausado`, botón de la Torre). En pausa el
    | autopilot no decide nada; lo que ya estaba en vuelo termina.
    |
    | Endurecer o relajar NO requiere redeploy: son flags.
    |
    */
    'autopilot' => [
        'enabled'             => (bool) env('CIRCUITO_AUTOPILOT', true),

        // Nivel MÁXIMO que el autopilot puede decidir solo (A < B < C).
        // DECISIÓN DE IRVING (2026-08-04): tope en 'B'. El nivel C es decisión de diseño/negocio y
        // SIEMPRE cae en su bandeja — es la regla dura de CLAUDE.md ("Nivel C: jamás sin decisión
        // de Irving"). Subirlo a 'C' es un cambio de una línea el día que él lo decida (y entonces
        // hay que actualizar también esa regla en CLAUDE.md, si no quedan contradiciéndose).
        'max_nivel'           => env('CIRCUITO_AUTOPILOT_MAX_NIVEL', 'B'),

        // Exigir que la opción recomendada esté marcada `reversible: true`. Aplica a B y C; el
        // nivel A ya es reversible por DEFINICIÓN (aditivo, no toca dinero/permisos/auth/prod).
        'requiere_reversible' => (bool) env('CIRCUITO_AUTOPILOT_REVERSIBLE', true),

        // Confianza MÍNIMA de la opción recomendada: alta | media | baja. Con 'alta' (default) una
        // opción sin el dato explícito NO califica → los items de briefs viejos van a la bandeja.
        'umbral_confianza'    => env('CIRCUITO_AUTOPILOT_CONFIANZA', 'alta'),

        // Minutos de gracia entre que se escribe el brief y el autopilot decide (ventana para que
        // Irving alcance a vetar). DECISIÓN DE IRVING: 0 = sin ventana, decide de inmediato.
        'ventana_gracia'      => (int) env('CIRCUITO_AUTOPILOT_GRACIA', 0),
    ],

    /*
    | #432 Fase 3 — Brief COMPLETO (multi-pregunta). ON: la bandeja usa la columna JSON `preguntas`
    | (varias preguntas por item) y la escalación las puebla TODAS de una. OFF: fallback al modelo
    | viejo de una sola `opciones`/`opcion_elegida`. Un item SIN `preguntas` cae al fallback aunque
    | esté ON, así que activar esto NO rompe los items existentes.
    */
    'multi_pregunta'   => (bool) env('CIRCUITO_MULTI_PREGUNTA', true),

];
