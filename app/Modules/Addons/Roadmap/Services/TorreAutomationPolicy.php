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
 * Producción · borrar datos · dinero · credenciales/seguridad. **No se pueden levantar desde ninguna
 * configuración**: ni con `nivel_automatizacion = autonomo`, ni con `automatizacion_override = auto`.
 * Si algún día hay que levantarlos será otro trabajo con su propia discusión, nunca un checkbox.
 *
 * La detección la hace `ThomasService::categoriaFronteraDura()`. **Aquí no hay ni una lista nueva de
 * términos**: esa lista ya costó dos incidentes documentados («palabra completa, no substring» y «no
 * distingue mención de negación»), y una segunda copia envejeciendo por separado sería el tercero.
 * Si la frontera necesita crecer, crece en Thomas.
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
     * El techo GLOBAL de `nivel_riesgo` que la máquina puede aprobar sola. `null` = ninguno (modo
     * manual: todo va a Irving). ESTA es la lectura única; nadie más consulta la config para esto.
     */
    public function techoGlobal(): ?string
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
    public function nivelEfectivo(string $actor): ?string
    {
        $global = $this->techoGlobal();
        if ($global === null) {
            return null;   // modo manual: no hay actor que apruebe nada
        }

        $clave = self::SUBTECHOS[$actor] ?? null;
        if ($clave === null) {
            return $global;
        }

        $sub = strtoupper((string) config($clave, 'B'));
        if (! isset(self::ORDEN[$sub])) {
            $sub = 'B';   // valor raro en config → cae al tope seguro, nunca al permisivo
        }

        return self::ORDEN[$sub] < self::ORDEN[$global] ? $sub : $global;
    }

    /** ¿Este actor puede aprobar un item de este nivel? */
    public function permite(string $actor, ?string $nivel): bool
    {
        $techo = $this->nivelEfectivo($actor);
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
    public function estadoInicial(RoadmapItem $item, string $actor): string
    {
        if ($this->tocaFronteraDura($item) !== null) {
            return 'requiere_irving';
        }

        $override = (string) ($item->automatizacion_override ?? 'hereda');

        if ($override === 'manual') {
            return 'requiere_irving';
        }

        if ($override === 'auto') {
            return $this->estadoAprobado($item);
        }

        return $this->permite($actor, $item->nivel_riesgo)
            ? $this->estadoAprobado($item)
            : 'requiere_irving';
    }

    /**
     * La categoría de frontera dura que toca el item, o null. Delega en Thomas: **cero listas
     * nuevas**. Mira el mismo texto que el carril mecánico (título + descripción + prompt) — mirar
     * menos campos que él dejaría pasar aquí lo que allá se frena, que es cómo dos guards con la
     * misma regla terminan dando veredictos distintos.
     */
    public function tocaFronteraDura(RoadmapItem $item): ?string
    {
        return $this->thomas->categoriaFronteraDura(
            (string) $item->title . ' ' . (string) $item->description . ' ' . (string) $item->prompt
        );
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
        $global = $this->techoGlobal();

        $actores = [];
        foreach (array_keys(self::SUBTECHOS) as $actor) {
            $sub = strtoupper((string) config(self::SUBTECHOS[$actor], 'B'));
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
            'techo_global'         => $global,
            'actores'              => $actores,
            'matriz'               => $matriz,
            'topes_duros'          => array_keys((array) config('circuito.thomas.escalamiento', [])),
            'auditor'              => [
                'activo'          => $cfg->auditor_activo,
                'max_por_corrida' => $cfg->auditor_max_por_corrida,
                'cooldown_min'    => $cfg->auditor_cooldown_min,
            ],
        ];
    }
}
