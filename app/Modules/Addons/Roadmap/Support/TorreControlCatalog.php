<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * Catálogo de controles del circuito, agrupado por ACTOR (item #943).
 *
 * Fuente única: `docs/circuito/inventario-de-controles.md` (los ~70 controles medidos) +
 * `docs/circuito/plan-configuracion-torre.md` §1-2 (las 3 cubetas y la agrupación por actor).
 * Este catálogo NO decide autorización — solo describe. La UI (`TorreConfigPanel.vue`) solo
 * pinta lo que este catálogo declara; el endpoint real de guardado sigue siendo
 * `RoadmapController::torreConfigGuardar()`, que valida sus propios 4 campos.
 *
 * ⚠️ GUARDRAIL DURO (#943): ningún control se vuelve editable en UI sin pasar por el catálogo
 * de acciones de la Fase 3 (#881). Mientras #881 no aterrice, `self::YA_EDITABLES` es la ÚNICA
 * lista de claves que se pintan verde/editable — todo lo demás que el plan clasifica como
 * "candidato verde" se pinta AZUL con la nota `pendiente_881 = true`. Subir algo a
 * `YA_EDITABLES` sin que exista su endpoint de guardado sería pintar un botón que no hace nada.
 */
class TorreControlCatalog
{
    /**
     * Claves ya editables desde `torreConfigGuardar()` (Entrega 1). Única fuente de verdad de
     * qué es realmente verde hoy — el resto de "candidatos verdes" del plan quedan azules.
     */
    private const YA_EDITABLES = [
        'torre_config.nivel_automatizacion',
        'auditor.enabled',
        'auditor.cap_por_ciclo',
        'auditor.min_intervalo_minutos',
    ];

    public static function grupos(): array
    {
        return [
            [
                'clave'   => 'automatizacion',
                'titulo'  => 'Automatización',
                'resumen' => 'El techo global y los interruptores maestros del circuito.',
                'controles' => self::gruposAutomatizacion(),
            ],
            [
                'clave'   => 'thomas',
                'titulo'  => 'Thomas',
                'resumen' => 'Decide sin esperar a Irving cuando la acción es reversible; arma el merge y el consolidado.',
                'controles' => self::gruposThomas(),
            ],
            [
                'clave'   => 'autopilot',
                'titulo'  => 'Autopilot',
                'resumen' => 'Toma la opción recomendada de un brief cuando trae confianza alta y reversible.',
                'controles' => self::gruposAutopilot(),
            ],
            [
                'clave'   => 'revisor',
                'titulo'  => 'Revisor',
                'resumen' => 'Agente adversarial que autoriza los B técnicos seguros sin gastar el turno de Irving.',
                'controles' => self::gruposRevisor(),
            ],
            [
                'clave'   => 'destrabador',
                'titulo'  => 'Des-trabador',
                'resumen' => 'Vacía la bandeja de \'esperando_merge_irving\': auto-merge de lo ya verificado.',
                'controles' => self::gruposDestrabador(),
            ],
            [
                'clave'   => 'auditor',
                'titulo'  => 'Auditor',
                'resumen' => 'El generador de trabajo: escanea el sistema y crea los items que llenan la cola.',
                'controles' => self::gruposAuditor(),
            ],
            [
                'clave'   => 'terminales',
                'titulo'  => 'Terminales',
                'resumen' => 'Cuántas terminales corren a la vez, y quién vigila que no se cuelguen.',
                'controles' => self::gruposTerminales(),
            ],
            [
                'clave'   => 'canal_respuesta',
                'titulo'  => 'Canal de respuesta',
                'resumen' => 'Motor todavía no existe — no se pinta ningún control (fase 5 del plan).',
                'controles' => [],
                'no_existe' => true,
            ],
            [
                'clave'   => 'guardrails',
                'titulo'  => 'Guardrails',
                'resumen' => 'Nunca expuestos: apagar cualquiera de estos es apagar la protección misma.',
                'controles' => [],
                // La lista real vive en `RoadmapController::GUARDRAILS` (una sola fuente, ya
                // consumida por la respuesta como `guardrails`) — no se duplica aquí.
                'fuente_externa' => 'guardrails',
            ],
        ];
    }

    // ── AUTOMATIZACIÓN ──────────────────────────────────────────────────────────────────────
    private static function gruposAutomatizacion(): array
    {
        return [
            self::control(
                'torre_config.nivel_automatizacion',
                'tabla torre_config',
                self::fuenteLiteral(null, 'ver panel — Política base'),
                'TorreAutomationPolicy',
                'Techo global: hasta qué nivel_riesgo aprueba la máquina sin Irving.',
                'Subirlo deja que más items C se ejecuten solos; bajarlo manda más trabajo a tu bandeja.',
                'verde',
            ),
            self::control(
                'roadmap_items.automatizacion_override',
                'columna en roadmap_items',
                self::fuenteLiteral('hereda', 'default en todos los items'),
                'TorreAutomationPolicy',
                'Override de la política base para UN item concreto.',
                'Permite que un item puntual se salte el techo global (para arriba o para abajo).',
                'verde',
            ),
            self::control(
                'circuito_pausado',
                'tabla settings',
                self::fuenteSetting('circuito_pausado', 'apagado'),
                'todos los actores',
                'Kill switch global — el botón rojo de la Torre.',
                'Con esto encendido nada avanza sin que tú lo autorices a mano, ni Thomas ni el autopilot.',
                'verde',
            ),
            self::control(
                'circuito_revisor',
                'tabla settings',
                self::fuenteSetting('circuito_revisor', 'apagado'),
                'scopeDespachable',
                'Si un item aprobado_revisor es despachable.',
                'Apagarlo deja el veredicto del Revisor sin efecto: esos items esperan a Irving igual.',
                'verde',
            ),
            self::control(
                'circuito_modo',
                'tabla settings',
                self::fuenteSetting('circuito_modo', 'autonomo'),
                'circuito:flags → vuelta.sh',
                'Modo del ejecutor on-box.',
                'Cambia cómo corre cada vuelta en el servidor; no es cosmético.',
                'verde',
            ),
            self::control(
                'circuito_modo_integracion',
                'tabla settings',
                self::fuenteSetting('circuito_modo_integracion', 'auto-merge'),
                'MergeRunner',
                'Si el merge a main es automático o manual.',
                'En manual, ninguna rama verificada llega a main sin que alguien la mergee a mano.',
                'verde',
            ),
        ];
    }

    // ── THOMAS ───────────────────────────────────────────────────────────────────────────────
    private static function gruposThomas(): array
    {
        return [
            self::control(
                'thomas.enabled',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.enabled'),
                'ThomasService',
                'Apaga a Thomas entero.',
                'Sin Thomas, ninguna terminal recibe respuesta al instante: todo lo que dude escala a requiere_irving.',
                'verde',
            ),
            self::control(
                'thomas.mecanico.max_nivel',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.mecanico.max_nivel'),
                'ThomasService',
                'Sub-techo del carril mecánico (auto-aprueba lo que no tiene nada que decidir).',
                'El nivel efectivo es min(techo global, este). Subirlo por encima del global no hace nada.',
                'verde',
            ),
            self::control(
                'thomas.mecanico.tope_diario',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.mecanico.tope_diario'),
                'ThomasService',
                'Auto-aprobaciones mecánicas por día.',
                'Es el freno de mano del carril mecánico: si la política se desmadra, el daño de un día está acotado a este número.',
                'verde',
            ),
            self::control(
                'thomas.ya_decidido.max_nivel',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.ya_decidido.max_nivel'),
                'TorreAutomationPolicy',
                'Sub-techo del carril "brief ya respondido" (evaluarYaDecidido).',
                'Nace en C porque hoy no mira ningún tope; bajarlo hace que un C con brief completo espere a Irving igual.',
                'verde',
            ),
            self::control(
                'thomas.exige_reversible_sin_recomendada',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.exige_reversible_sin_recomendada'),
                'ThomasService',
                'Si ninguna opción es reversible y no hay recomendada, escala.',
                'Apagarlo hace que Thomas tome la primera opción sin más — más autonomía, más riesgo.',
                'verde',
            ),
            self::control(
                'thomas.automerge.enabled',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.automerge.enabled'),
                'elegibleAutoMerge',
                'Auto-merge de trabajo verificado.',
                'Apagarlo deja todo el trabajo terminado esperando que Irving lo mergee a mano.',
                'verde',
            ),
            self::control(
                'thomas.automerge.cap_por_ciclo',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.automerge.cap_por_ciclo'),
                'DestrabarCommand',
                'Merges por ciclo del destrabe.',
                'Un cap chico a propósito: un merge malo es más caro de deshacer que una aprobación mala.',
                'verde',
            ),
            self::control(
                'thomas.automerge.rutas_sensibles',
                'config/circuito.php',
                self::fuenteConfigCount('circuito.thomas.automerge.rutas_sensibles', 'patrones'),
                'elegibleAutoMerge',
                'Rutas que NUNCA auto-mergean aunque el título sea inocente.',
                'Es un guardrail — verificable contra el diff, no contra lo que dice el item. Editable = desactivable.',
                'azul',
            ),
            self::control(
                'thomas.automerge.patrones_destructivos',
                'config/circuito.php',
                self::fuenteConfigCount('circuito.thomas.automerge.patrones_destructivos', 'patrones'),
                'elegibleAutoMerge',
                'Patrones destructivos en el diff de migraciones que bloquean el auto-merge.',
                'Una migración con drop/truncate no se deshace con git revert — el esquema ya cambió.',
                'azul',
            ),
            self::control(
                'thomas.consolidado.enabled/horas_default/doc_path',
                'config/circuito.php',
                self::fuenteLiteral(null, 'ver config/circuito.php — thomas.consolidado'),
                'ThomasService',
                'Consolidado estratégico: junta preguntas de negocio en una sola pregunta para Irving.',
                'horas_default=0 significa que Thomas nunca procede solo en lo estratégico, siempre espera.',
                'verde',
            ),
            self::control(
                'thomas.cierre.exige_reporte_coloquial',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.cierre.exige_reporte_coloquial'),
                'ThomasService',
                'Exige reporte en llano antes de dar un item por cerrado.',
                'Apagarlo permite cerrar items sin explicar qué cambió ni dónde verlo.',
                'verde',
            ),
            self::control(
                'thomas.cierre.exige_enlace_revision',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.cierre.exige_enlace_revision'),
                'ThomasService',
                'Exige el deep-link a la UI real antes de cerrar.',
                'Apagarlo permite cerrar items sin decir dónde probarlos.',
                'verde',
            ),
            self::control(
                'thomas.esfuerzo.*',
                'config/circuito.php',
                self::fuenteLiteral(null, 'A=20 · B=45 · C=90 min base, tope 240'),
                'ThomasService',
                'Estimación de ETA por nivel de riesgo + tamaño del spec.',
                'Solo orientativo — nada se rechaza por pasarse del estimado.',
                'verde',
            ),
            self::control(
                'clasificador.reglas.*',
                'config/circuito.php',
                self::fuenteConfigCount('circuito.clasificador.reglas', 'módulos'),
                'ClasificarModuloCommand',
                'Mapa término → módulo para el footprint automático.',
                'Un item mal clasificado corre en paralelo con otro que sí lo toca y se pisan.',
                'azul',
            ),
        ];
    }

    // ── AUTOPILOT ────────────────────────────────────────────────────────────────────────────
    private static function gruposAutopilot(): array
    {
        return [
            self::control(
                'autopilot.enabled',
                'config/circuito.php',
                self::fuenteConfig('circuito.autopilot.enabled'),
                'AutopilotService',
                'Apaga el autopilot entero.',
                'Sin autopilot, ningún brief se auto-decide: todo espera a Irving o a Thomas.',
                'verde',
            ),
            self::control(
                'autopilot.max_nivel',
                'config/circuito.php',
                self::fuenteConfig('circuito.autopilot.max_nivel'),
                'AutopilotService, AutopilotCommand',
                'Nivel máximo que el autopilot puede decidir solo (ya NO gobierna scopeDespachable, ver #944).',
                'Subirlo deja que items C se ejecuten sin que Irving los revise antes; el efectivo sigue topado por la política base.',
                'verde',
            ),
            self::control(
                'autopilot.umbral_confianza',
                'config/circuito.php',
                self::fuenteConfig('circuito.autopilot.umbral_confianza'),
                'AutopilotService',
                'Confianza mínima que debe traer la opción recomendada del brief.',
                'Con "alta" (default), un brief viejo sin ese dato explícito se queda en tu bandeja.',
                'verde',
            ),
            self::control(
                'autopilot.requiere_reversible',
                'config/circuito.php',
                self::fuenteConfig('circuito.autopilot.requiere_reversible'),
                'AutopilotService',
                'Exige reversible:true en la opción recomendada para B/C.',
                'Apagarlo deja que el autopilot decida algo irreversible sin ese candado.',
                'verde',
            ),
            self::control(
                'autopilot.ventana_gracia',
                'config/circuito.php',
                self::fuenteConfig('circuito.autopilot.ventana_gracia'),
                'AutopilotService',
                'Minutos de espera desde el brief antes de decidir.',
                '0 = decide de inmediato, sin ventana para que alcances a vetar.',
                'verde',
            ),
        ];
    }

    // ── REVISOR ──────────────────────────────────────────────────────────────────────────────
    private static function gruposRevisor(): array
    {
        return [
            self::control(
                'revisor.model_routine',
                'config/circuito.php',
                self::fuenteConfig('circuito.revisor.model_routine'),
                'RevisorService',
                'Modelo usado para el veredicto de rutina (la mayoría de los B).',
                'Cambiarlo cambia costo y calidad de CADA veredicto del Revisor.',
                'verde',
            ),
            self::control(
                'revisor.model_hard',
                'config/circuito.php',
                self::fuenteConfig('circuito.revisor.model_hard'),
                'RevisorService',
                'Modelo para 2ª opinión (B difícil/borderline) y briefs de C.',
                'Es el modelo que arma los briefs de decisión que lees — más caro, se usa menos.',
                'verde',
            ),
            self::control(
                'revisor.model',
                'config/circuito.php',
                self::fuenteConfig('circuito.revisor.model'),
                'fallback de model_routine',
                'Alias de compatibilidad — mismo valor que model_routine.',
                'Editarlo por separado confundiría con model_routine; se muestra pero no se edita aparte.',
                'azul',
            ),
            self::control(
                'revisor.max_tokens',
                'config/circuito.php',
                self::fuenteConfig('circuito.revisor.max_tokens'),
                'RevisorService',
                'Tope de tokens del veredicto del Revisor.',
                'Muy bajo y el veredicto se corta a medias; muy alto y cada llamada cuesta más.',
                'verde',
            ),
            self::control(
                'revisor.brief_tokens',
                'config/circuito.php',
                self::fuenteConfig('circuito.revisor.brief_tokens'),
                'RevisorService',
                'Tope de tokens de cada brief de decisión.',
                'Un brief cortado a medias te llega incompleto a la bandeja.',
                'verde',
            ),
            self::control(
                'revisor.alcance.denylist',
                'config/circuito.php',
                self::fuenteConfigCount('circuito.revisor.alcance.denylist', 'términos', 'circuito.revisor.alcance.denylist_word'),
                'RevisorService::enAlcance',
                'Prefiltro PROPIO del Revisor — qué NO evalúa la IA (escala directo sin gastar el turno).',
                'Es una SEGUNDA lista, distinta de la frontera dura de Thomas. Cambiar un término cambia qué escala y qué no: eso es un diff revisado, no un textarea (fase 6 del plan, no aquí).',
                'azul',
            ),
            self::control(
                'revisor.perfil_path',
                'config/circuito.php',
                self::fuenteLiteral('docs/perfil-decisiones-irving.md', null),
                'RevisorService::perfilIrving',
                'Perfil de decisiones de Irving, inyectado al prompt del Revisor.',
                'Lo edita Irving a mano en ese archivo; el circuito nunca le escribe.',
                'azul',
            ),
            self::control(
                'RevisorService::TRIAJE_C_PLAIN',
                'RevisorService.php (constante en duro)',
                self::fuenteConstCount(\App\Modules\Addons\Roadmap\Services\RevisorService::class, 'TRIAJE_C_PLAIN'),
                'triajeC',
                'Términos que fuerzan nivel C en el triaje de nivel null.',
                'Huérfano: cambiarlo hoy exige editar código, no config. Migrar es fase 6 del plan.',
                'verde',
            ),
            self::control(
                'RevisorService::TRIAJE_C_WORD',
                'RevisorService.php (constante en duro)',
                self::fuenteConstCount(\App\Modules\Addons\Roadmap\Services\RevisorService::class, 'TRIAJE_C_WORD'),
                'triajeC',
                'Ídem TRIAJE_C_PLAIN, con match de palabra completa (términos cortos/ambiguos).',
                'Huérfano — mismo caso que TRIAJE_C_PLAIN.',
                'verde',
            ),
            self::control(
                'RevisorService::TRIAJE_NEGACIONES + VENTANA_BYTES',
                'RevisorService.php (constante en duro)',
                self::fuenteLiteral(null, 'ver RevisorService.php:269,285'),
                'triajeC',
                'Detecta negación cercana a un término de TRIAJE_C (ej. "no toca prod") y afloja.',
                'Huérfano — la ventana de bytes decide qué tan "cerca" cuenta como negación.',
                'verde',
            ),
        ];
    }

    // ── DES-TRABADOR ─────────────────────────────────────────────────────────────────────────
    private static function gruposDestrabador(): array
    {
        return [
            self::control(
                'thomas.destrabe_bandeja.enabled',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.destrabe_bandeja.enabled'),
                'SchedulerCommand::tickDestrabe',
                'Enciende el destrabe automático de la bandeja.',
                'Apagado, todo lo terminado se acumula en esperando_merge_irving sin que nadie lo mueva.',
                'verde',
            ),
            self::control(
                'thomas.destrabe_bandeja.intervalo_minutos',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.destrabe_bandeja.intervalo_minutos'),
                'tickDestrabe',
                'Cadencia del destrabe — throttle para no re-escanear la bandeja cada minuto.',
                'Bajarlo reacciona más rápido; subirlo deja más items esperando entre pasadas.',
                'verde',
            ),
            self::control(
                'thomas.destrabe_bandeja.limit',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.destrabe_bandeja.limit'),
                'tickDestrabe',
                'Tamaño del lote que procesa cada pasada.',
                'Antes causaba el fallo de sort-buffer (#864, ya corregido); subirlo mucho puede repetir ese síntoma.',
                'verde',
            ),
        ];
    }

    // ── AUDITOR ──────────────────────────────────────────────────────────────────────────────
    private static function gruposAuditor(): array
    {
        return [
            self::control(
                'auditor.enabled',
                'config/circuito.php',
                self::fuenteConfig('circuito.auditor.enabled'),
                'AuditorService::habilitado',
                'Kill switch propio del Auditor.',
                'Apagado, la cola nunca se rellena sola: con la cola vacía las terminales quedan ociosas.',
                'verde',
                editableAyer: true,
            ),
            self::control(
                'auditor.cap_por_ciclo',
                'config/circuito.php',
                self::fuenteConfig('circuito.auditor.cap_por_ciclo'),
                'AuditorService::ciclo',
                'Tope DURO de items nuevos por corrida.',
                'Aunque el escaneo encuentre 300 huecos, nunca se crean más de este número de una vez.',
                'verde',
                editableAyer: true,
            ),
            self::control(
                'auditor.min_intervalo_minutos',
                'config/circuito.php',
                self::fuenteConfig('circuito.auditor.min_intervalo_minutos'),
                'AuditorService::debeCorrer',
                'Cooldown entre escaneos.',
                'Bajarlo mucho escanea ~1,500 archivos PHP más seguido de lo necesario.',
                'verde',
                editableAyer: true,
            ),
            self::control(
                'auditor.umbral_cola',
                'config/circuito.php',
                self::fuenteConfig('circuito.auditor.umbral_cola'),
                'AuditorService::debeCorrer',
                'Solo genera trabajo si la cola reclamable está por debajo de esto.',
                'Con la cola llena, generar más solo ensuciaría la Hoja de Ruta.',
                'verde',
            ),
            self::control(
                'auditor.items_por_modulo_por_ciclo',
                'config/circuito.php',
                self::fuenteConfig('circuito.auditor.items_por_modulo_por_ciclo'),
                'AuditorService (round-robin)',
                'Cuántos items de UN módulo se toman antes de pasar al siguiente.',
                'Subirlo puede llenar la cola de un solo módulo y dejar terminales ociosas por falta de módulos distintos.',
                'verde',
            ),
            self::control(
                'auditor.carriles.paralelo / .serializado',
                'config/circuito.php',
                self::fuenteConfigCount('circuito.auditor.carriles.paralelo', 'módulos', 'circuito.auditor.carriles.serializado'),
                'modulosAAuditar',
                'Qué módulos se auditan y en qué orden — serializado = acoplados, van de a uno.',
                'Mover un módulo a paralelo cuando SÍ está acoplado puede hacer que dos items se pisen.',
                'azul',
            ),
            self::control(
                'auditor.detectores.*',
                'config/circuito.php',
                self::fuenteDetectores('circuito.auditor.detectores'),
                'detectarGaps',
                'Qué tipos de gap busca el Auditor (huecos, enlaces rotos, TODOs, andamiaje, sin_clasificar, semilla).',
                'Apagar un detector deja de generar ESE tipo de item sin tocar los demás.',
                'verde',
            ),
            self::control(
                'auditor.terminos_producto',
                'config/circuito.php',
                self::fuenteConfigCount('circuito.auditor.terminos_producto', 'términos'),
                'AuditorService (gap → bandeja vs. cola)',
                'Qué palabras dentro de un TODO/FIXME delatan una decisión de producto.',
                'Si aparece uno de estos, el gap va a tu bandeja con la pregunta en vez de crearse como item mecánico.',
                'azul',
            ),
            self::control(
                'auditor.excluir_modulos',
                'config/circuito.php',
                self::fuenteConfigCount('circuito.auditor.excluir_modulos', 'módulos'),
                'modulosAAuditar',
                'Módulos que el motor nunca audita (Demo, Security, Voice).',
                'Agregar un módulo aquí lo saca por completo de la generación automática de trabajo.',
                'verde',
            ),
            self::control(
                'auditor.spec.*',
                'config/circuito.php',
                self::fuenteLiteral(null, 'umbral 30% · cap 25/item · min 3 rutas'),
                'medirContraSpec',
                'Detectores de declaración: huecos entre lo que dice module.json y las rutas reales.',
                'Sube la cobertura del contrato público — nunca borra ni crea código, solo declara.',
                'verde',
            ),
        ];
    }

    // ── TERMINALES ───────────────────────────────────────────────────────────────────────────
    private static function gruposTerminales(): array
    {
        return [
            self::control(
                'paralelismo',
                'config/circuito.php',
                self::fuenteConfig('circuito.paralelismo'),
                'getParalelismo',
                'Cuántas terminales/worktrees corren a la vez.',
                'Subirlo sin más CPU/RAM puede saturar el box; bajarlo deja trabajo listo sin tomar.',
                'verde',
            ),
            self::control(
                'max_builds',
                'config/circuito.php (fantasma) / env CIRCUITO_MAX_BUILDS',
                self::fuenteLiteral(null, 'se ajusta por .env — ver npm-build.sh'),
                'deploy/circuito/npm-build.sh (NO PHP)',
                'Builds npm simultáneos máximo.',
                '⚠️ Editar config/circuito.php aquí NO hace nada: el semáforo real lee el env CIRCUITO_MAX_BUILDS directo.',
                'azul',
            ),
            self::control(
                'reaper.max_reintentos',
                'config/circuito.php',
                self::fuenteConfig('circuito.reaper.max_reintentos'),
                'reap-stuck',
                'Reclamos fallidos del MISMO item antes de rendirse y escalar a Irving.',
                'Muy bajo escala items que solo necesitaban un reintento más; muy alto cicla un item roto.',
                'verde',
            ),
            self::control(
                'reaper.gracia_minutos',
                'config/circuito.php',
                self::fuenteConfig('circuito.reaper.gracia_minutos'),
                'reap-stuck',
                'Minutos de gracia antes de creerle a un slot que se ve libre.',
                'Muy bajo puede re-encolar un item cuya vuelta apenas está arrancando.',
                'verde',
            ),
            self::control(
                'watchdog.scheduler_stale_seg',
                'config/circuito.php (migrado #941)',
                self::fuenteConfig('circuito.watchdog.scheduler_stale_seg'),
                'WatchdogService',
                'Segundos tras los que el scheduler se considera caído.',
                'Muy bajo dispara falsos positivos por jitter normal; muy alto tarda en notar un scheduler muerto.',
                'verde',
            ),
            self::control(
                'watchdog.worker_hung_seg',
                'config/circuito.php (migrado #941)',
                self::fuenteConfig('circuito.watchdog.worker_hung_seg'),
                'WatchdogService',
                'Segundos de latido frío tras los que un worker se considera colgado → reap.',
                'Muy bajo mata vueltas legítimas que solo van lentas; muy alto deja un worker colgado más tiempo.',
                'verde',
            ),
            self::control(
                'watchdog.max_intentos',
                'config/circuito.php (migrado #941)',
                self::fuenteConfig('circuito.watchdog.max_intentos'),
                'WatchdogService',
                'Reintentos de auto-recuperación por causa antes de escalar a Irving.',
                'Sube el umbral de paciencia del watchdog antes de despertarte.',
                'verde',
            ),
            self::control(
                'watchdog.log_cap',
                'config/circuito.php (migrado #941)',
                self::fuenteConfig('circuito.watchdog.log_cap'),
                'WatchdogService',
                'Cuántos eventos de bitácora del watchdog se conservan.',
                'Parámetro de forma — no cambia comportamiento, solo cuánto historial ves.',
                'azul',
            ),
            self::control(
                'escalacion_bucle_umbral',
                'config/circuito.php (migrado #941)',
                self::fuenteConfig('circuito.escalacion_bucle_umbral'),
                'RoadmapItem::contarEscalacion',
                'Cuántas escalaciones IGUALES seguidas sacan un item del pool automático (anti-bucle).',
                'Es el control que más items congela. Subirlo deja ciclar más veces antes de rendirse; bajarlo saca items del pool más rápido.',
                'verde',
            ),
            self::control(
                'RoadmapReportService::RESUMEN_MAX',
                'RoadmapReportService.php (constante en duro)',
                self::fuenteConst(\App\Modules\Addons\Roadmap\Services\RoadmapReportService::class, 'RESUMEN_MAX'),
                'espejo de comentarios_claude',
                'Tamaño máximo del espejo legible de comentarios_claude.',
                'Parámetro de forma — migrarlo añadiría superficie sin añadir control real.',
                'azul',
            ),
            self::control(
                'worker_nombres',
                'config/circuito.php (override en settings)',
                self::fuenteConfigCount('circuito.worker_nombres', 'nombres'),
                'roster de la Torre (pestaña Terminales)',
                'Nombres legibles de los slots wt-1..wt-N (renombrables por Irving).',
                'Solo cosmético — "trabajado por Ada" en vez de "wt-3". No cambia comportamiento.',
                'verde',
            ),
            self::control(
                'vuelta_timeout_seg',
                'config/circuito.php · env CIRCUITO_TIMEOUT',
                self::fuenteConfig('circuito.vuelta_timeout_seg'),
                'deploy/circuito/vuelta.sh (timeout real) + Torre (reloj)',
                'Límite real de una vuelta.',
                'El valor de aquí es solo el espejo que pinta el reloj; el límite real lo aplica el timeout del script en el servidor.',
                'azul',
            ),
            self::control(
                'thomas.cabida.umbral_segundos',
                'config/circuito.php',
                self::fuenteConfig('circuito.thomas.cabida.umbral_segundos'),
                'circuito:cabida',
                'Mediana histórica (segundos) por encima de la cual un item se recomienda descomponer antes de empezar.',
                'Queda por debajo del timeout real (600s) a propósito: conviene descomponer antes de rozar la pared.',
                'verde',
            ),
        ];
    }

    // ── HELPERS DE CONSTRUCCIÓN ──────────────────────────────────────────────────────────────
    private static function control(
        string $clave,
        string $donde,
        array $valorResuelto,
        string $quienLee,
        string $queGobierna,
        string $consecuencia,
        string $bucketDoc,
        bool $editableAyer = false,
    ): array {
        $yaEditable = $editableAyer || in_array($clave, self::YA_EDITABLES, true);
        $bucketEfectivo = $bucketDoc;
        $pendiente881 = false;

        if ($bucketDoc === 'verde' && !$yaEditable) {
            $bucketEfectivo = 'azul';
            $pendiente881 = true;
        }

        return [
            'clave'          => $clave,
            'donde'          => $donde,
            'valor'          => $valorResuelto['valor'],
            'quien_lee'      => $quienLee,
            'que_gobierna'   => $queGobierna,
            'consecuencia'   => $consecuencia,
            'bucket'         => $bucketEfectivo,
            'editable'       => $bucketEfectivo === 'verde',
            'pendiente_881'  => $pendiente881,
            'nota'           => $pendiente881
                ? 'editable pendiente del catálogo de acciones (#881)'
                : null,
        ];
    }

    // ── HELPERS DE VALOR (siempre EN VIVO — nunca se copia un número a mano) ───────────────────
    private static function fuenteConfig(string $path): array
    {
        $v = config($path);

        return ['valor' => self::formatear($v)];
    }

    private static function fuenteSetting(string $clave, string $default): array
    {
        try {
            $v = \Illuminate\Support\Facades\DB::table('settings')->where('key', $clave)->value('value');
        } catch (\Throwable) {
            $v = null;
        }

        return ['valor' => $v !== null && $v !== '' ? (string) $v : $default];
    }

    private static function fuenteConfigCount(string $path, string $unidad, ?string $path2 = null): array
    {
        $a = (array) config($path);
        $n = count($a);
        if ($path2) {
            $n += count((array) config($path2));
        }

        return ['valor' => "{$n} {$unidad}"];
    }

    /**
     * Lee la constante VIVA vía reflexión (no un número copiado a mano): funciona sin importar
     * su visibilidad (varias de estas constantes son `private`, ej. RESUMEN_MAX).
     */
    private static function leerConst(string $class, string $const): mixed
    {
        try {
            return (new \ReflectionClassConstant($class, $const))->getValue();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function fuenteConst(string $class, string $const): array
    {
        return ['valor' => self::formatear(self::leerConst($class, $const))];
    }

    private static function fuenteConstCount(string $class, string $const): array
    {
        $v = self::leerConst($class, $const);
        $n = is_array($v) ? count($v) : 0;

        return ['valor' => "{$n} términos"];
    }

    private static function fuenteDetectores(string $path): array
    {
        $d = (array) config($path);
        $on = count(array_filter($d));

        return ['valor' => "{$on}/" . count($d) . ' activos'];
    }

    private static function fuenteLiteral(?string $valor, ?string $nota): array
    {
        return ['valor' => $valor ?? $nota ?? '—'];
    }

    private static function formatear(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'sí' : 'no';
        }
        if (is_array($v)) {
            return count($v) . ' elementos';
        }
        if ($v === null || $v === '') {
            return '—';
        }

        return (string) $v;
    }
}
