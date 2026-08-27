<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\TorreConfig;

/**
 * EL CORAZÓN — un solo lugar donde vive la decisión de «¿esto puede avanzar sin Irving?».
 *
 * ── POR QUÉ ─────────────────────────────────────────────────────────────────────────────────────
 *
 * Antes de esta clase había CUATRO topes de nivel repartidos, y uno de ellos mentía:
 * `circuito.autopilot.max_nivel` gobernaba de hecho a TODOS los actores desde
 * `RoadmapItem::scopeDespachable`, con un nombre que decía «autopilot». Un panel que dijera
 * «Estándar» mientras ese knob valía `C` no sería un panel incompleto: sería un panel que miente, y
 * el daño no se queda en ese control — la gente le cree unas semanas, descubre que no, y deja de
 * creerle también a lo que sí era cierto.
 *
 * ── EL DISEÑO: TECHO GLOBAL + SUB-TECHOS POR ACTOR ──────────────────────────────────────────────
 *
 * El techo global (`TorreConfig`) es la única fuente de verdad. Los sub-techos por actor sobreviven
 * con su significado LITERAL, y el nivel efectivo de cada uno es `min(global, sub-techo)`.
 *
 * Que `thomas.mecanico` sea más conservador que `autopilot` es una distinción REAL: el carril
 * mecánico no tiene un brief humano detrás. Colapsarlos en un solo número perdería esa asimetría.
 *
 * **El candado es una DESIGUALDAD, no una igualdad** (`TorreTechosCoherentesTest`): divergir hacia
 * abajo está permitido y es sano; hacia arriba es imposible.
 *
 * ── LOS CUATRO TOPES DUROS ──────────────────────────────────────────────────────────────────────
 *
 * Producción · borrar datos · dinero · credenciales/seguridad. **Ningún ajuste de AUTOMATIZACIÓN los
 * levanta**: ni `nivel_automatizacion = autonomo`, ni `automatizacion_override = auto`. Eso no
 * cambió y es lo que este archivo garantiza.
 *
 * ⚠️ Lo que SÍ cambió (#648, 2026-08-27, decisión explícita de Irving): la LISTA y el EFECTO de cada
 * frontera se gobiernan desde la pestaña «Configuración» → Fronteras (`circuito_fronteras`). Aquel
 * «será otro trabajo con su propia discusión, nunca un checkbox» fue justamente ese trabajo — y la
 * contrapartida es que cada perilla viene con su número al lado (cuántos items dispara, cuántas
 * veces la válvula la abrió) y cada cambio queda auditado, aflojar como alerta.
 *
 * La detección la hace `ThomasService::fronteraDuraDeItem()` sobre `FronterasService`. **Aquí no hay
 * ni una lista nueva de términos**: esa lista ya costó dos incidentes documentados («palabra
 * completa, no substring» y «no distingue mención de negación»), y una segunda copia envejeciendo
 * por separado sería el tercero. Si la frontera necesita crecer, crece en su tabla.
 */
class TorreAutomationPolicy
{
    /** Orden de `nivel_riesgo`. Comparar por índice, nunca por string. */
    public const ORDEN = ['A' => 1, 'B' => 2, 'C' => 3];

    /**
     * Sub-techo de cada actor que aprueba por su cuenta. La clave es el actor tal como se identifica
     * en el log del item, para que el rastro y la política usen el mismo vocabulario.
     *
     * `revisor` y `destrabe` NO tienen sub-techo propio: los gobierna sólo el techo global. Es
     * deliberado — su criterio es un veredicto de IA caso por caso, no un carril con reglas fijas,
     * así que un segundo número no diría nada que el global no diga ya.
     */
    public const SUBTECHOS = [
        'autopilot'          => 'circuito.autopilot.max_nivel',
        'thomas.mecanico'    => 'circuito.thomas.mecanico.max_nivel',
        'thomas.ya_decidido' => 'circuito.thomas.ya_decidido.max_nivel',
    ];

    public function __construct(
        private TorreConfigService $config,
        private ThomasService $thomas,
    ) {
    }

    // ── TECHOS ──────────────────────────────────────────────────────────────────────────────────

