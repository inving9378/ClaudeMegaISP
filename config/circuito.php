<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modo CONTINUO — sin rondas (#507 sub-paso 3)
    |--------------------------------------------------------------------------
    |
    | El circuito ya NO trabaja por rondas con ventana de tiempo: `circuito:scheduler` corre cada
    | minuto y lanza una vuelta POR ITEM en el primer slot libre, y una terminal que termina jala el
    | siguiente de la cola (`circuito:claim-next`) sin esperar a nadie.
    |
    | Con esto en true la Torre deja de anunciar "próxima vuelta" (ver `proximaVueltaAt`), que era
    | una ficción heredada del modelo viejo. Ponerlo en false revive esa estimación.
    |
    */
    'continuo' => (bool) env('CIRCUITO_CONTINUO', true),

    /*
    |--------------------------------------------------------------------------
    | Intervalo del cron del Circuito — DEPRECADO (#507 sub-paso 3)
    |--------------------------------------------------------------------------
    |
    | DEPRECADO: solo se usa si `continuo` está en false. Ya no describe cómo trabaja el circuito
    | (el cron real dispara cada minuto, no cada 30). Se conserva para poder volver atrás.
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
    | Footprint DESCONOCIDO — ronda dedicada DIFERIDA (Fase 2A.1)
    |--------------------------------------------------------------------------
    |
    | Un item con `modulo` vacío/'Sin clasificar' tiene footprint desconocido: podría tocar cualquier
    | archivo, así que por diseño (#432 B2) corre SOLO. Eso no se toca — lo que cambia es CUÁNDO se
    | despacha.
    |
    | Antes se tomaba en cuanto ordenaba primero con la flota quieta y se cortaba la ronda ahí mismo,
    | así que un solo item sin clasificar se llevaba las 6 terminales aunque detrás de él hubiera
    | trabajo módulo-disjunto listo. Con esto en `true`, el desconocido se DIFIERE al cierre del
    | barrido: sólo se despacha si no hubo nada más que despachar esta ronda (o si es `urgente`, que
    | conserva su prioridad de `ordenCola()`).
    |
    | #212 (2026-08-28, decisión Irving) — YA NO exige la flota completamente quieta. Antes de este
    | fix, la ronda dedicada además requería `nada en vuelo` → con 6 terminales en pool continuo casi
    | siempre hay ALGO corriendo, así que los desconocidos (5 urgentes del incidente P0) nunca
    | alcanzaban turno: inanición total. Ahora se despacha en su propio slot aunque otros módulos
    | conocidos estén en vuelo (aditivo seguro por default); la única serialización que se conserva
    | es contra OTRO desconocido ya en vuelo (`desconocidoEnVuelo()`) — nunca dos a la vez. La
    | colisión real contra trabajo conocido, si la hay, la atrapa `detectarColisionesEnVuelo()`
    | (diff de archivos real, post-hoc).
    |
    | ⚠️ CONTRAPARTIDA: con cola sostenida de trabajo módulo-disjunto NO urgente, el desconocido puede
    | seguir esperando varias rondas (solo se prefiere sobre `$out` si es urgente). El desatasco real
    | es CLASIFICARLO (`circuito:clasificar-modulo`); el detector `sin_clasificar` del auditor ya
    | emite el item que lo pide. Ponerlo en `false` restaura el comportamiento legacy sin redeploy.
    |
    */
    'desconocido_diferido' => (bool) env('CIRCUITO_DESCONOCIDO_DIFERIDO', true),

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
    | queda FUERA de alcance y se ESCALA sin gastar IA (temas sensibles: dinero/seguridad/prod/
    | destructivo/negocio). ⚠️ Es el PREFILTRO PROPIO del Revisor, no "la" frontera dura del
    | circuito — esa es `jarvis.escalamiento` (`JarvisService::categoriaFronteraDura`), una lista
    | aparte con su propio criterio. Item #944 (2026-08-21): antes este bloque se llamaba a sí
    | mismo "frontera dura" a secas y confundía las dos listas. Arranque estrecho: ante la duda,
    | agrega términos, no los quites.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | VÁLVULA DE CONTEXTO — afloja el veredicto del keyword, nunca lo amplía
    |--------------------------------------------------------------------------
    | El matcher de términos no distingue mencionar un tema de tocarlo. Cuando pega, en vez de
    | sentenciar C directo se pregunta al modelo si el término se USA o solo se NOMBRA, y SOLO
    | puede aflojar. Si falla, se cae o tarda, queda el veredicto del keyword → el peor caso de
    | esta ruta es exactamente el comportamiento anterior a ella.
    |
    | Volumen medido antes de encenderla: 85 de 164 items triados en 30 días → ~2.8 llamadas/día.
    | `enabled = false` la apaga y el clasificador vuelve a ser 100% determinista.
    */
    'valvula_contexto' => [
        'enabled'    => (bool) env('CIRCUITO_VALVULA_CONTEXTO', true),
        // Hereda el modelo de rutina del revisor si no se fija uno: no se inventa un ID nuevo.
        'model'      => env('CIRCUITO_VALVULA_MODEL', env('CIRCUITO_REVISOR_MODEL', 'claude-sonnet-4-6')),
        // Clasificación binaria + una frase: no necesita más techo.
        'max_tokens' => (int) env('CIRCUITO_VALVULA_MAX_TOKENS', 300),
    ],

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
        // SÍ es fuente versionada: lo edita Irving a mano, el circuito NUNCA le escribe.
        'perfil_path'   => base_path('docs/perfil-decisiones-irving.md'),
        // Candidatos CRUDOS que el circuito captura solo (PerfilAprendizajeService). ESTADO de
        // runtime, no fuente: vive en storage/ (gitignored) por la misma razón que
        // `jarvis.consolidado.doc_path` — ensuciaba docs/ en cada vuelta y abortaba el deploy.
        'pendientes_perfil_path' => storage_path('app/circuito/pendientes-perfil-irving.md'),
        'alcance'    => [
            // PREFILTRO PROPIO DEL REVISOR (si el título/módulo/plan menciona esto → escala SIN
            // gastar IA). NO es la frontera dura de Jarvis — ver nota arriba.
            // Afinada (#338): se quitaron términos demasiado amplios que escalaban FALSOS POSITIVOS
            // ('rol ', 'roles', 'auth', 'banco', 'prod' bare) — la sensibilidad real la cubren
            // términos específicos (permiso/permisos/spatie, credencial/bcrypt, producción/deploy…).
            'denylist' => [
                // dinero / cobros
                'dinero', 'pago', 'cobro', 'factura', 'facturación', 'saldo', 'tarifa',
                'openpay', 'spei', 'clabe', 'cargo', 'nómina', 'comisión',
                // seguridad / permisos / auth (específicos, no substrings que peguen de más)
                // NOTA: 'login' y 'token' REMOVIDOS — falsos positivos mecánicos por substring
                // ('login' vive en `login_user`, campo de identidad; 'token' pega CSRF/sesión/API
                // rutinarios). La frontera real de auth la cubren permiso/permisos/spatie/password/
                // credencial/seguridad/idor/bcrypt + el prompt del revisor (que distingue registrar
                // un permiso nuevo —rutina— de cambiar permisos/roles existentes —Irving—).
                // NOTA: 'secret' y 'precio' MOVIDOS a `denylist_word` (#904) — ver ahí el porqué.
                'permiso', 'permisos', 'spatie', 'password',
                'contraseña', 'credencial', 'seguridad', 'idor', 'bcrypt',
                // producción / despliegue
                'producción', 'deploy', 'despliegue', 'remote:deploy', '.env',
                // datos destructivos
                'migrate:fresh', 'drop ', 'truncate', 'delete from', 'borrado masivo', 'destructiv',
                // negocio / estrategia / arquitectura
                'negocio', 'estrategia', 'arquitectura', 'multi-tenant', 'tenant',
            ],
            // #904 — Cortos/ambiguos: en modo flex (arriba) disparan como PREFIJO de una palabra real
            // NO relacionada (mismo defecto que motivó #865, sobreviviendo dentro de su propio fix).
            // Verificado contra diccionario es_ES (aspell): 'secret'->secretaria/secretario/
            // secretaría/secretariado/secretismo/secretor… (133 palabras reales); 'precio'->
            // precioso/preciosa/preciosidad/preciosismo (11 palabras reales). Van con `\b…\b` (match
            // exacto) y las flexiones legítimas se enumeran a mano — así no se pierde cobertura real
            // (secreto/secreta/precios) como sí pasaría con un simple `\bsecret\b`/`\bprecio\b` que
            // dejaría de cazar sus flexiones. Otros candidatos vistos ('cargo', 'saldo', 'pago') se
            // verificaron con casos reales y NO tienen colisión práctica (ver
            // DetectorTerminosPalabraCompletaTest) — se quedan en modo flex, sin mover de más.
            'denylist_word' => [
                'secret', 'secreto', 'secretos', 'secreta', 'secretas',
                'precio', 'precios',
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

    // #938 — límite real de una vuelta (lo aplica `timeout` en deploy/circuito/vuelta.sh vía
    // CIRCUITO_TIMEOUT). Expuesto aquí SOLO para que la Torre (pestaña Terminales) pinte el
    // reloj de cada terminal contra el mismo número real, sin duplicar el 600 a mano.
    'vuelta_timeout_seg' => (int) env('CIRCUITO_TIMEOUT', 600),

    // A partir de cuántos segundos una vuelta VIVA se considera COLGADA (2026-08-26). No basta con
    // que esté reparentada a init: el cron lanza TODAS sus vueltas desprendidas, así que PPID=1 es
    // el estado normal, no una anomalía. Antes la Torre pintaba rojo por PPID=1 a secas y titulaba
    // "DETENIDO POR: Ejecutor huérfano" con seis agentes trabajando bien — un rojo que suena en
    // operación normal enseña a ignorar el tablero. Lo que SÍ es anomalía es sobrevivir a su propio
    // timeout: el `timeout` de vuelta.sh mata al agente hijo, no al bucle padre, y el 22-ago una
    // vuelta corrió 1d21h lanzando un agente cada 3.7 s. 3600 = 6x el timeout nominal: holgado para
    // el arranque + integración + limpieza, y muy por debajo de cualquier caso real de colgado.
    'vuelta_colgada_seg' => (int) env('CIRCUITO_VUELTA_COLGADA_SEG', 3600),

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
    /*
    |--------------------------------------------------------------------------
    | FRENO DE MANO — el centinela en archivo (#170)
    |--------------------------------------------------------------------------
    |
    | Ruta ABSOLUTA y literal a propósito: `vuelta.sh` hace `cd` al worktree del slot y corre
    | `php artisan` desde ahí, y cada worktree tiene su propio `storage/` REAL. Con una ruta
    | relativa (o `storage_path()`) cada terminal tendría su freno privado — y un freno que sólo
    | detiene a una de seis no es un freno.
    |
    | Vive en `storage/app/circuito` porque es el único sitio que leen los DOS usuarios del
    | sistema: `meganet` (cron y ejecutor) y `www-data` (la Torre). El runtime del circuito
    | (`/home/meganet/circuito`) NO sirve: ese home es 0700 y la Torre no podría leerlo.
    |
    */
    'freno' => [
        'centinela' => env('CIRCUITO_FRENO_CENTINELA', '/var/www/megaisp/storage/app/circuito/PAUSA'),
    ],

    'autopilot' => [
        'enabled'             => (bool) env('CIRCUITO_AUTOPILOT', true),

        // Nivel MÁXIMO que el autopilot puede decidir solo (A < B < C).
        // DECISIÓN DE IRVING (2026-08-25): baja de 'C' a 'A' al reencender el circuito. El motivo
        // es de SECUENCIA, no de desconfianza en el autopilot: se va a soltar al revisor sobre un
        // backlog de ~107 items sin triar, así que va a haber material aprobable de golpe, y
        // ninguno de riesgo B o C debe auto-aprobarse mientras se mira la primera vuelta en vivo.
        // Historial: 2026-08-04 se puso en 'C' (máxima autonomía) tras proponerle el tope en B.
        // Para volver a subirlo NO hace falta redeploy: es un flag (o `CIRCUITO_AUTOPILOT_MAX_NIVEL`).
        // Lo que NUNCA toca el autopilot, sin importar este valor: [BLOCKED-]/[PARKED-] (frontera
        // dura) y cualquier pregunta que el Revisor marque `requiere_irving`.
        'max_nivel'           => env('CIRCUITO_AUTOPILOT_MAX_NIVEL', 'A'),

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
    |--------------------------------------------------------------------------
    | JARVIS — política de decisión y escalamiento (Torre v2)
    |--------------------------------------------------------------------------
    |
    | El problema que resuelve: hasta ahora la ÚNICA salida de una terminal que dudaba era
    | `requiere_irving`, así que cualquier titubeo despertaba al humano y el item se quedaba
    | esperando en vez de avanzar sobre la opción recomendada. No había autoridad intermedia.
    |
    | REGLA DE ORO (default): opción recomendada → avanza → registra la decisión en el historial
    | del item. Revisión POSTERIOR, no previa.
    |
    | Jarvis escala a Irving SOLO si la acción es IRREVERSIBLE y de ALTO IMPACTO. Ese conjunto es
    | el de abajo y es corto a propósito: cada término que se agregue aquí es una interrupción más
    | para Irving. Al revés también: quitar términos abre autonomía, así que se tocan con cuidado.
    |
    | La evaluación es DETERMINISTA (coincidencia de términos, sin llamada a IA): la terminal
    | pregunta y recibe respuesta en el acto, sin quedarse bloqueada esperando un turno del loop.
    |
    */
    'jarvis' => [
        'enabled' => (bool) env('CIRCUITO_JARVIS', true),

        /*
        |----------------------------------------------------------------------
        | VIGILIA (entrega A) — la mitad de Jarvis que NO puede compartir destino
        | con lo que vigila.
        |----------------------------------------------------------------------
        |
        | `tick()` vive en base y cuelga del cron del scheduler. Eso basta para decidir, y no
        | basta para vigilar: cuando la base se cayó el 22-ago, el único que podía contarlo se
        | cayó con ella; y cuando el 24-ago se comentaron las 9 líneas del circuito, Jarvis se
        | quedó sin latido sin que ninguna pantalla lo dijera.
        |
        | La vigilia mide y avisa LEYENDO Y ESCRIBIENDO ARCHIVO, sin base, con su propio cron.
        | En esta entrega SÓLO MIDE: no aísla, no libera, no mata. La autoridad llega después,
        | y el registro de PIDs de aquí es su prerrequisito.
        */
        'vigilia' => [
            'enabled' => (bool) env('CIRCUITO_JARVIS_VIGILIA', true),

            // RUTA ABSOLUTA, jamás storage_path(): igual que el centinela del freno (#170), este
            // comando puede correr desde un worktree, y cada worktree tiene su storage/ REAL.
            // Con ruta relativa habría un Jarvis por terminal, que es no tener ninguno.
            // Vive bajo storage/app porque es el único sitio que leen los DOS usuarios
            // (`meganet` que mide y `www-data` que pinta la Torre).
            'dir' => env('CIRCUITO_JARVIS_DIR', '/var/www/megaisp/storage/app/circuito/jarvis'),

            // INTERRUPTOR DE HOMBRE MUERTO. Si el latido envejece más que esto, la Torre lo pinta
            // en rojo. Un supervisor muerto en silencio convierte el silencio en falsa calma.
            // 180 s = tres vueltas de su cron de un minuto: tolera un pico, no tolera una muerte.
            'latido_umbral_seg' => (int) env('CIRCUITO_JARVIS_LATIDO_UMBRAL', 180),

            // Raíz de los worktrees. De aquí sale el hallazgo que motivó la entrega: hay SIETE
            // `laravel.log` creciendo (uno por worktree más el del checkout principal) y la sonda
            // medía UNO. Se recorre con glob para que un worktree nuevo entre solo.
            'raiz_worktrees' => env('CIRCUITO_RUNTIME', '/home/meganet/circuito'),

            // Log del checkout principal — el único que se medía hasta hoy.
            // Desde #175/#653: `medirLogs()` solo usa el DIRECTORIO de esta ruta (dirname) para
            // buscar ahí los `laravel-*.log` diarios reales — el nombre de archivo legacy
            // (`laravel.log` a secas) ya no se lee ni tiene que existir.
            'log_principal' => env('CIRCUITO_LOG_PRINCIPAL', '/var/www/megaisp/storage/logs/laravel.log'),

            // Un `claude` interactivo más viejo que esto es sospechoso de sesión abandonada.
            // Se REPORTA, nunca se mata: hoy hay cuatro de 41 días y uno de ellos podría ser la
            // sesión con la que Irving está trabajando. 24 h.
            'claude_viejo_seg' => (int) env('CIRCUITO_JARVIS_CLAUDE_VIEJO', 86400),

            // GRACIA DE ARRANQUE — segundos tras el boot del box durante los cuales un "no pude
            // conectarme a la base" se lee como «MySQL aún no levanta», no como «la base
            // desapareció», y por tanto NO dispara el freno automático de #228.
            //
            // Nace del 2026-08-28: el box arrancó 20:54:57, la vigilia midió a las 20:56:03 contra
            // un MySQL que todavía no aceptaba conexiones, puso el freno, y las seis terminales se
            // quedaron una hora paradas con la base intacta (522 tablas). No fue mala suerte: la
            // vigilia corre cada minuto desde el boot y siempre gana la carrera, así que CADA
            // reinicio frenaba el circuito.
            //
            // 180 s = tres corridas de su cron de un minuto. Subirlo alarga la ventana en la que
            // una base realmente caída al arranque tardaría en frenar; bajarlo a 0 restaura el
            // comportamiento anterior (frenar siempre que no se pueda medir).
            'gracia_arranque_seg' => (int) env('CIRCUITO_JARVIS_GRACIA_ARRANQUE', 180),
        ],

        /*
        | CONJUNTO DE ESCALAMIENTO — las cuatro fronteras duras del encargo, más el caso de spec
        | contradictorio (que se detecta aparte, no por término). Se evalúa contra la PREGUNTA de
        | la terminal + el título/módulo del item.
        |
        | Ojo con los substrings: los términos van con el contexto suficiente para no pegar de más
        | (lección del revisor #338, donde 'token'/'login' escalaban falsos positivos mecánicos).
        */
        'escalamiento' => [
            // 1) Tocar PRODUCCIÓN
            'produccion' => [
                'producción', 'produccion', 'prod .108', '192.168.105.108', '38.123.192.198',
                'v1megaisp', 'ClaudeMegaISP', 'remote:deploy', 'desplegar a prod', 'deploy a prod',
                'push a origin', 'git push',
            ],
            // 2) BORRAR datos
            'borrar_datos' => [
                'migrate:fresh', 'drop table', 'drop column', 'truncate', 'delete from',
                'borrado masivo', 'borrar la tabla', 'purgar datos', 'destructiv',
            ],
            // 3) GASTAR dinero (mover dinero real o contratar consumo)
            'dinero' => [
                'cobrar', 'cobro real', 'aplicar pago', 'mover dinero', 'openpay', 'spei',
                'domiciliación', 'domiciliacion', 'facturar', 'timbrar', 'nómina', 'nomina',
                'contratar', 'costo por uso', 'api de pago',
            ],
            // 4) CREDENCIALES / seguridad
            'credenciales' => [
                'credencial', 'api key', 'api_key', 'secreto', 'secret', 'contraseña', 'password',
                'rotar token', '.env', 'llave privada', 'certificado',
                // 'permiso' a secas y no 'permiso de rol': #542 («los permisos editados en un rol
                // no se reflejan») se colaba al carril automático porque no coincidía con la
                // frase exacta. Como esta lista se evalúa por SUBSTRING, 'permiso' cubre también
                // 'permisos' y no pega dentro de ninguna palabra ajena — sobre-cubrir aquí es el
                // lado seguro: de más, un item va a Irving; de menos, se decide solo algo de auth.
                'permiso', 'spatie', 'bcrypt', 'idor',
            ],
        ],

        /*
        | Cuando la terminal NO marca opción recomendada, Jarvis toma la primera opción declarada
        | `reversible: true`. Si NINGUNA lo es, eso ya es una señal de irreversibilidad: escala.
        | Ponerlo en false hace que Jarvis tome la primera opción sin más (más autonomía, más riesgo).
        */
        'exige_reversible_sin_recomendada' => (bool) env('CIRCUITO_JARVIS_EXIGE_REVERSIBLE', true),

        /*
        | ESTIMACIÓN DE ESFUERZO — orientativa y NUNCA bloqueante (nada se rechaza por pasarse).
        | Alimenta `roadmap_items.eta_minutos` para que la Torre muestre cuánto lleva cada terminal
        | y para ordenar el reparto. Minutos base por nivel + ajuste por tamaño del spec.
        */
        'esfuerzo' => [
            'base_por_nivel'   => ['A' => 20, 'B' => 45, 'C' => 90],
            'base_sin_nivel'   => 45,
            'min_por_kb_spec'  => 8,     // cada KB de description+prompt suma esto
            'tope_minutos'     => 240,   // techo del estimado (el timeout real lo pone vuelta.sh)
        ],

        /*
        | #895 — ¿CABE EN UNA VUELTA? Antes de picar código, la terminal corre
        | `circuito:cabida` para decidir si conviene descomponer en sub-items en vez de arrancar.
        | Deliberadamente CONSERVADOR: solo dispara con evidencia dura (nunca con el bucket
        | heurístico de `EstimadorTiempo`, que es un techo por nivel de riesgo sin muestras reales
        | y dispararía casi siempre). Tres señales, cualquiera basta:
        |   1. `veces_timeouteo >= 1` — el item YA timeouteó antes, avanzó o no (dato empírico, no
        |      estimado; #194 separado de `reanudaciones_timeout`, que solo cuenta reanudaciones CON
        |      avance y es ciego al item que gira en vacío).
        |   2. (#193) el propio spec se declara multi-fase: >= `min_fases_explicitas` encabezados
        |      `--- ETIQUETA ENUM ... ---` con la MISMA etiqueta y enumeradores distintos (p.ej.
        |      `HIJO A` … `HIJO E`). No es el modelo infiriendo fases: es quien escribió el spec
        |      quien ya las enumeró — barato (regex, sin BD) y honesto (nada se adivina).
        |   3. mediana histórica (`eta_metodo = 'historico'`, ≥3 muestras módulo+nivel) por encima
        |      del umbral de segundos.
        | `umbral_segundos` queda por debajo del timeout real de `vuelta.sh` (600s) a propósito:
        | conviene decomponer ANTES de rozar la pared, no justo al borde.
        */
        'cabida' => [
            'umbral_segundos'       => (int) env('CIRCUITO_CABIDA_UMBRAL_SEGUNDOS', 480),
            // 0 = señal apagada. Mismo umbral "≥3 muestras" que ya usa el resto del circuito
            // como evidencia suficiente (ver EstimadorTiempo::MIN_MUESTRAS).
            'min_fases_explicitas' => (int) env('CIRCUITO_CABIDA_MIN_FASES_EXPLICITAS', 3),
        ],

        /*
        |----------------------------------------------------------------------
        | CARRIL MECÁNICO (#566) — auto-aprobar lo que no tiene nada que decidir
        |----------------------------------------------------------------------
        |
        | El autopilot exige un brief con `confianza`/`reversible` explícitos, y un item sin brief
        | se queda en la bandeja aunque sea trabajo obvio. Pero hay una clase de item donde no hay
        | NADA que decidir: cerrar un hueco ruteado, borrar andamiaje muerto, registrar una ruta
        | declarada que da 404, clasificar un módulo. Pedirle a Irving que "decida" eso es puro
        | peaje: la decisión ya está tomada por el enunciado del item.
        |
        | Este carril los aprueba sin brief, y SOLO si pasan las cuatro puertas:
        |   1. NO cae en el conjunto de escalamiento (prod / borrar datos / dinero / credenciales).
        |   2. NO menciona nada de `negocio` (qué DEBE hacer una feature no lo decide una máquina).
        |   3. SÍ coincide con una `senal` mecánica explícita (allowlist, no "todo lo que no sea…").
        |   4. Nivel A o B. Un C es, por definición, una decisión de diseño de Irving.
        |
        | Allowlist y no denylist a propósito: si un item no encaja en ninguna señal conocida, se
        | queda con Irving. Lo que se baja es el umbral para lo mecánico, no la prudencia.
        |
        | Las `senales` se matchean por PALABRA COMPLETA (misma lección de substring del #338).
        |
        */
        'mecanico' => [
            'enabled'   => (bool) env('CIRCUITO_JARVIS_MECANICO', true),

            /*
            | SUB-TECHO del carril mecánico. Es más conservador que el del autopilot A PROPÓSITO: el
            | carril mecánico no tiene un brief humano detrás. Esa asimetría es información, no una
            | inconsistencia — el panel la muestra.
            |
            | ⚠️ El nivel EFECTIVO es `min(techo_global, este)`. Subirlo por encima del techo global
            | no tiene efecto y `TorreTechosCoherentesTest` lo impide. Bajarlo siempre se puede.
            */
            'max_nivel' => env('CIRCUITO_JARVIS_MECANICO_MAX_NIVEL', 'B'),

            // Tope de auto-aprobaciones mecánicas por día. Freno de mano: si la política se
            // desmadra, el daño está acotado a este número y se ve en un solo día.
            'tope_diario' => (int) env('CIRCUITO_JARVIS_MECANICO_TOPE_DIA', 25),

            // (3) SEÑALES — lo que sí se reconoce como mecánico. Ampliar esto es ampliar autonomía.
            'senales' => [
                // andamiaje muerto / código sin consumidores
                'código muerto', 'codigo muerto', 'dead code', 'andamiaje', 'cascarón vacío',
                'cascaron vacio', 'sin consumidores', 'no ruteado', 'no ruteados', 'método vacío',
                'metodo vacio', 'métodos vacíos', 'metodos vacios', 'duplicado', 'duplicada',
                // huecos y rutas
                'hueco ruteado', 'huecos ruteados', 'ruta no registrada', 'rutas no registradas',
                'ruta declarada', '404', 'enlace roto', 'link roto',
                // higiene mecánica
                'typo', 'renombrar', 'clasificar', 'footprint', 'todo mecánico', 'todo mecanico',
                'registrar permiso', 'registrar en module_registry', 'guard de null', 'null safe',
                'php -l', 'lint',
            ],

            // (2) NEGOCIO — si aparece algo de esto, no es mecánico por más que traiga una señal.
            'negocio' => [
                'negocio', 'producto', 'estrategia', 'precio', 'precios', 'tarifa', 'tarifas',
                'comercial', 'modelo de cobro', 'qué debe hacer', 'que debe hacer', 'rediseñar',
                'rediseño', 'redisenar', 'ux', 'decidir el alcance', 'política de', 'politica de',
                'item madre', 'ítem madre',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | AUTO-MERGE de trabajo verificado (#566 E1)
        |----------------------------------------------------------------------
        |
        | El problema: un nivel C con la rama terminada se parqueaba en `esperando_merge_irving` y
        | ahí se quedaba hasta que Irving mergeara a mano. Peor: cada clic de "aprobar" en la
        | bandeja lo volvía a poner `aprobado_irving` y lo re-armaba para el pool, así que un worker
        | lo tomaba, veía que no había nada que hacer y lo re-escalaba. Ese es el bucle de #117
        | (13 vueltas) — no era falta de permiso, era que lo pendiente NO era una aprobación.
        |
        | Jarvis ahora mergea ese trabajo él mismo. NO reimplementa el merge: se lo encola al
        | MergeRunner de siempre, que ya corre la verificación de regresión (php -l + boot), el
        | gate de frontend y aborta ante conflicto dejando main intacto. Jarvis sólo decide QUÉ
        | ramas son elegibles.
        |
        | RETIENE para Irving lo que apunte a prod o sea irreversible: la rama se inspecciona
        | archivo por archivo, no sólo el título.
        |
        */
        /*
        | SUB-TECHO del carril «YA DECIDIDO» (`evaluarYaDecidido`).
        |
        | ⚠️ NACE EN `C` PORQUE ES LO QUE ESE CARRIL HACE HOY: hasta esta entrega no miraba ningún
        | tope de nivel, así que un item C con el brief completamente respondido quedaba
        | `aprobado_revisor` y se despachaba. Inicializarlo en `B` "por prudencia" habría apagado un
        | comportamiento existente como efecto colateral de construir el panel — y después nadie
        | sabría si lo que cambió fue el tablero o la política.
        |
        | Bajarlo es un clic de Irving en el panel, no una decisión de quien escribe esta línea.
        */
        'ya_decidido' => [
            'max_nivel' => env('CIRCUITO_JARVIS_YA_DECIDIDO_MAX_NIVEL', 'C'),
        ],

        'automerge' => [
            'enabled' => (bool) env('CIRCUITO_JARVIS_AUTOMERGE', true),

            // Tope de auto-merges por ciclo. Cap chico a propósito: un merge malo es más caro de
            // deshacer que una aprobación mala, y así el daño de un ciclo cabe en un vistazo.
            'cap_por_ciclo' => (int) env('CIRCUITO_JARVIS_AUTOMERGE_CAP', 5),

            /*
            | RUTAS SENSIBLES — si la rama toca alguna, NO se auto-mergea aunque el título sea
            | inocente. Es la diferencia entre "el item dice que no toca prod" y "el diff no toca
            | prod": lo segundo es verificable.
            */
            'rutas_sensibles' => [
                '.env', 'deploy/', 'config/deployment.php', 'config/database.php',
                'app/Console/Commands/Active/RemoteDeployCommand.php',
                'app/Modules/Addons/Releases/', 'database/migrations_old/',
            ],

            /*
            | PATRONES DESTRUCTIVOS en el diff de migraciones. Una migración que sólo agrega es
            | reversible; una que hace drop/truncate no se deshace con `git revert` — el esquema
            | ya cambió. Esas quedan para Irving.
            */
            'patrones_destructivos' => [
                'dropColumn', 'dropIfExists', 'dropTable', 'truncate', 'delete from', 'DROP ',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | DESTRABE AUTOMÁTICO EN EL SCHEDULER (#547)
        |----------------------------------------------------------------------
        |
        | `circuito:destrabar-bandeja --apply` (auto-merge/auto-decisión/consolidado de arriba,
        | #566 E1/E2/E4) existía SOLO como comando manual — nada lo disparaba. Un item terminado +
        | decidido + verde se quedaba parqueado en `esperando_merge_irving` sin que nada avanzara:
        | el síntoma de #547 ("ya está terminado, solo espera el merge... y ahí se estanca"). La
        | elegibilidad ya era correcta (frontera dura / nivel C / migración destructiva la
        | retienen); solo faltaba encender el motor.
        |
        | Enganchado en el SCHEDULER (`SchedulerCommand::tickDestrabe()`), igual que Jarvis::tick()
        | y el Auditor arriba — NO en su propia línea de crontab: el scheduler ya es "el único
        | despachador" (#432 B1) y una línea aparte abriría una segunda carrera sobre los mismos
        | items. Throttle propio para no re-escanear la bandeja cada minuto sin necesidad (el cap
        | de auto-merges por CICLO de arriba ya acota el daño; esto solo acota la frecuencia).
        */
        'destrabe_bandeja' => [
            'enabled'           => (bool) env('CIRCUITO_JARVIS_DESTRABE_SCHEDULER', true),
            'intervalo_minutos' => (int) env('CIRCUITO_JARVIS_DESTRABE_INTERVALO', 5),
            'limit'             => (int) env('CIRCUITO_JARVIS_DESTRABE_LIMIT', 120),
        ],

        /*
        |----------------------------------------------------------------------
        | CONSOLIDADO ESTRATÉGICO (#566 E4)
        |----------------------------------------------------------------------
        |
        | Lo genuinamente estratégico no se adivina, pero tampoco tiene por qué bloquear N items
        | por separado: se junta en UNA sola pregunta con la recomendación de Jarvis para cada
        | punto, para que Irving conteste en bloque.
        |
        | `horas_default`: si no contesta en ese plazo, Jarvis procede con la opción reversible
        | recomendada y la deja registrada para revisión POSTERIOR. 0 = nunca procede solo.
        */
        'consolidado' => [
            'enabled'       => (bool) env('CIRCUITO_JARVIS_CONSOLIDADO', true),
            'horas_default' => (int) env('CIRCUITO_JARVIS_CONSOLIDADO_HORAS', 48),
            // ESTADO que el circuito reescribe en runtime, NO fuente versionada: vive en
            // storage/ (gitignored). Estaba en docs/ y cada vuelta del circuito ensuciaba un
            // archivo trackeado → el guardrail de allowlist del deploy (git_staging_gate)
            // abortaba el release por "archivos fuera del allowlist de artefactos".
            'doc_path'      => storage_path('app/circuito/decisiones-pendientes-irving.md'),
        ],

        /*
        | CIERRE — qué exige Jarvis antes de dar un item por terminado. Son los criterios de
        | aceptación mínimos y comunes a todo item; los específicos viven en el propio item.
        */
        'cierre' => [
            // El item debe traer con qué revisarlo: en llano y con el lugar de la UI donde verlo.
            'exige_reporte_coloquial' => (bool) env('CIRCUITO_JARVIS_EXIGE_REPORTE', true),
            'exige_enlace_revision'   => (bool) env('CIRCUITO_JARVIS_EXIGE_ENLACE', true),

            // #1005 (#1003 §2) — si hay `enlace_revision`, exigir que el path resuelva contra el
            // registro de rutas (Route match) antes de aceptar el cierre.
            'valida_enlace_resuelve'  => (bool) env('CIRCUITO_JARVIS_VALIDA_ENLACE', true),

            /*
            | #1005 — ROLLOUT EN DOS FASES a propósito (el propio spec del item pide "considerar un
            | periodo de solo-warning antes de bloquear duro": el gate corre en el `saving` del
            | modelo, así que afecta el cierre de CUALQUIER item de CUALQUIER terminal en paralelo).
            | `false` (default) = MODO ADVERTENCIA: un cierre incompleto queda registrado en el
            | `log` del item (antes ni eso — `verificarCierre()` no tenía consumidores) pero SÍ se
            | completa. `true` = FAIL-CLOSED real: un cierre incompleto se parquea en
            | `aprobado_irving` (mismo patrón que el parqueo C-sin-merge) en vez de completarse.
            | Subir a `true` cuando el modo advertencia lleve un tiempo sin sorpresas.
            */
            'bloquea'                 => (bool) env('CIRCUITO_JARVIS_CIERRE_BLOQUEA', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | REAPER — huérfanos por worker muerto/colgado (#561)
    |--------------------------------------------------------------------------
    |
    | Un item huérfano (worker muerto/colgado con el item en_progreso) ya NO escala directo a la
    | bandeja de Irving: se RE-ENCOLA al estado aprobado que tenía antes de reclamarse, para que el
    | pool lo vuelva a tomar. `max_reintentos` es el tope de reclamos fallidos DEL MISMO item
    | (`roadmap_items.reap_count`) antes de rendirse y escalar a `requiere_irving` — evita ciclar
    | infinito en un item genuinamente roto.
    |
    */
    'reaper' => [
        'max_reintentos' => (int) env('CIRCUITO_REAPER_MAX_REINTENTOS', 3),

        /*
        | #566 — VÍA RÁPIDA por flock del slot. Minutos de gracia desde que se reclamó el item
        | antes de creerle al flock. Solo existe para no pisar una vuelta que apenas arranca: el
        | scheduler reclama el item ANTES de que `vuelta.sh` tome el flock, así que durante esos
        | primeros segundos el slot se ve "libre" sin estarlo.
        |
        | Pasada la gracia, un slot libre significa que NO hay vuelta corriendo ahí — el kernel
        | suelta el flock aunque el proceso muera de golpe — y el item se re-encola de inmediato,
        | sin esperar los 25 min del camino lento. Eso importa porque un huérfano con footprint
        | desconocido tiene parada a TODA la flota mientras dure.
        */
        'gracia_minutos' => (int) env('CIRCUITO_REAPER_GRACIA_MIN', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | CLASIFICADOR DE FOOTPRINT (#566, liga con #526)
    |--------------------------------------------------------------------------
    |
    | Por qué existe: `modulo` NO es una etiqueta cosmética — es el FOOTPRINT con el que el
    | scheduler serializa. Un item con módulo desconocido ("Sin clasificar"/null/vacío) podría
    | tocar cualquier archivo, así que por diseño (#432 B2) **corre SOLO y bloquea a las 6
    | terminales** mientras dure. Un puñado de items sin clasificar basta para tener la flota
    | entera parada de a uno.
    |
    | Mapa DETERMINISTA término → módulo, evaluado contra título + descripción.
    |
    | DOS REGLAS DE FORMA, aprendidas a golpes:
    |
    |  1. Se matchea por PALABRA COMPLETA, no por substring. Con `str_contains`, «Portal
    |     colaborador» caía en el módulo del circuito porque "cola" vive dentro de "colaborador".
    |     Es el mismo accidente que obligó a sacar 'login' y 'token' del denylist del revisor
    |     (#338). Por eso tampoco hay términos de 2-3 letras aquí: son minas de substring.
    |
    |  2. El ORDEN va de específico a genérico y el PRIMER match gana. Las palabras del circuito
    |     ("cola", "terminal", "vuelta") son las más genéricas del vocabulario, así que van al
    |     final: si un item de Talento menciona "terminal", gana Talento.
    |
    | Los destinos salen del vocabulario YA en uso: la idea es dar footprint, no inventar más
    | variantes (eso agravaría el drift de #526).
    |
    | Lo que NO matchea se queda sin clasificar A PROPÓSITO: adivinar mal es peor que no adivinar
    | (dos items con footprint equivocado corren en paralelo y se pisan). Esos los ve Irving.
    |
    */
    'clasificador' => [
        'reglas' => [
            // — Lo más específico primero (nombres propios y frases, no palabras sueltas) —
            'ModuleManager'   => ['module_registry', 'module registry', 'módulo reportes', 'modulo reportes', 'registrar addon', 'modulemanager'],
            'Auditoria'       => ['auditoría', 'auditoria'],
            'Gestión de Red'  => ['caja', 'cajas', 'olt', 'onu', 'nap', 'fibra'],
            'Talento'         => ['talento', 'colaborador', 'colaboradores', 'técnico de campo', 'tecnico de campo'],
            'MegaFamilia'     => ['megafamilia', 'control parental'],
            'Flotas'          => ['flotas', 'vehículo', 'vehiculo', 'vehículos', 'geocerca', 'geocercas'],
            'PortalPago'      => ['portal de pago', 'portalpago', 'clabe', 'openpay'],
            'Marketing'       => ['marketing', 'whatsapp', 'evolution'],

            // — Núcleo —
            'Core / Permisos' => ['permiso', 'permisos', 'roles', 'spatie'],
            'Core / Layout'   => ['sidebar', 'menú lateral', 'menu lateral', 'layout', 'regresión visual', 'regresion visual'],
            'Core / Release'  => ['buscar actualizaciones', 'estás al día', 'estas al dia', 'release', 'deploy', 'despliegue'],
            'Core / arquitectura' => ['arquitectura', 'config:cache', 'multi-terminal'],

            // — Infra del circuito: lo más GENÉRICO, al final —
            'Roadmap / Torre de control' => [
                'torre', 'panorama', 'reloj', 'cuenta atrás', 'cuenta atras',
                'buscador con ia', 'pestaña hoja de ruta',
            ],
            'Roadmap / Circuito CC' => [
                'autopilot', 'revisor', 'scheduler', 'worker', 'terminal', 'terminales', 'bandeja',
                'cola', 'auto-merge', 'automerge', 'reaper', 'jarvis', 'circuito', 'escalamiento',
                'destrabar', 'anti-bucle', 'vuelta',
            ],
            'Roadmap' => ['hoja de ruta', 'roadmap'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MOTOR DE AUDITORÍA CONTINUA — el generador de trabajo (#559, "Item Madre")
    |--------------------------------------------------------------------------
    |
    | Qué resuelve: el circuito ya sabe REPARTIR (scheduler) y JUZGAR (Jarvis/revisor/autopilot),
    | pero no sabe GENERAR. Cuando la cola se vacía, las 6 terminales se quedan ociosas esperando
    | que un humano escriba items. Este motor escanea el sistema módulo por módulo, detecta lo que
    | falta y CREA los items-hijo que cierran esos huecos.
    |
    | Lo que NO es: no reparte (eso es del scheduler), no aprueba (los items nacen sin aprobar como
    | cualquier otro) y no decide de producto (lo que huele a decisión va a la bandeja de Irving).
    |
    | KILL-SWITCH: `enabled` en false lo apaga al instante (o CIRCUITO_AUDITOR=false en .env).
    | Además respeta el kill switch global del circuito (`circuito_pausado`, botón de la Torre):
    | en pausa NO crea nada.
    |
    */
    'auditor' => [
        // ── KILL-SWITCH del motor ──────────────────────────────────────────────────────────────
        'enabled' => (bool) env('CIRCUITO_AUDITOR', true),

        /*
        | CAP por ciclo — tope DURO de items nuevos por corrida. Es la contención principal contra
        | un desbordamiento: aunque el escaneo encuentre 300 gaps, nunca se crean más de esto de
        | una. Arranca conservador; subirlo es un cambio de una línea (ver docs/motor-auditoria.md).
        */
        'cap_por_ciclo' => (int) env('CIRCUITO_AUDITOR_CAP', 10),

        /*
        | Cuántos items se toman de UN MISMO módulo por ciclo antes de pasar al siguiente.
        |
        | Ojo, esto NO es cosmético: `modulo` es el footprint con el que el scheduler SERIALIZA
        | (#432 B2). Si un ciclo generara sus 10 items para Mapas, el scheduler los correría de a
        | uno y 5 terminales quedarían ociosas — justo lo que este motor viene a evitar. Por eso el
        | reparto es ROUND-ROBIN entre módulos: pocos items de muchos módulos llenan las 6
        | terminales; muchos items de un módulo llenan una.
        */
        'items_por_modulo_por_ciclo' => (int) env('CIRCUITO_AUDITOR_POR_MODULO', 2),

        /*
        | UMBRAL de disparo: el motor sólo corre si la cola REALMENTE reclamable (lo que el
        | scheduler podría despachar ahora mismo) está por debajo de esto. Con la cola llena se
        | queda quieto: generar más trabajo no ayudaría y sólo ensuciaría la Hoja de Ruta.
        */
        'umbral_cola' => (int) env('CIRCUITO_AUDITOR_UMBRAL', 3),

        /*
        | Minutos mínimos entre escaneos. El scheduler corre cada minuto; escanear ~1,500 archivos
        | PHP cada minuto sería un desperdicio. Con la cola vacía, un escaneo cada 15 min basta de
        | sobra para que las terminales nunca se queden sin trabajo.
        */
        'min_intervalo_minutos' => (int) env('CIRCUITO_AUDITOR_INTERVALO', 15),

        /*
        | LOS DOS CARRILES (inventario de módulos, 2026-08-08).
        |
        | `paralelo`: módulos con acoplamiento ~0 (nadie los consume, no consumen a nadie) → sus
        | items pueden correr a la vez en distintas terminales sin pisarse.
        |
        | `serializado`: la base acoplada. Clientes (in=8), Configuracion (in=9), CRM (in=8) y
        | ModuleManager (in=7) sostienen a casi todo el sistema; dos cambios simultáneos ahí se
        | pisan. Van al final y de a uno (el scheduler ya serializa por `modulo`; esto además evita
        | que el motor los ponga en cabeza de cola).
        |
        | El orden de la lista ES el orden de trabajo. Un módulo sin gaps se salta solo (está en su
        | DoD de Fase 1), así que la lista no hay que mantenerla a mano cuando algo se termina.
        |
        | #809 — `Reportes` SE QUITÓ de aquí: `app/Modules/Addons/Reportes` ya no existe en disco (la
        | semilla de `InventarioSemilla` que lo describía —module vacío, duplicaba /releases— trae su
        | propio `vigente` que ya lo daba por cerrado al confirmar que el directorio desapareció). Sin
        | directorio, el carril auditaba en vacío: cero huecos/enlaces/TODOs/andamiaje/spec, siempre.
        |
        | #903 — Tanda 1 de la ampliación a los 11 módulos que quedaban fuera (decisión de Irving:
        | tandas de 2-3, priorizando criticidad de negocio). Entran `Auth`, `Planes`, `Documentos`:
        | Auth = permisos/seguridad (login, roles), Planes = catálogo de planes ligado a facturación
        | (Internet/VoIP/Custom/Bundle), Documentos = plantillas de contratos/facturas — las 3 más
        | cercanas a "facturación/permisos/clientes" entre las 11 candidatas (ninguna es literalmente
        | Clientes/Configuracion/CRM, que ya están en `serializado`). Van a `paralelo`: coupling medido
        | por referencias cruzadas a su namespace (`App\Modules\{Core|Addons}\{Modulo}` fuera del
        | propio módulo) es Auth=1, Planes=4, Documentos=0 — muy por debajo del umbral de `serializado`
        | (Clientes in=8, Configuracion in=9, CRM in=8, ModuleManager in=7). Verificado que los 3
        | directorios existen con controllers reales y `module.json` `active:true` antes de sumarlos
        | (regla del item, evita repetir el caso `Reportes`). Quedan 8 módulos para tandas futuras:
        | Dashboard, Documentacion, Layout, Localizacion, Release, IA, SmartImportExport, WarRoom.
        |
        | #918 — Tanda 2 (2 módulos, decisión de Irving en #918: alto tráfico operativo, arranque
        | conservador). Entran `Dashboard` y `Localizacion`: Dashboard = pantalla de mayor visibilidad
        | de uso diario del sistema, Localizacion = alimenta CRM/mapeo de red/domicilios de clientes
        | (estado/municipio/colonia/sucursal) — las 2 más cercanas a "tráfico operativo alto" entre
        | las 8 candidatas restantes (ninguna toca facturación/permisos/pagos). Antes de sumarlas se
        | revisó el resultado de la tanda 1 (14 items generados sobre Auth/Planes/Documentos, 11
        | completados, 1 escalado, 2 aprobados — ritmo sano, no hubo motivo para frenar). Coupling
        | medido igual que en tanda 1 (referencias cruzadas a `App\Modules\{Core|Addons}\{Modulo}`
        | fuera del propio módulo): Dashboard=0, Localizacion=0 — muy por debajo del umbral de
        | `serializado`. Verificados ambos directorios con controllers reales y `module.json`
        | `active:true`. Quedan 6 módulos para tandas futuras: Documentacion, Layout, Release, IA,
        | SmartImportExport, WarRoom.
        */
        'carriles' => [
            'paralelo' => [
                'GestionRed', 'Inventario', 'Mapas', 'Tickets', 'Scheduling', 'Hub',
                'Finanzas', 'Mensajes', 'Vendedores', 'Talento', 'Flotas', 'Marketing',
                'Payments', 'MegaFamilia', 'VoIP', 'WhatsAppAgent', 'PortalCliente', 'PortalPago',
                'Embajadores', 'Usuarios', 'Roadmap / Circuito CC',
                'Auth', 'Planes', 'Documentos',
                'Dashboard', 'Localizacion',
            ],
            'serializado' => ['Clientes', 'Configuracion', 'CRM', 'ModuleManager'],
        ],

        /*
        | #809 — ALIAS carril → nombre real del directorio del módulo. Un carril es el FOOTPRINT con
        | el que se etiqueta la columna `modulo` de los items generados (y con el que el scheduler
        | serializa, #432 B2); no siempre coincide con el nombre del directorio en
        | `app/Modules/{Core|Addons}/`. Sólo entra aquí un carril cuyo directorio DIFIERE del
        | footprint — la mayoría no lo necesita. Sin alias, `AuditorService::rutaModulo()` audita ese
        | carril en vacío (era el caso de `Roadmap / Circuito CC`, cuyo directorio real es `Roadmap`).
        */
        'alias_directorio' => [
            'Roadmap / Circuito CC' => 'Roadmap',
        ],

        /*
        | DETECTORES de Fase 1. Apagar uno deja de generar ESE tipo de item sin tocar el resto.
        |
        |  - hueco_ruteado: método con RUTA ACTIVA y cuerpo vacío. El hueco real (el usuario hace
        |    clic y no pasa nada). Sólo había 3 en todo el sistema al 2026-08-08.
        |  - enlace_roto:   URL declarada en el module.json (menú/admin_cards/config_sections) que
        |    NO resuelve a ninguna ruta registrada → 404 desde el menú.
        |  - todo:          TODO/FIXME/HACK reales en comentario (no la palabra española "todo").
        |  - andamiaje:     métodos resource vacíos SIN ruta (basura de make:controller --resource).
        |    Es limpieza, no funcionalidad: un item por módulo, no uno por método.
        |  - sin_clasificar: items de la Hoja de Ruta con footprint desconocido, que por diseño
        |    corren SOLOS y bloquean a las 6 terminales (#526). Clasificarlos libera la flota.
        |  - semilla:       pendientes del inventario 2026-08-08 que el escaneo no puede ver.
        */
        'detectores' => [
            'hueco_ruteado'  => (bool) env('CIRCUITO_AUDITOR_D_HUECOS', true),
            'enlace_roto'    => (bool) env('CIRCUITO_AUDITOR_D_ENLACES', true),
            'todo'           => (bool) env('CIRCUITO_AUDITOR_D_TODOS', true),
            'andamiaje'      => (bool) env('CIRCUITO_AUDITOR_D_ANDAMIAJE', true),
            'sin_clasificar' => (bool) env('CIRCUITO_AUDITOR_D_SINCLAS', true),
            'semilla'        => (bool) env('CIRCUITO_AUDITOR_D_SEMILLA', true),
        ],

        /*
        | Términos que delatan una DECISIÓN DE PRODUCTO dentro de un TODO/FIXME. Si el texto del
        | comentario trae alguno, el gap NO se crea como item mecánico: se manda a la bandeja de
        | Irving con la pregunta. "Refactorizar esto" es mecánico; "preguntar si eliminamos el
        | archivo" no lo es, y adivinarlo sería fabricar una decisión suya.
        |
        | Nota: ADEMÁS de esto, todo gap pasa por la frontera dura de Jarvis
        | (`circuito.jarvis.escalamiento`: producción / borrar datos / dinero / credenciales). Lo
        | que cae ahí NUNCA sale como item mecánico, sin importar lo que diga este listado.
        */
        'terminos_producto' => [
            'preguntar', 'pregunta', 'decidir', 'definir', 'confirmar con', 'revisar con',
            'pedido por', 'consultar', 'evaluar si', 'validar con', 'depende de',
        ],

        /*
        | Módulos que el motor NO audita.
        |  - Demo: es el addon de EJEMPLO del kit modular; sus huecos son didácticos a propósito.
        |  - Security / Voice: son librerías internas sin module.json ni provider, no módulos.
        */
        'excluir_modulos' => ['Demo', 'Security', 'Voice'],

        /*
        |-----------------------------------------------------------------------------------------
        | FASE 2B — MEDIR CONTRA EL SPEC (`module.json`). El generador construye su propio sustrato.
        |-----------------------------------------------------------------------------------------
        |
        | El Paso 0 (2026-08-18, `php artisan circuito:inventario-spec`) midió que el spec describe
        | ~3.7 % de la superficie real: 117 endpoints declarados sobre 3,193 pares método+ruta. Un
        | detector semántico perfecto sobre ese 3.7 % daría dos docenas de items y volvería a secarse.
        |
        | Por eso el PRIMER producto del generador no son huecos de código: son **huecos de
        | DECLARACIÓN**. Y tienen la propiedad que buscábamos — cada módulo que completa su
        | `module.json` amplía la superficie que el detector puede medir en la vuelta siguiente. El
        | generador se alimenta a sí mismo porque su primer trabajo es construir el instrumento.
        |
        | Todo aquí es LOOKUP: cada hallazgo traza a un conteo, no a un juicio. Confianza 1.0 en la
        | discrepancia. ⚠️ Y CERO en el diagnóstico: ver `desalineada`.
        */
        'spec' => [

            'enabled' => (bool) env('CIRCUITO_AUDITOR_SPEC', true),

            /*
            | % de las rutas del módulo que deben estar declaradas en `api_endpoints` para
            | considerarlo cubierto. NO es 100 % a propósito: `api_endpoints` describe el contrato
            | público, no cada ruta interna de datatable. Medido al calibrar: el mejor hoy es
            | Mensajes con 29 %, y hay 21 módulos por debajo de este umbral.
            */
            'umbral_cobertura' => (int) env('CIRCUITO_AUDITOR_SPEC_UMBRAL', 30),

            /* Módulos con menos rutas que esto no se molestan: no hay contrato que declarar. */
            'min_rutas' => (int) env('CIRCUITO_AUDITOR_SPEC_MIN_RUTAS', 3),

            /*
            | Tope de endpoints que pide UN item. Es lo que hace converger el ciclo en vez de
            | producir un item imposible: Mapas declara 2 de 200: pedirle "declara 200" no es una
            | tarea, es un proyecto. Cada tanda sube la cobertura, y la vuelta siguiente genera la
            | tanda siguiente (la huella de dedup incluye el tramo, ver `AuditorService::huella`).
            */
            'cap_por_item' => (int) env('CIRCUITO_AUDITOR_SPEC_CAP', 25),

            /* Cuántos endpoints desalineados se listan dentro del item (el resto va en el conteo). */
            'muestra_desalineada' => 12,

            'detectores' => [
                // Módulo con rutas registradas y CERO api_endpoints. Ahí está el volumen: 16 módulos.
                'modulo_sin_declarar'    => true,

                // Declara, pero por debajo del umbral. 21 módulos.
                'declaracion_incompleta' => true,

                /*
                | ⚠️ Declarado ≠ registrado, DIRECCIÓN DESCONOCIDA.
                |
                | El lookup detecta la discrepancia con certeza y NO dice nunca cuál de los dos lados
                | está mal. Medido: Flotas declara `/api/flotas/*` cuando existen 65 rutas bajo
                | `flotas/api/*`, y Planes declara un esquema de URLs que nunca se construyó así —
                | los dos son la DECLARACIÓN envejecida, no código faltante.
                |
                | Por eso el hallazgo se llama «declaración y realidad no coinciden» y NUNCA "falta
                | construir X". Un detector que emite "falta construir X" cuando X existe con otro
                | nombre no produce ruido: produce TRABAJO FABRICADO, que es peor.
                */
                'desalineada'            => true,

                /*
                | Permiso declarado que no existe en la tabla `permissions`. Hoy rinde CERO
                | (111/111 correctos) y eso es exactamente lo que se espera: su valor es de GUARDIA
                | CONTRA REGRESIONES, no de generación. Un detector que no encuentra nada aquí está
                | sano, no roto.
                */
                'permiso_inexistente'    => true,

                /*
                | NO TODAVÍA. Sólo 9 de 43 módulos declaran `screens`: no hay contra qué medir. Es
                | precisamente el trabajo que producen los items de arriba; se enciende cuando la
                | superficie declarada haya subido.
                */
                'sin_screens'            => false,
            ],
        ],

        /*
        |-----------------------------------------------------------------------------------------
        | #1015 — FRENO POR SEQUÍA de la fuente `código` (item #1004 §3, propiedad 2/3; la 1
        | —"uno solo vivo a la vez"— ya existía en `debeCorrer()`).
        |-----------------------------------------------------------------------------------------
        |
        | `min_intervalo_minutos` de arriba es FIJO. Esto lo alarga dinámicamente cuando el
        | escaneo de código lleva varias corridas EN VIVO seguidas sin encontrar nada nuevo (medido
        | 2026-08-21: nuevos=0 en casi todos los módulos) — seguir escaneando cada 15 min con la
        | fuente agotada es trabajo desperdiciado.
        |
        | El contador (`AuditorService::rachaSeca()`) es SOLO de esta fuente (los detectores de
        | este archivo: huecos/enlaces/TODOs/andamiaje/spec/semilla). Una futura minería de
        | bitácora (#1004 §4) es OTRO mecanismo con su propio ritmo — no debe frenarse por esto,
        | así que este backoff nunca debe tocar nada fuera de `AuditorService::ciclo()`.
        */
        'sequia' => [
            // Corridas EN VIVO seguidas con 0 nuevos (de TODO el ciclo, no por módulo) antes de
            // empezar a alargar el intervalo. Por debajo de esto, el intervalo es el normal.
            'racha_umbral' => (int) env('CIRCUITO_AUDITOR_SEQUIA_UMBRAL', 3),

            // Minutos que se suman al intervalo base por cada corrida seca adicional una vez
            // cruzado el umbral (backoff lineal, no exponencial — más fácil de razonar).
            'incremento_minutos' => (int) env('CIRCUITO_AUDITOR_SEQUIA_INCREMENTO', 15),

            // Techo del intervalo alargado: nunca deja de escanear del todo (el motor sigue vivo,
            // sólo más espaciado), y un cambio real de código lo revive de inmediato (cualquier
            // corrida con nuevos > 0 resetea la racha a 0).
            'intervalo_max_minutos' => (int) env('CIRCUITO_AUDITOR_SEQUIA_MAX', 120),
        ],
    ],

    /*
    | #432 Fase 3 — Brief COMPLETO (multi-pregunta). ON: la bandeja usa la columna JSON `preguntas`
    | (varias preguntas por item) y la escalación las puebla TODAS de una. OFF: fallback al modelo
    | viejo de una sola `opciones`/`opcion_elegida`. Un item SIN `preguntas` cae al fallback aunque
    | esté ON, así que activar esto NO rompe los items existentes.
    */
    'multi_pregunta'   => (bool) env('CIRCUITO_MULTI_PREGUNTA', true),

    /*
    |---------------------------------------------------------------------------------------------
    | FASE 2A.4 — RE-TRIAGE DE FRENOS. La regla es ASIMÉTRICA, y esa asimetría es todo el punto.
    |---------------------------------------------------------------------------------------------
    |
    |  · FRENO DEL CLASIFICADOR (`origen_bloqueo = 'clasificador'`) → CADUCA SOLO. Es un consejo
    |    automático: si en `clasificador_caduca_dias` nadie lo confirmó (nadie lo convirtió en freno
    |    humano), vence y se va. Un consejo que nadie ratificó no debe seguir opinando para siempre.
    |
    |  · FRENO HUMANO (`origen_bloqueo = 'humano'`) → **NUNCA CADUCA.** Es una decisión de Irving y
    |    el sistema NO la revoca por antigüedad. Por eso aquí NO hay ninguna clave
    |    `humano_caduca_dias`: su ausencia es la decisión, no un olvido. `RetriageFrenosCommand`
    |    falla-cerrado si algún día alguien la agrega.
    |
    | Los 33 frenos vivos no son items bloqueados por error: son decisiones de Irving que se le
    | olvidó haber tomado. Caducarlos solos se las revocaría a la mala; lo que hacen falta son
    | RECORDATORIOS. De ahí `resurface_dias`: cada N días el digest lista "frenos que pusiste tú y
    | llevan X días en pie", con el item, la fecha y lo que decía el rótulo.
    */
    /*
    |---------------------------------------------------------------------------------------------
    | FASE 2A.7 (#808) — LIVENESS DE LOS PROCESOS PROGRAMADOS.
    |---------------------------------------------------------------------------------------------
    |
    | Una regla implementada y NO agendada es un no-op invisible: 2A.4 dejó el caducado del
    | clasificador escrito, probado y fail-closed… y sin su línea de cron no corre. Sin nada que lo
    | delate, eso se descubre en dos meses preguntándose por qué nada caducó nunca.
    |
    | Cada proceso de aquí sella su último latido AL TERMINAR (listener único de `CommandFinished`
    | en el ModuleServiceProvider — NO hay que tocar cada comando, y uno nuevo sólo necesita una
    | fila aquí). El digest delata en su PRIMERA línea a cualquiera que lleve más de `max_horas`
    | sin latir, o que no haya latido nunca.
    |
    | `si_no_corre` es lo que se pierde, en una frase: un "no ha corrido" sin consecuencia se ignora.
    */
    'procesos_programados' => [

        'circuito:scheduler' => [
            // #946 — nombre humano + cadencia en HORAS (numérica, no texto) para el Semáforo de la
            // Torre: 🟡 a 2× esta cadencia sin éxito, 🔴 a 3×. Aditivo: nadie más lee estas 2 llaves.
            'motor'         => 'Scheduler',
            'cadencia_horas' => 1 / 60,
            'max_horas'   => 1,
            // Reusa el latido que el scheduler YA sella (unix timestamp), en vez de sellar un
            // segundo: dos relojes del mismo hecho es cómo empieza siempre la deriva.
            'beat_key'    => 'circuito_scheduler_beat',
            'formato'     => 'unix',
            // #197 — a propósito SIN `excluye_opciones`/`exige_opciones` aquí, igual que los otros
            // 3 procesos `formato => 'unix'` de este archivo (destrabar-bandeja/auditor/watchdog):
            // `sellarLatido()` retorna temprano para `formato==='unix'` ANTES de leer esas llaves
            // (RoadmapCircuitoService::sellarLatido), así que declararlas aquí sería config sin
            // consumidor. La protección real contra dry-run vive INLINE en el propio comando
            // (`SchedulerCommand::handle()`, `if (! $this->option('dry'))` antes de sellar el
            // latido) — verificado empíricamente 2026-08-28: `circuito:scheduler --dry` NO mueve
            // `circuito_scheduler_beat`. Si se re-audita este archivo y parece "el scheduler es
            // el único sin excluir --dry", NO es un bug: es el patrón correcto para self-sealing.
            'si_no_corre' => 'NADIE reparte trabajo: las 6 terminales quedan paradas',
            // #942 — texto tal cual aparece en el crontab real de `meganet` (verificado con
            // `crontab -l` el 2026-08-21). Solo lectura: se edita en el crontab, no aquí.
            'cadencia'    => 'cada minuto',
        ],

        'circuito:re-triage' => [
            // #946 — sin cadencia fija real (todavía no agendado, ver `linea_cron` abajo): el
            // Semáforo cae a su umbral configurable (derivado de `max_horas`, ver `semaforoMotores`).
            'motor'         => 'Re-triage',
            'cadencia_horas' => null,
            'max_horas'   => 48,
            // Un DRY-RUN no caducó nada, así que no cuenta como "el proceso corrió". Sin esto, un
            // `circuito:re-triage` a mano desde una sesión enmascararía que el cron no existe —
            // que es exactamente la mentira que este vigilante viene a evitar.
            'exige_opciones' => ['apply'],
            'si_no_corre' => 'el freno del CLASIFICADOR nunca caduca (2A.4 queda de adorno)',
            // #808 — ningún ejecutor on-box puede escribir el crontab del SO (sandbox lo bloquea,
            // verificado). Línea exacta para `crontab -e` del usuario meganet, JUSTO ANTES de la
            // línea del digest (40 6) para que a las 06:40 ya refleje lo vencido. El digest la
            // imprime tal cual cuando `agendado === false` (ver DigestCommand::procesosProgramados).
            //
            // ⚠️ EL DESTINO DE LA REDIRECCIÓN ES PARTE DE LA LÍNEA, no un detalle cosmético.
            // Apuntaba a `/var/log/circuito-digest.log`, y `/var/log` es root:root 755: `meganet`
            // no puede crear el archivo, así que el shell de cron FALLA AL ABRIRLO y el comando no
            // llega a ejecutarse nunca. Ese es el modo de fallo más caro que hay aquí, porque no
            // deja rastro en ningún panel — no hay excepción que sellar ni exit code que registrar,
            // y el proceso se ve «agendado» mientras nunca ha corrido. Le pasó al propio digest
            // (corregido en el crontab el 2026-08-26); esta línea lo habría reproducido en cuanto
            // alguien la copiara. Escribe en `/home/meganet/circuito/logs/`, que es del usuario que
            // corre el cron — mismo criterio que `vigilia-wrap.sh`.
            'linea_cron' => "30 6 * * * /var/www/megaisp/deploy/circuito/cron-wrap.sh circuito:re-triage --apply >> /home/meganet/circuito/logs/digest.log 2>&1",
            // #942 — todavía NO está en el crontab (ver `linea_cron` arriba); no tiene cadencia real.
            'cadencia'    => 'sin agendar (#808)',
        ],

        'circuito:digest' => [
            'motor'         => 'Digest',
            'cadencia_horas' => 24,
            'max_horas'   => 48,
            'si_no_corre' => 'no hay métricas ni recordatorio de los frenos que puso Irving',
            'cadencia'    => '06:40 diario',
        ],

        /*
        | AUDITADO EL 2026-08-19: este registro cubría 4 de 13 motores reales. El destrabe de la
        | bandeja llevaba OCHO DÍAS fallando cada minuto y el panel no lo habría pintado en rojo —
        | ni siquiera lo habría pintado. **Ausente es peor que rojo.** El mecanismo estaba bien; el
        | registro estaba incompleto, que es la misma forma de fallar: algo que se ve sano porque
        | nadie lo está mirando.
        */

        // Enganchado DENTRO del scheduler (throttle 5 min). Sella su propio latido SOLO al terminar
        // bien, así que su beat ya es «última ejecución EXITOSA», que es justo lo que hay que mirar.
        'circuito:destrabar-bandeja' => [
            'motor'         => 'Des-trabador',
            'cadencia_horas' => 5 / 60,
            'max_horas'   => 2,
            'beat_key'    => 'circuito_destrabe_bandeja_beat',
            'formato'     => 'unix',
            'si_no_corre' => 'la bandeja no se destraba: nada se auto-mergea ni se auto-decide, y los '
                . 'items terminados se acumulan en esperando_merge_irving',
            'cadencia'    => 'dentro del scheduler · throttle 5 min',
        ],

        // También dentro del scheduler; su gating (cola < umbral) puede impedirle correr
        // legítimamente, por eso el tope es de un día y no de horas.
        'circuito:auditor' => [
            // #946 — gating por cola (no un reloj fijo): sin cadencia numérica, umbral configurable
            // vía `max_horas` (igual que re-triage arriba).
            'motor'         => 'Auditor',
            'cadencia_horas' => null,
            'max_horas'   => 24,
            'beat_key'    => 'circuito_auditor_ultima_corrida',
            'formato'     => 'unix',
            'si_no_corre' => 'con la cola vacía nadie genera trabajo: las 6 terminales se quedan ociosas',
            'cadencia'    => 'dentro del scheduler · cola < 3 y ≥ 15 min desde la última',
        ],

        'circuito:watchdog' => [
            'motor'         => 'Watchdog',
            'cadencia_horas' => 2 / 60,
            'max_horas'   => 1,
            'beat_key'    => 'circuito_watchdog_beat',
            'formato'     => 'unix',
            'si_no_corre' => 'nadie vigila a los workers ni auto-recupera anomalías del scheduler',
            'cadencia'    => 'cada 2 min',
        ],

        'circuito:revisar-backlog' => [
            'motor'         => 'Revisor',
            'cadencia_horas' => 2 / 60,
            'max_horas'   => 1,
            'excluye_opciones' => ['dry'],
            'si_no_corre' => 'los B se quedan sin veredicto del revisor y no llegan a la cola',
            'cadencia'    => 'cada 2 min',
        ],

        'circuito:destrabe' => [
            'motor'         => 'Des-trabe (Opus)',
            'cadencia_horas' => 4 / 60,
            'max_horas'   => 2,
            'si_no_corre' => 'la bandeja no recibe el re-triaje de Opus: lo técnico/seguro se queda con Irving',
            'cadencia'    => 'cada 4 min',
        ],

        'circuito:reap-stuck' => [
            'motor'         => 'Reap-stuck',
            'cadencia_horas' => 2 / 60,
            'max_horas'   => 1,
            'si_no_corre' => 'los reclamos huérfanos no se liberan y su footprint bloquea a la flota',
            'cadencia'    => 'cada 2 min',
        ],

        'circuito:brief-c' => [
            'motor'         => 'Autopilot (brief)',
            'cadencia_horas' => 10 / 60,
            'max_horas'   => 2,
            'si_no_corre' => 'los C se quedan sin brief y el autopilot no puede calificar nada',
            'cadencia'    => 'cada 10 min',
        ],

        /*
        | NO se vigilan a propósito, y conviene que quede escrito para que nadie los añada por
        | simetría:
        |  · `circuito:disparo-check` — sólo ADELANTA una corrida del scheduler, que ya está
        |    vigilado. Su fallo no pierde nada que el scheduler no recupere al minuto siguiente.
        |  · `MergeRunner::drain()` y `JarvisService::tick()` — corren en CADA vuelta del scheduler,
        |    sin throttle. «Cuándo corrieron por última vez» siempre diría «hace un minuto» y no
        |    informaría de nada: su señal útil no es liveness, es si su último intento falló.
        */

        'circuito:priorizar-seguridad' => [
            'motor'         => 'Priorizar-seguridad',
            'cadencia_horas' => 24,
            'max_horas'   => 48,
            // `--dry` no escribe; `--item=` es la clasificación de UN item (la dispara
            // `ClasificarRiesgoJob` al crear), no el BARRIDO diario. Ninguna de las dos cuenta.
            'excluye_opciones' => ['dry', 'item'],
            'si_no_corre' => 'el barrido diario del clasificador no clasifica nada nuevo',
            'cadencia'    => '06:20 diario',
        ],

        // #921 Fase 2 / #957 — cron diario (Kernel.php, hard-coded como activitylog:archive).
        // 30h de margen sobre el diario (no 24h clavado) para no pintar rojo por jitter normal
        // del scheduler del SO antes de que de verdad se le haya pasado un día completo.
        'circuito:reactivar-agendados' => [
            'motor'         => 'Reactivar-agendados',
            'cadencia_horas' => 1,
            'max_horas'   => 3,
            'si_no_corre' => 'los items agendados a futuro NUNCA vuelven solos al pool aunque su '
                . 'fecha ya haya pasado — se quedan fuera hasta que alguien los toque a mano',
            // #942 lo dejó SOLO en `app/Console/Kernel.php` (Laravel Schedule), asumiendo el
            // `schedule:run` estándar. CORRECCIÓN 2026-08-26: en este box ESE CRON NO EXISTE — las
            // líneas del circuito se invocan una por una a propósito — así que el comando no había
            // corrido NUNCA y ningún item agendado volvía solo al pool. Y el fallo era callado: la
            // compuerta `agendados` se ve verde mientras no haya ninguno diferido. Ahora tiene su
            // propia línea de crontab (cron-wrap.sh, cada 10 min) como los demás motores; la de
            // Kernel.php se conserva para el entorno que sí tenga `schedule:run` (correr dos veces
            // es inofensivo: el segundo pase no encuentra nada). La ventana de alarma baja de 30 h
            // a 3: con cadencia de 10 min, media hora sin latir ya es señal de que el cron murió.
            'cadencia'    => 'cada 10 min (crontab del circuito) + 00:05 diario (Kernel.php, donde haya schedule:run)',
        ],
    ],

    'retriage' => [

        // Cada cuántos días vence un freno del CLASIFICADOR que nadie confirmó.
        'clasificador_caduca_dias' => (int) env('CIRCUITO_RETRIAGE_CLASIFICADOR_DIAS', 14),

        // Cada cuántos días el digest vuelve a poner los frenos humanos enfrente de Irving. Diario
        // sería ruido: se vuelve invisible por repetición, que es justo el problema que resuelve.
        'resurface_dias'           => (int) env('CIRCUITO_RETRIAGE_RESURFACE_DIAS', 7),

        // Cuántos frenos lista el digest por pasada (los demás salen en el conteo).
        'resurface_top'            => (int) env('CIRCUITO_RETRIAGE_RESURFACE_TOP', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | ANTI-BUCLE — umbral de escalación repetida (item #941)
    |--------------------------------------------------------------------------
    |
    | Huérfano de alta prioridad migrado a config SIN cambiar el valor (era
    | `RoadmapItem::ESCALACION_BUCLE_UMBRAL`, constante en duro). Lo lee
    | `contarEscalacion()`: si la MISMA causa de escalación se repite esta
    | cantidad de veces sin cambio material, el item sale del pool automático
    | (`bloqueado_por_bucle`). NO se expone todavía en el panel de la Torre
    | (eso es el sub-item de reorganización por actor).
    */
    'escalacion_bucle_umbral' => (int) env('CIRCUITO_ESCALACION_BUCLE_UMBRAL', 3),

    /*
    |--------------------------------------------------------------------------
    | WATCHDOG — umbrales de salud del equipo de workers (item #941)
    |--------------------------------------------------------------------------
    |
    | Huérfanos de alta prioridad migrados a config SIN cambiar los valores
    | (eran constantes en duro de `WatchdogService.php`). NO se exponen
    | todavía en el panel de la Torre (eso es el sub-item de reorganización
    | por actor).
    */
    'watchdog' => [
        // Seg tras los que el scheduler se considera CAÍDO (alineado con la Torre).
        'scheduler_stale_seg' => (int) env('CIRCUITO_WATCHDOG_SCHEDULER_STALE_SEG', 180),

        // Seg de latido frío tras los que un worker "corriendo" se considera COLGADO → reap.
        'worker_hung_seg' => (int) env('CIRCUITO_WATCHDOG_WORKER_HUNG_SEG', 600),

        // Máx intentos consecutivos de auto-recuperación por causa antes de ESCALAR a Irving.
        'max_intentos' => (int) env('CIRCUITO_WATCHDOG_MAX_INTENTOS', 3),

        // Cuántos eventos de bitácora se conservan.
        'log_cap' => (int) env('CIRCUITO_WATCHDOG_LOG_CAP', 60),
    ],

];
