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
        // DECISIÓN DE IRVING (2026-08-04, confirmada tras proponerle el tope en B): MÁXIMA
        // AUTONOMÍA = 'C'. Un nivel C solo pasa si además trae `reversible: true` y confianza alta,
        // así que lo irreversible y lo de negocio sigue siendo suyo. La regla de CLAUDE.md se
        // actualizó en el mismo commit para no quedar contradiciendo a este flag.
        // Lo que NUNCA toca el autopilot, sin importar este valor: [BLOCKED-]/[PARKED-] (frontera
        // dura) y cualquier pregunta que el Revisor marque `requiere_irving`.
        'max_nivel'           => env('CIRCUITO_AUTOPILOT_MAX_NIVEL', 'C'),

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
    | THOMAS — política de decisión y escalamiento (Torre v2)
    |--------------------------------------------------------------------------
    |
    | El problema que resuelve: hasta ahora la ÚNICA salida de una terminal que dudaba era
    | `requiere_irving`, así que cualquier titubeo despertaba al humano y el item se quedaba
    | esperando en vez de avanzar sobre la opción recomendada. No había autoridad intermedia.
    |
    | REGLA DE ORO (default): opción recomendada → avanza → registra la decisión en el historial
    | del item. Revisión POSTERIOR, no previa.
    |
    | Thomas escala a Irving SOLO si la acción es IRREVERSIBLE y de ALTO IMPACTO. Ese conjunto es
    | el de abajo y es corto a propósito: cada término que se agregue aquí es una interrupción más
    | para Irving. Al revés también: quitar términos abre autonomía, así que se tocan con cuidado.
    |
    | La evaluación es DETERMINISTA (coincidencia de términos, sin llamada a IA): la terminal
    | pregunta y recibe respuesta en el acto, sin quedarse bloqueada esperando un turno del loop.
    |
    */
    'thomas' => [
        'enabled' => (bool) env('CIRCUITO_THOMAS', true),

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
                'rotar token', '.env', 'llave privada', 'certificado', 'permiso de rol',
                'dar permiso', 'otorgar permiso', 'spatie', 'bcrypt', 'idor',
            ],
        ],

        /*
        | Cuando la terminal NO marca opción recomendada, Thomas toma la primera opción declarada
        | `reversible: true`. Si NINGUNA lo es, eso ya es una señal de irreversibilidad: escala.
        | Ponerlo en false hace que Thomas tome la primera opción sin más (más autonomía, más riesgo).
        */
        'exige_reversible_sin_recomendada' => (bool) env('CIRCUITO_THOMAS_EXIGE_REVERSIBLE', true),

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
            'enabled'   => (bool) env('CIRCUITO_THOMAS_MECANICO', true),
            'max_nivel' => env('CIRCUITO_THOMAS_MECANICO_MAX_NIVEL', 'B'),

            // Tope de auto-aprobaciones mecánicas por día. Freno de mano: si la política se
            // desmadra, el daño está acotado a este número y se ve en un solo día.
            'tope_diario' => (int) env('CIRCUITO_THOMAS_MECANICO_TOPE_DIA', 25),

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
        | Thomas ahora mergea ese trabajo él mismo. NO reimplementa el merge: se lo encola al
        | MergeRunner de siempre, que ya corre la verificación de regresión (php -l + boot), el
        | gate de frontend y aborta ante conflicto dejando main intacto. Thomas sólo decide QUÉ
        | ramas son elegibles.
        |
        | RETIENE para Irving lo que apunte a prod o sea irreversible: la rama se inspecciona
        | archivo por archivo, no sólo el título.
        |
        */
        'automerge' => [
            'enabled' => (bool) env('CIRCUITO_THOMAS_AUTOMERGE', true),

            // Tope de auto-merges por ciclo. Cap chico a propósito: un merge malo es más caro de
            // deshacer que una aprobación mala, y así el daño de un ciclo cabe en un vistazo.
            'cap_por_ciclo' => (int) env('CIRCUITO_THOMAS_AUTOMERGE_CAP', 5),

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
        | CONSOLIDADO ESTRATÉGICO (#566 E4)
        |----------------------------------------------------------------------
        |
        | Lo genuinamente estratégico no se adivina, pero tampoco tiene por qué bloquear N items
        | por separado: se junta en UNA sola pregunta con la recomendación de Thomas para cada
        | punto, para que Irving conteste en bloque.
        |
        | `horas_default`: si no contesta en ese plazo, Thomas procede con la opción reversible
        | recomendada y la deja registrada para revisión POSTERIOR. 0 = nunca procede solo.
        */
        'consolidado' => [
            'enabled'       => (bool) env('CIRCUITO_THOMAS_CONSOLIDADO', true),
            'horas_default' => (int) env('CIRCUITO_THOMAS_CONSOLIDADO_HORAS', 48),
            'doc_path'      => base_path('docs/decisiones-pendientes-irving.md'),
        ],

        /*
        | CIERRE — qué exige Thomas antes de dar un item por terminado. Son los criterios de
        | aceptación mínimos y comunes a todo item; los específicos viven en el propio item.
        */
        'cierre' => [
            // El item debe traer con qué revisarlo: en llano y con el lugar de la UI donde verlo.
            'exige_reporte_coloquial' => (bool) env('CIRCUITO_THOMAS_EXIGE_REPORTE', true),
            'exige_enlace_revision'   => (bool) env('CIRCUITO_THOMAS_EXIGE_ENLACE', true),
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
                'cola', 'auto-merge', 'automerge', 'reaper', 'thomas', 'circuito', 'escalamiento',
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
    | Qué resuelve: el circuito ya sabe REPARTIR (scheduler) y JUZGAR (Thomas/revisor/autopilot),
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
        */
        'carriles' => [
            'paralelo' => [
                'GestionRed', 'Inventario', 'Mapas', 'Tickets', 'Scheduling', 'Hub',
                'Finanzas', 'Mensajes', 'Vendedores', 'Talento', 'Flotas', 'Marketing',
                'Payments', 'MegaFamilia', 'VoIP', 'WhatsAppAgent', 'PortalCliente', 'PortalPago',
                'Embajadores', 'Reportes', 'Usuarios', 'Roadmap / Circuito CC',
            ],
            'serializado' => ['Clientes', 'Configuracion', 'CRM', 'ModuleManager'],
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
        | Nota: ADEMÁS de esto, todo gap pasa por la frontera dura de Thomas
        | (`circuito.thomas.escalamiento`: producción / borrar datos / dinero / credenciales). Lo
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
    ],

    /*
    | #432 Fase 3 — Brief COMPLETO (multi-pregunta). ON: la bandeja usa la columna JSON `preguntas`
    | (varias preguntas por item) y la escalación las puebla TODAS de una. OFF: fallback al modelo
    | viejo de una sola `opciones`/`opcion_elegida`. Un item SIN `preguntas` cae al fallback aunque
    | esté ON, así que activar esto NO rompe los items existentes.
    */
    'multi_pregunta'   => (bool) env('CIRCUITO_MULTI_PREGUNTA', true),

];