    /**
     * La POLÍTICA BASE: hasta qué `nivel_riesgo` aprueba la máquina por defecto.
     * `null` = ninguno (modo `manual`). ESTA es la lectura única; nadie más consulta la config.
     *
     * ⚠️ SE LLAMA «BASE» Y NO «TECHO» A PROPÓSITO. En `estandar`/`asistido`/`autonomo` un
     * `automatizacion_override = auto` sobre un item concreto SÍ puede excederla — así que no es un
     * techo, es un valor por defecto, y llamarlo techo sería estrenar el panel con un nombre que
     * miente. Es exactamente la enfermedad que esta fase viene curando (`autopilot.max_nivel`
     * gobernando a todos, `destrabe_bandeja.enabled` diciendo que sí con el comando muerto).
     *
     * La ÚNICA excepción es `manual`, que sí es absoluto: ver `estadoInicial()`.
     *
     * Los SUB-TECHOS por actor sí son techos de verdad: nunca exceden la base
     * (`TorreTechosCoherentesTest` lo fija como desigualdad).
     */
    public function politicaBase(): ?string
    {
        return $this->config->get()->techoGlobal();
    }

    /** El nivel de automatización tal como lo nombra el panel (`manual`…`autonomo`). */
    public function nivelAutomatizacion(): string
    {
        return (string) $this->config->get()->nivel_automatizacion;
    }

    /**
     * Nivel EFECTIVO de un actor = `min(techo_global, sub-techo del actor)`.
     * Un actor sin sub-techo declarado queda gobernado sólo por el global.
     */
    public function nivelEfectivo(string $actor, ?string $subTechoSimulado = null): ?string
    {
        $global = $this->politicaBase();
        if ($global === null) {
            return null;   // modo manual: no hay actor que apruebe nada
        }

        // `$subTechoSimulado` es SÓLO para el simulador de la pantalla («¿cuántos items calificarían
        // si moviera la perilla a B?»). Ninguna vía que ESCRIBA lo pasa: la simulación no puede
        // convertirse por descuido en una forma de saltarse el techo real.
        $sub = $subTechoSimulado !== null && isset(self::ORDEN[$subTechoSimulado])
            ? $subTechoSimulado
            : $this->subTecho($actor);
        if ($sub === null) {
            return $global;
        }

        return self::ORDEN[$sub] < self::ORDEN[$global] ? $sub : $global;
    }

    /**
     * El sub-techo declarado de un actor, o `null` si no tiene (sólo lo gobierna el global).
     *
     * #648 — el del AUTOPILOT es el único movible desde la pantalla: `torre_config.autopilot_max_nivel`
     * gana sobre `config/circuito.php` cuando está puesto. Se resuelve en un solo lugar para que no
     * haya dos sitios decidiendo cuál manda — que es cómo `autopilot.max_nivel` acabó gobernando a
     * todos los actores con un nombre que decía otra cosa.
     */
    public function subTecho(string $actor): ?string
    {
        if ($actor === 'autopilot') {
            return $this->config->get()->autopilotMaxNivel();
        }

        $clave = self::SUBTECHOS[$actor] ?? null;
        if ($clave === null) {
            return null;
        }

        $sub = strtoupper((string) config($clave, 'B'));

        return isset(self::ORDEN[$sub]) ? $sub : 'B';   // valor raro → tope seguro, nunca el permisivo
    }

    /** ¿Este actor puede aprobar un item de este nivel? */
    public function permite(string $actor, ?string $nivel, ?string $subTechoSimulado = null): bool
    {
        $techo = $this->nivelEfectivo($actor, $subTechoSimulado);
        if ($techo === null || $nivel === null || ! isset(self::ORDEN[$nivel])) {
            return false;   // sin nivel no está triado: no se ejecuta solo
        }

        return self::ORDEN[$nivel] <= self::ORDEN[$techo];
    }

    // ── LA DECISIÓN ─────────────────────────────────────────────────────────────────────────────

    /**
     * En qué estado debe quedar un item que un ACTOR AUTOMÁTICO quiere aprobar.
     *
     * ORDEN DE EVALUACIÓN — los topes van PRIMERO y siempre ganan:
     *   1-4. Frontera dura (producción · borrar datos · dinero · credenciales)  → requiere_irving
     *   5.   `automatizacion_override = 'manual'`                               → requiere_irving
     *   6.   `automatizacion_override = 'auto'`                                 → aprobado (salta la matriz)
     *   7.   Matriz: nivel del item vs nivel efectivo del actor
     *
     * NO gobierna la aprobación iniciada por un HUMANO (`RoadmapController::store`, donde la
     * autorización es Irving mismo) ni la vía externa (`RoadmapCircuitoService::guard()`, token
     * Cowork/MCP). Esas dos quedan fuera por decisión explícita.
     */
    public function estadoInicial(RoadmapItem $item, string $actor, ?string $subTechoSimulado = null): string
    {
        // (1-4) FRONTERA DURA. Gana siempre, por delante de todo. No se levanta desde ninguna
        // configuración: ni con `autonomo`, ni con `override = auto`.
        if ($this->tocaFronteraDura($item) !== null) {
            return 'requiere_irving';
        }

        // (5) `manual` ES ABSOLUTO. Ningún override lo sobrepasa.
        //
        // Es un PARO DE EMERGENCIA, y un paro con excepciones no es un paro. El modo de fallo que
        // esto cierra: el override es PEGAJOSO —se pone una vez y ahí se queda—, así que bajar la
        // política a `manual` por un incidente dejaría volando los items con un `auto` puesto hace
        // tres semanas. `manual` tiene que significar manual.
        if ($this->politicaBase() === null) {
            return 'requiere_irving';
        }

        $override = (string) ($item->automatizacion_override ?? 'hereda');

        // (6) Bajar la automatización de un item se permite siempre, sin fricción.
        if ($override === 'manual') {
            return 'requiere_irving';
        }

        // (7) Subir SÍ puede exceder la política base — pero sólo en `estandar`/`asistido`/
        // `autonomo`, que son gradaciones de «cuánta autonomía por defecto». Una excepción
        // deliberada sobre un item concreto es una autorización explícita de Irving, y es el caso
        // que le da valor al override: política en `estandar` y aun así quiero que ESTE B corra solo.
        if ($override === 'auto') {
            return $this->estadoAprobado($item);
        }

        // (8) Matriz: nivel del item contra el nivel efectivo del actor.
        return $this->permite($actor, $item->nivel_riesgo, $subTechoSimulado)
            ? $this->estadoAprobado($item)
            : 'requiere_irving';
    }

    /**
     * EL OVERRIDE ES DE UN SOLO USO: se consume en la primera resolución de estado y el item vuelve
     * a `hereda`.
     *
     * La autorización que da Irving es **para este item, ahora** — no un permiso permanente que
     * sobreviva a cambios de política que haga meses después. Esto elimina el problema de la
     * caducidad sin inventar una fecha de caducidad, que sería otro número que nadie recuerda.
     *
     * ⚠️ **SE CONSUME AL DESPACHAR, NO AL APROBAR** (opción B, decisión de Irving 2026-08-19). El
     * consumo real vive en el UPDATE atómico de `RoadmapCircuitoService::claimNextParalelo()`.
     *
     * Consumirlo al aprobar tenía dos fallos: (1) quemaba la autorización aunque el item nunca
     * llegara a correr —el actor aprueba y algo falla después—, y (2) dejaba el item
     * aprobado-y-nunca-despachable, porque el gate de nivel de `scopeDespachable` ya no vería el
     * `auto` que lo hacía elegible. La autorización es «para este item, ahora», y «ahora» es cuando
     * corre.
     *
     * Este método queda como la operación EN MEMORIA (mutación sin guardar), para quien necesite
     * simularlo o para un consumo manual desde la UI. El camino vivo es el del reclamo.
     *
     * @return bool si había un override que consumir
     */
    public function consumirOverride(RoadmapItem $item): bool
    {
        $previo = (string) ($item->automatizacion_override ?? 'hereda');
        if ($previo === 'hereda') {
            return false;
        }

        $item->automatizacion_override = 'hereda';

        return true;
    }

    /**
     * Items con un override VIGENTE que excede la política base. Es el contador de la portada: una
     * excepción que nadie ve es un agujero; una que se cuenta en la portada es una decisión.
     *
     * @return array{total:int,ids:array<int,int>}
     */
    public function overridesPorEncimaDeLaBase(): array
    {
        $base = $this->politicaBase();

        $q = RoadmapItem::query()
            ->whereNull('archivado_at')
            ->where('automatizacion_override', 'auto')
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado']);

        // Con la base en `manual` NINGÚN override corre, así que todos los `auto` vigentes exceden
        // la base por definición. Con base A/B/C, sólo los de nivel por encima.
        if ($base !== null) {
            $permitidos = array_slice(['A', 'B', 'C'], 0, self::ORDEN[$base]);
            $q->where(fn ($w) => $w->whereNotIn('nivel_riesgo', $permitidos)->orWhereNull('nivel_riesgo'));
        }

        $ids = $q->orderBy('id')->limit(200)->pluck('id')->map(fn ($i) => (int) $i)->all();

        return ['total' => count($ids), 'ids' => $ids];
    }

    /**
     * La categoría de frontera dura que toca el item, o null. Delega en Thomas: **cero listas
     * nuevas**. Mira el mismo texto que el carril mecánico (título + descripción + prompt) — mirar
     * menos campos que él dejaría pasar aquí lo que allá se frena, que es cómo dos guards con la
     * misma regla terminan dando veredictos distintos.
     */
    public function tocaFronteraDura(RoadmapItem $item): ?string
    {
        // Delega en `fronteraDuraDeItem`, que además honra el veredicto de la VÁLVULA DE NACIMIENTO.
        // Éste es el guard que de verdad retenía los items de Irving: fuerza `requiere_irving` «por
        // delante de todo», así que un item que sólo MENCIONABA «producción» quedaba en su bandeja
        // para siempre aunque el triaje ya lo hubiera leído como B. Un item que TOCA la frontera lo
        // sigue frenando igual — la válvula sólo despeja las menciones, y sólo cuando está segura.
        return $this->thomas->fronteraDuraDeItem($item);
    }

    /**
     * El estado aprobado que corresponde al nivel. Reusa los que el pool YA reconoce
     * (A → `aprobado_claude`, B/C → `aprobado_revisor`): no se inventa un estado nuevo.
     */
    private function estadoAprobado(RoadmapItem $item): string
    {
        return $item->nivel_riesgo === 'A' ? 'aprobado_claude' : 'aprobado_revisor';
    }

    // ── PARA LA UI ──────────────────────────────────────────────────────────────────────────────

    /**
     * La foto completa que pinta el panel: techo global + nivel efectivo de cada actor + la matriz.
     * Si Thomas está en `B` con el global en `C`, eso SE VE, porque es información.
     */
    public function panorama(): array
    {
        $cfg    = $this->config->get();
        $global = $this->politicaBase();

        $actores = [];
        foreach (array_keys(self::SUBTECHOS) as $actor) {
            $sub = (string) $this->subTecho($actor);
            $actores[$actor] = [
                'sub_techo' => $sub,
                'efectivo'  => $this->nivelEfectivo($actor),
                'topado'    => $global !== null && isset(self::ORDEN[$sub])
                               && self::ORDEN[$sub] > self::ORDEN[$global],
            ];
        }
        // Actores sin sub-techo: sólo el global.
        foreach (['revisor', 'destrabe'] as $actor) {
            $actores[$actor] = ['sub_techo' => null, 'efectivo' => $global, 'topado' => false];
        }

        $matriz = [];
        foreach (['A', 'B', 'C'] as $nivel) {
            foreach (TorreConfig::NIVELES as $lvl) {
                $techo = TorreConfig::TECHO_POR_NIVEL[$lvl];
                $matriz[$nivel][$lvl] = ($techo !== null && self::ORDEN[$nivel] <= self::ORDEN[$techo])
                    ? 'auto' : 'irving';
            }
        }

        return [
            'nivel_automatizacion' => $cfg->nivel_automatizacion,
            'niveles'              => TorreConfig::NIVELES,
            'politica_base'        => $global,
            'manual_es_absoluto'   => $global === null,
            'overrides_excedentes' => $this->overridesPorEncimaDeLaBase(),
            'actores'              => $actores,
            'matriz'               => $matriz,
            // #648 — la lista viva, no la de config: desde la pestaña «Configuración» se pueden
            // apagar categorías, y un panel que siguiera listando la config mostraría fronteras
            // que ya no están vigentes.
            'topes_duros'          => array_keys(app(FronterasService::class)->mapa()),
            'auditor'              => [
                'activo'          => $cfg->auditor_activo,
                'max_por_corrida' => $cfg->auditor_max_por_corrida,
                'cooldown_min'    => $cfg->auditor_cooldown_min,
            ],
        ];
    }
}
