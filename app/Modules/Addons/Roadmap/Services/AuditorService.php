<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Support\InventarioSemilla;
use App\Services\EnvRuntimeScanner;
use FilesystemIterator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route as RouteFacade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * MOTOR DE AUDITORÍA CONTINUA (#559, "Item Madre") — el GENERADOR de trabajo del circuito.
 *
 * El circuito ya sabía repartir (`circuito:scheduler`) y juzgar (Jarvis / revisor / autopilot),
 * pero no sabía GENERAR: cuando la cola se vaciaba, las 6 terminales se quedaban ociosas hasta que
 * un humano escribiera items. Este servicio cierra ese hueco: escanea el sistema módulo por módulo,
 * detecta lo que falta, y crea los items-hijo que lo cierran.
 *
 * FRONTERAS (por diseño, no por omisión):
 *   - NO reparte. Crea items y se va; despachar sigue siendo del scheduler.
 *   - NO aprueba. Los items nacen por `RoadmapIntakeService` → `pendiente_revision`, como cualquiera.
 *     Un item MECÁNICO nace nivel A, y un A pendiente ya es reclamable por el pool (política vigente);
 *     el que decide sigue siendo el triaje de siempre, no este motor.
 *   - NO decide de producto. Lo que huele a "¿qué debe hacer esto?" va a la bandeja de Irving con la
 *     pregunta concreta. Fabricar una decisión suya sería el peor modo de fallo posible.
 *
 * CONTENCIÓN (para que no se desborde): kill-switch propio + kill-switch global del circuito, cap
 * duro por ciclo, dedup contra items abiertos Y cerrados, intervalo mínimo entre escaneos, y
 * frontera dura de Jarvis aplicada a TODO gap antes de dejarlo salir como mecánico.
 */
class AuditorService
{
    /** Sentinel de footprint desconocido, igual que en RoadmapCircuitoService. */
    private const MODULO_DESCONOCIDO = 'Sin clasificar';

    private const SETTING_ULTIMA_CORRIDA = 'circuito_auditor_ultima_corrida';
    private const SETTING_ULTIMO_REPORTE = 'circuito_auditor_ultimo_reporte';

    /** #1015 — memoria de cobertura por módulo (JSON: {modulo: {ultima_auditoria_at, nuevos}}). */
    private const SETTING_COBERTURA = 'circuito_auditor_cobertura_modulos';

    /** #1015 — corridas EN VIVO seguidas con 0 nuevos en TODO el ciclo (fuente "código"). */
    private const SETTING_RACHA_SECA = 'circuito_auditor_racha_seca';

    /** #712 — Nivel 2 ("EL GASTO"): ISO8601 de cuándo se apagó, o ausente = armado. */
    private const SETTING_GASTO_APAGADO_DESDE = 'circuito_auditor_gasto_apagado_desde';

    /** Cache por-request del índice de rutas registradas. */
    private ?array $indiceRutas = null;

    public function __construct(
        private RoadmapCircuitoService $circuito,
        private RoadmapIntakeService $intake,
        private TorreConfigService $torreConfig,
    ) {
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // GATING — cuándo corre
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * KILL-SWITCH propio del motor. Independiente del kill switch global.
     *
     * Fuente única = `torre_config.auditor_activo` (panel de la Torre → Configuración), no
     * `config('circuito.auditor.enabled')` — un panel que muestra un valor que no gobierna nada
     * es el "panel que miente" que `TorreAutomationPolicy` existe para evitar (item #852).
     */
    public function habilitado(): bool
    {
        return (bool) $this->torreConfig->get()->auditor_activo;
    }

    /**
     * Profundidad REAL de la cola = items que el scheduler podría despachar AHORA.
     *
     * Deliberadamente NO cuenta `aprobado_irving` a secas: al 2026-08-08 había 87 items en ese
     * estado y sólo 0 reclamables (35 fuera del pool automático, 26 esperando merge, 25 rotulados
     * [BLOCKED-]/[PARKED-]). Contar los aprobados "en bruto" haría creer que hay cola cuando las
     * terminales están de brazos cruzados — justo el error que este motor viene a corregir.
     *
     * Por eso reusa `ejecutablesParalelo()`, que es la MISMA puerta del scheduler.
     */
    public function profundidadCola(): int
    {
        // Sin excluir módulos en vuelo y con tope alto: queremos el tamaño de la cola, no el plan
        // de reparto de esta vuelta.
        return count($this->circuito->ejecutablesParalelo([], 200));
    }

    /** Slots (worktrees) libres ahora mismo. */
    public function slotsLibres(): int
    {
        $n = $this->circuito->getParalelismo();
        $libres = 0;
        for ($k = 1; $k <= $n; $k++) {
            $path = "/home/meganet/circuito/wt-{$k}.lock";
            $f = @fopen($path, 'c');
            if (! $f) {
                continue;
            }
            if (flock($f, LOCK_EX | LOCK_NB)) {
                $libres++;
                flock($f, LOCK_UN);
            }
            fclose($f);
        }

        return $libres;
    }

    public function ultimaCorrida(): ?int
    {
        $v = DB::table('settings')->where('key', self::SETTING_ULTIMA_CORRIDA)->value('value');

        return $v === null ? null : (int) $v;
    }

    /**
     * ¿Debe correr un ciclo? Devuelve el diagnóstico completo para que el comando lo reporte
     * (que NO corra es información tan útil como que corra).
     */
    public function debeCorrer(bool $forzar = false): array
    {
        $cola   = $this->profundidadCola();
        $slots  = $this->slotsLibres();
        $umbral = (int) config('circuito.auditor.umbral_cola', 3);
        $racha  = $this->rachaSeca();

        // #712 — NIVEL 2 ("EL GASTO"). Se evalúa (y, si toca, se RE-ARMA) una sola vez aquí: el
        // propio chequeo intenta reactivarse si ya se completó un item real desde que se apagó —
        // así una corrida del scheduler basta, sin depender de un observer.
        $gastoApagado = $this->gastoApagado();

        $base = [
            'cola' => $cola, 'slots_libres' => $slots, 'umbral' => $umbral, 'racha_seca' => $racha,
            'gasto_apagado' => $gastoApagado,
        ];

        if (! $this->habilitado()) {
            return $base + ['corre' => false, 'motivo' => 'Motor APAGADO (auditor_activo = false en Torre → Configuración).'];
        }
        if ($this->circuito->isPaused()) {
            return $base + ['corre' => false, 'motivo' => 'Circuito en PAUSA (kill switch global): el motor no crea nada.'];
        }
        if ($forzar) {
            return $base + ['corre' => true, 'motivo' => 'Forzado (--forzar): se ignoran umbral, intervalo y el apagado por sequía.'];
        }

        // Gate DURO, previo a cola/intervalo: si está apagado, no genera nada, ocupe o no la cola
        // terminales libres.
        if ($gastoApagado) {
            $umbralGasto = (int) config('circuito.auditor.sequia.gasto_racha_umbral', 2);

            return $base + ['corre' => false, 'motivo' => "Generador APAGADO por sequía (Nivel 2, #712): "
                . "{$racha} ciclo(s) seguido(s) sin hallazgos nuevos (≥ {$umbralGasto}). Se re-arma solo "
                . 'cuando un item REAL (sin auditor_fingerprint, no generado por este motor) se complete.'];
        }

        $base_min  = (int) $this->torreConfig->get()->auditor_cooldown_min;
        $intervalo = $this->intervaloEfectivo($base_min, $racha);
        $ultima    = $this->ultimaCorrida();
        if ($ultima !== null && (time() - $ultima) < $intervalo * 60) {
            $faltan  = (int) ceil(($intervalo * 60 - (time() - $ultima)) / 60);
            $sequia  = $intervalo > $base_min
                ? " (alargado por sequía: {$racha} corrida(s) seguidas sin hallazgos nuevos, base {$base_min} min)"
                : '';

            return $base + ['corre' => false, 'motivo' => "Escaneado hace poco: faltan ~{$faltan} min para el próximo (intervalo {$intervalo} min{$sequia})."];
        }

        if ($cola >= $umbral) {
            return $base + ['corre' => false, 'motivo' => "Cola con {$cola} item(s) reclamables (umbral {$umbral}): hay trabajo, no hace falta generar."];
        }

        return $base + ['corre' => true, 'motivo' => "Cola en {$cola} (< umbral {$umbral}) con {$slots} terminal(es) libre(s)."];
    }

    /**
     * #1015 — corridas EN VIVO (ciclo completo, no `--modulo`) seguidas con 0 nuevos en TODO el
     * ciclo. Se resetea a 0 en cuanto una corrida encuentra algo — el backoff es sólo mientras la
     * fuente de código está de verdad agotada.
     */
    public function rachaSeca(): int
    {
        $v = DB::table('settings')->where('key', self::SETTING_RACHA_SECA)->value('value');

        return $v === null ? 0 : (int) $v;
    }

    /** Intervalo real a usar, alargado (backoff lineal, con techo) si la sequía cruzó el umbral. */
    private function intervaloEfectivo(int $base, int $racha): int
    {
        $umbral = (int) config('circuito.auditor.sequia.racha_umbral', 3);
        if ($racha < $umbral) {
            return $base;
        }

        $incremento = (int) config('circuito.auditor.sequia.incremento_minutos', 15);
        $max        = (int) config('circuito.auditor.sequia.intervalo_max_minutos', 120);
        $exceso     = $racha - $umbral + 1;

        return min($max, $base + $exceso * $incremento);
    }

    /**
     * #712 (Thomas Parte 2) — NIVEL 2, "EL GASTO": ¿el generador está apagado?
     *
     * Distinto de `intervaloEfectivo()` (Nivel 1, LA SONDA): eso sólo ALARGA cada cuánto se
     * escanea y nunca deja de escanear del todo. Esto es un apagado DURO — tras
     * `sequia.gasto_racha_umbral` ciclos EN VIVO seguidos sin hallazgos nuevos, deja de ocupar
     * terminales por completo, aunque la cola esté seca y haya slots libres.
     *
     * Antes de responder, intenta RE-ARMARSE: si ya se completó un item REAL desde que se apagó,
     * se reactiva aquí mismo y responde `false`. Sin esto habría que enganchar un observer sólo
     * para una condición que un query ya resuelve en cada chequeo del scheduler.
     */
    public function gastoApagado(): bool
    {
        $desde = $this->gastoApagadoDesde();
        if ($desde === null) {
            return false;
        }
        if ($this->huboItemRealCompletadoDesde($desde)) {
            $this->rearmarGasto();

            return false;
        }

        // #891 Fase 3a — HALF-OPEN: deja pasar UN sondeo cada `gasto_reintento_min` minutos sin
        // rearmar el timestamp. Si el ciclo vuelve a salir seco, `evaluarApagarGasto()` lo renueva
        // al cierre — así el costo queda acotado a un sondeo por ventana, nunca indefinido.
        if ($this->reintentoActivo() && $this->venceReintento($desde)) {
            return false;
        }

        return true;
    }

    /**
     * #891 Fase 3a — estado del freno para UI (Fase 3b lo consume, sin duplicar esta lectura):
     * `armado` = nunca se apagó; `disparado` = apagado y el sondeo aún no toca (o el half-open
     * está desactivado); `medio_abierto` = apagado pero la ventana de reintento YA venció, el
     * próximo sondeo pasará. `reintento_en_segundos` es el countdown hasta ese punto (null si no
     * aplica: armado, o half-open desactivado).
     */
    public function estadoGastoUi(): array
    {
        $desde = $this->gastoApagadoDesde();
        if ($desde === null) {
            return ['estado' => 'armado', 'desde' => null, 'reintento_en_segundos' => null];
        }

        if (! $this->reintentoActivo()) {
            return ['estado' => 'disparado', 'desde' => $desde, 'reintento_en_segundos' => null];
        }

        $vence = Carbon::parse($desde)->addMinutes((int) config('circuito.auditor.sequia.gasto_reintento_min', 30));
        if ($vence->isPast()) {
            return ['estado' => 'medio_abierto', 'desde' => $desde, 'reintento_en_segundos' => 0];
        }

        return [
            'estado' => 'disparado',
            'desde' => $desde,
            'reintento_en_segundos' => now()->diffInSeconds($vence),
        ];
    }

    /**
     * #891 Fase 3a — ¿el half-open está activo? Lee de `torre_config` si la Fase 3b ya agregó la
     * columna; si no, cae al default de fábrica en `config/circuito.php` (mismo patrón que usa
     * `debeCorrer()` con `auditor_cooldown_min`, línea ~165).
     *
     * TODO(#925 Fase 3b): cuando exista `torre_config.auditor_gasto_reintento_activo`, cambiar a
     * `(bool) $this->torreConfig->get()->auditor_gasto_reintento_activo`.
     */
    private function reintentoActivo(): bool
    {
        return (bool) config('circuito.auditor.sequia.gasto_reintento_activo', true);
    }

    /**
     * #891 Fase 3a — ¿ya venció la ventana de reintento desde que se apagó el gasto? Mismo
     * fallback que `reintentoActivo()`: `torre_config` cuando exista (Fase 3b), config por ahora.
     *
     * TODO(#925 Fase 3b): cambiar a `(int) $this->torreConfig->get()->auditor_gasto_reintento_min`.
     */
    private function venceReintento(string $desde): bool
    {
        $minutos = (int) config('circuito.auditor.sequia.gasto_reintento_min', 30);

        return Carbon::parse($desde)->addMinutes($minutos)->isPast();
    }

    /** ISO8601 de cuándo se apagó el gasto, o null si está armado. */
    private function gastoApagadoDesde(): ?string
    {
        $v = DB::table('settings')->where('key', self::SETTING_GASTO_APAGADO_DESDE)->value('value');

        return $v !== null && $v !== '' ? (string) $v : null;
    }

    /**
     * ¿Se completó un item REAL después de `$desde`? "Real" = SIN `auditor_fingerprint` — el
     * motor sella esa columna en TODO lo que él mismo crea (`crear()` más abajo), así que su
     * ausencia es justo la señal de "no lo generó Thomas". Usa `completed_at` (no `updated_at`):
     * el modelo lo sella UNA sola vez al completar (`RoadmapItem::booted()`), así que no se
     * dispara por una edición posterior cualquiera del mismo item.
     */
    private function huboItemRealCompletadoDesde(string $desde): bool
    {
        return RoadmapItem::where('estado_aprobacion', 'completado')
            ->whereNull('auditor_fingerprint')
            ->where('completed_at', '>', $desde)
            ->exists();
    }

    private function rearmarGasto(): void
    {
        DB::table('settings')->where('key', self::SETTING_GASTO_APAGADO_DESDE)->delete();
        Log::channel('roadmap_externo')->info('auditor-gasto-rearmado', [
            'motivo' => 'item real completado desde el apagado',
        ]);
    }

    /**
     * Apaga el gasto si la racha (ya actualizada) cruzó el umbral de Nivel 2. Se llama SOLO al
     * cierre de un ciclo COMPLETO en vivo (mismo punto donde ya se actualiza `racha_seca`) — un
     * `--modulo` parcial no dice nada sobre si la fuente está agotada.
     *
     * #891 Fase 3a — idempotente A PROPÓSITO (ya no corta si ya estaba apagado): así el sondeo del
     * half-open, cuando vuelve a salir seco, RENUEVA el timestamp en vez de dejarlo viejo — es lo
     * que acota el costo a un sondeo cada `gasto_reintento_min` minutos en vez de indefinido. Si la
     * racha volvió a 0 (hubo hallazgo nuevo), esta función ni se dispara: el guard corta antes.
     */
    private function evaluarApagarGasto(int $racha): void
    {
        $umbral = (int) config('circuito.auditor.sequia.gasto_racha_umbral', 2);
        if ($racha < $umbral) {
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => self::SETTING_GASTO_APAGADO_DESDE],
            ['value' => now()->toIso8601String(), 'updated_at' => now()]
        );
        Log::channel('roadmap_externo')->info('auditor-gasto-apagado', ['racha_seca' => $racha, 'umbral' => $umbral]);
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // ORDEN DE TRABAJO — los dos carriles
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Módulos a auditar, EN ORDEN: primero el carril paralelo (acoplamiento ~0, sus items pueden
     * correr a la vez), después la base acoplada (Clientes/Configuracion/CRM/ModuleManager).
     * Los excluidos (Demo, Security, Voice) nunca entran.
     */
    public function modulosAAuditar(): array
    {
        $c          = (array) config('circuito.auditor.carriles', []);
        $excluir    = array_map('mb_strtolower', (array) config('circuito.auditor.excluir_modulos', []));
        $serializado = (array) ($c['serializado'] ?? []);
        $ordenado   = array_merge((array) ($c['paralelo'] ?? []), $serializado);

        $modulos = array_values(array_filter(
            array_unique($ordenado),
            fn ($m) => ! in_array(mb_strtolower($m), $excluir, true)
        ));

        // #809 — un carril que no resuelve a ningún directorio audita EN VACÍO (0 huecos/enlaces/
        // TODOs/andamiaje/spec, siempre) sin que nada lo diga: el bug real que dejó 14/41 gaps
        // invisibles. Avisar en el log en vez de callarse; no se excluye de la lista (podría ser
        // temporal — un módulo recién movido — y sacarlo solo escondería la señal).
        foreach ($modulos as $m) {
            if ($this->rutaModulo($m) === null) {
                Log::channel('roadmap_externo')->warning('auditor-carril-no-resuelve', [
                    'modulo'  => $m,
                    'detalle' => "El carril «{$m}» no resuelve a ningún directorio de módulo "
                        . "(app/Modules/Core|Addons/{$m}, ni por alias en circuito.auditor.alias_directorio) "
                        . '→ se audita en vacío.',
                ]);
            }
        }

        // #1015 — MEMORIA DE COBERTURA: dentro de cada carril (el carril sigue mandando — la base
        // acoplada sigue yendo siempre después de la paralela, esa propiedad no se toca), prioriza
        // los módulos NUNCA auditados y luego los de auditoría más VIEJA, en vez de recorrer
        // siempre la lista en el mismo orden fijo. `usort` es estable desde PHP 8.0: sin cobertura
        // registrada (recién desplegado), el orden de config se conserva tal cual.
        $cobertura = $this->cobertura();
        $esSerial  = array_flip(array_map('mb_strtolower', $serializado));
        $carrilDe  = fn (string $m) => isset($esSerial[mb_strtolower($m)]) ? 1 : 0;
        $ultimaDe  = fn (string $m) => $cobertura[$m]['ultima_auditoria_at'] ?? null;

        usort($modulos, function (string $a, string $b) use ($carrilDe, $ultimaDe) {
            $ca = $carrilDe($a);
            $cb = $carrilDe($b);
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }
            $ua = $ultimaDe($a);
            $ub = $ultimaDe($b);
            if ($ua === $ub) {
                return 0;
            }
            if ($ua === null) {
                return -1;   // nunca auditado → primero
            }
            if ($ub === null) {
                return 1;
            }

            return $ua <=> $ub;   // ISO8601: orden lexicográfico = orden temporal
        });

        return $modulos;
    }

    /** #1015 — mapa `modulo => {ultima_auditoria_at, nuevos}` persistido en `settings`. */
    public function cobertura(): array
    {
        $raw = DB::table('settings')->where('key', self::SETTING_COBERTURA)->value('value');
        if (! $raw) {
            return [];
        }
        $d = json_decode((string) $raw, true);

        return is_array($d) ? $d : [];
    }

    /** ¿El módulo está en su DoD de Fase 1? = sin gaps MECÁNICOS detectables. */
    public function moduloEnDoD(string $modulo): bool
    {
        foreach ($this->detectarGaps($modulo) as $g) {
            if ($g['clase'] === 'mecanico') {
                return false;
            }
        }

        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // DETECCIÓN DE GAPS
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * FASE 2B — MEDIR LA REALIDAD CONTRA EL SPEC DEL MÓDULO (`module.json`).
     *
     * ── LAS DOS CAPAS DEL SPEC (no confundirlas: ambas sirven, y son distintas) ──────────────────
     *
     *   1. **`module.json` → ESTRUCTURA.** Endpoints, permisos, pantallas. Vive con el código y se
     *      versiona con él. Es lo que este método mide HOY, entero por LOOKUP: cada hallazgo traza
     *      a un conteo, no a un juicio.
     *
     *   2. **Item `[SPEC]` → INTENCIÓN.** Criterios de DoD en prosa que Irving escribe desde la
     *      Torre sin desplegar código. **PENDIENTE, no descartado:** hoy hay 0 items `[SPEC]`, así
     *      que no habría nada que leer, pero el hueco es legítimo. La versión anterior de este
     *      docblock lo daba como el ÚNICO contrato; era el contrato equivocado para la estructura,
     *      no una mala idea para la intención.
     *
     * ── POR QUÉ EL PRIMER PRODUCTO SON HUECOS DE DECLARACIÓN ────────────────────────────────────
     *
     * El Paso 0 midió que el spec describe ~3.7 % de la superficie real (117 endpoints declarados
     * sobre 3,193 pares método+ruta). Un detector semántico perfecto sobre ese 3.7 % daría dos
     * docenas de items y volvería a secarse — el problema que veníamos a resolver, retrasado.
     *
     * Así que el generador arranca produciendo **su propio sustrato**: los huecos de DECLARACIÓN son
     * el gap mejor documentado del sistema y nadie los había contado como tal (16 módulos con rutas
     * y cero `api_endpoints`; 21 por debajo del umbral). Y cada módulo que completa su `module.json`
     * amplía la superficie que el detector puede medir en la vuelta siguiente: el generador se
     * alimenta a sí mismo, no por un truco de re-siembra sino porque su primer trabajo es construir
     * el instrumento con el que va a medir después.
     *
     * ── LA REGLA QUE NO SE NEGOCIA ──────────────────────────────────────────────────────────────
     *
     * **Detectar la discrepancia con certeza NO es saber qué falta.** El lookup da lo primero con
     * confianza 1.0 y lo segundo no lo da nunca. Por eso ningún hallazgo de aquí dice "falta
     * construir X": un detector que lo diga cuando X existe con otro nombre no produce ruido,
     * produce TRABAJO FABRICADO. Ver `detSpecDesalineada()`.
     */
    public function medirContraSpec(string $modulo): array
    {
        $cfg = (array) config('circuito.auditor.spec', []);
        if (! ($cfg['enabled'] ?? true)) {
            return [];
        }

        $spec = $this->leerManifiesto($modulo);
        if ($spec === null) {
            return [];   // sin `module.json` no hay spec contra el cual medir (no es un gap: es otro problema)
        }

        $rutas = $this->rutasDelModulo($modulo);
        $det   = (array) ($cfg['detectores'] ?? []);
        $gaps  = [];

        if ($det['modulo_sin_declarar'] ?? true) {
            $gaps = array_merge($gaps, $this->detSpecSinDeclarar($modulo, $spec, $rutas, $cfg));
        }
        if ($det['declaracion_incompleta'] ?? true) {
            $gaps = array_merge($gaps, $this->detSpecIncompleta($modulo, $spec, $rutas, $cfg));
        }
        if ($det['desalineada'] ?? true) {
            $gaps = array_merge($gaps, $this->detSpecDesalineada($modulo, $spec, $cfg));
        }
        if ($det['permiso_inexistente'] ?? true) {
            $gaps = array_merge($gaps, $this->detSpecPermisos($modulo, $spec));
        }

        return $gaps;
    }

    // ── 2B/1: módulo con rutas registradas y CERO api_endpoints ────────────────────────────────

    private function detSpecSinDeclarar(string $modulo, array $spec, array $rutas, array $cfg): array
    {
        $n = count($rutas);
        if ($n < (int) ($cfg['min_rutas'] ?? 3) || count($spec['api_endpoints'] ?? []) > 0) {
            return [];
        }

        $cap   = (int) ($cfg['cap_por_item'] ?? 25);
        $pedir = min($n, $cap);

        return [[
            'modulo'  => $modulo,
            'tipo'    => 'spec_modulo_sin_declarar',
            'clase'   => 'mecanico',
            // El TRAMO va en la clave para que la huella cambie cuando haya progreso: así la vuelta
            // siguiente puede pedir la tanda siguiente en vez de que el dedup la bloquee para
            // siempre. Sin progreso, la huella no cambia y no se re-crea (falla hacia el lado bueno).
            'clave'   => 'api_endpoints#t0',
            'titulo'  => "{$modulo}: declara sus endpoints en module.json ({$n} rutas registradas, 0 declaradas)",
            'detalle' => "El módulo tiene **{$n} rutas registradas** y su `module.json` no declara NINGÚN "
                . "`api_endpoints`. Sin declaración no hay contra qué medirlo: hoy es invisible para el motor "
                . "de auditoría, para el manual y para cualquier revisión de contrato.\n\n"
                . "**Qué hacer:** agregar hasta **{$pedir}** entradas a `api_endpoints` en "
                . "`app/Modules/*/{$modulo}/module.json`, empezando por las rutas de contrato público "
                . "(no hace falta declarar cada feed interno de datatable). Cada entrada:\n"
                . "`{\"method\": \"GET\", \"path\": \"/ruta/real\", \"description\": \"…\", \"permission\": \"permiso_real\"}`\n\n"
                . "**Verificable:** `php artisan circuito:inventario-spec --detalle` debe mostrar el módulo con "
                . "más endpoints y sin desajustes. El `path` tiene que coincidir con la ruta REAL "
                . "(`php artisan route:list`), y el `permission` tiene que existir en la tabla `permissions`.\n\n"
                . "Si el módulo sigue por debajo del umbral de cobertura, la siguiente vuelta del motor "
                . "generará la tanda siguiente. No hace falta declararlo todo de una.",
        ]];
    }

    // ── 2B/2: declara, pero por debajo del umbral de cobertura ─────────────────────────────────

    private function detSpecIncompleta(string $modulo, array $spec, array $rutas, array $cfg): array
    {
        $n = count($rutas);
        $d = count($spec['api_endpoints'] ?? []);
        if ($d === 0 || $n < (int) ($cfg['min_rutas'] ?? 3)) {
            return [];
        }

        $umbral = (int) ($cfg['umbral_cobertura'] ?? 30);
        $pct    = (int) round(100 * $d / max(1, $n));
        if ($pct >= $umbral) {
            return [];
        }

        $cap    = (int) ($cfg['cap_por_item'] ?? 25);
        $meta   = (int) ceil($n * $umbral / 100);
        $faltan = min($meta - $d, $cap);
        $tramo  = intdiv($d, max(1, $cap));   // ver nota sobre la huella en detSpecSinDeclarar()

        return [[
            'modulo'  => $modulo,
            'tipo'    => 'spec_declaracion_incompleta',
            'clase'   => 'mecanico',
            'clave'   => "api_endpoints#t{$tramo}",
            'titulo'  => "{$modulo}: amplía api_endpoints en module.json ({$d}/{$n} = {$pct}%, umbral {$umbral}%)",
            'detalle' => "El `module.json` declara **{$d}** endpoints de **{$n}** rutas registradas (**{$pct}%**), "
                . "por debajo del umbral de cobertura ({$umbral}%). Lo no declarado es superficie que el motor "
                . "de auditoría NO puede medir.\n\n"
                . "**Qué hacer:** agregar **{$faltan}** entradas más a `api_endpoints` (llegar al umbral pide "
                . "~{$meta} en total). Prioriza el contrato público: lo que otro módulo, la app móvil o un "
                . "integrador llamaría. No hace falta declarar cada feed interno.\n\n"
                . "**Verificable:** `php artisan circuito:inventario-spec --detalle`. El `path` debe coincidir "
                . "con `route:list` y el `permission` debe existir en `permissions`.\n\n"
                . "Es deliberadamente una TANDA, no el módulo entero: si sigue por debajo del umbral, la "
                . "vuelta siguiente genera la siguiente. Un item de «declara 200 endpoints» no es una tarea.",
        ]];
    }

    // ── 2B/3: declarado ≠ registrado. DIRECCIÓN DESCONOCIDA ────────────────────────────────────

    /**
     * ⚠️ EL DETECTOR MÁS DELICADO DE LOS CUATRO, y no por lo que detecta sino por cómo lo NOMBRA.
     *
     * Un endpoint declarado que no resuelve a ninguna ruta significa una de dos cosas, y el lookup
     * **no las distingue**: (a) se declaró y no se construyó, o (b) se construyó distinto y la
     * declaración envejeció. Medido el 2026-08-18, los dos casos grandes son del tipo (b): Flotas
     * declara `/api/flotas/*` cuando existen 65 rutas bajo `flotas/api/*` (prefijo invertido), y
     * Planes declara un esquema de URLs que nunca se construyó así.
     *
     * Por eso el item pregunta CUÁL DE LOS DOS LADOS se corrige, y jamás afirma que falte construir.
     */
    private function detSpecDesalineada(string $modulo, array $spec, array $cfg): array
    {
        $idx   = $this->indiceMetodoUri();
        $malos = [];

        foreach ($spec['api_endpoints'] ?? [] as $e) {
            if (! isset($idx[$this->claveRuta($e['method'] ?? 'GET', $e['path'] ?? '')])) {
                $malos[] = strtoupper($e['method'] ?? 'GET') . ' ' . ($e['path'] ?? '?');
            }
        }
        if (! $malos) {
            return [];
        }

        sort($malos);
        $muestra = array_slice($malos, 0, (int) ($cfg['muestra_desalineada'] ?? 12));
        $n       = count($malos);

        return [[
            'modulo'  => $modulo,
            'tipo'    => 'spec_desalineada',
            'clase'   => 'mecanico',
            // La huella deriva de la LISTA: si se arregla parte, cambia y puede volver a salir; si
            // nadie tocó nada, no se re-crea.
            'clave'   => 'desalineada#' . substr(sha1(implode('|', $malos)), 0, 12),
            'titulo'  => "{$modulo}: declaración y realidad no coinciden en {$n} endpoint(s) de module.json",
            'detalle' => "Estos endpoints están declarados en `module.json` y **no resuelven a ninguna ruta "
                . "registrada**:\n\n  · " . implode("\n  · ", $muestra)
                . ($n > count($muestra) ? "\n  · … y " . ($n - count($muestra)) . ' más' : '') . "\n\n"
                . "⚠️ **ESTO NO DICE QUE FALTE CONSTRUIRLOS.** La discrepancia es certera; su causa no. "
                . "Puede ser (a) declarado y nunca construido, o (b) construido con otra ruta y la declaración "
                . "envejeció. Casos reales medidos del tipo (b): Flotas declaraba `/api/flotas/*` teniendo 65 "
                . "rutas bajo `flotas/api/*`, y Planes declaraba URLs que nunca existieron así.\n\n"
                . "**Primera pregunta del trabajo: ¿cuál de los dos lados se corrige?** Compara contra "
                . "`php artisan route:list` filtrando por el módulo:\n"
                . "  · si la ruta existe con otro path/método → corrige el `module.json` (lo normal);\n"
                . "  · si de verdad no existe nada equivalente → el endpoint nunca se construyó: registra la "
                . "decisión de construirlo o de borrar la declaración.\n\n"
                . "Ambas salidas son válidas; elige la que deje declaración y código diciendo lo mismo.",
        ]];
    }

    // ── 2B/4: permiso declarado que no existe (guardia, no generador) ──────────────────────────

    /**
     * Hoy rinde CERO: los 111 permisos declarados existen los 111. **Eso es lo esperado, no un
     * detector roto.** Su valor es de GUARDIA CONTRA REGRESIONES — el día que alguien declare un
     * permiso que no sembró, o renombre uno en BD sin tocar los manifiestos, esto lo dice.
     */
    private function detSpecPermisos(string $modulo, array $spec): array
    {
        $declarados = [];
        foreach ($spec['api_endpoints'] ?? [] as $e) {
            if (! empty($e['permission'])) {
                $declarados[$e['permission']] = true;
            }
        }
        foreach ($spec['permissions'] ?? [] as $p) {
            $nombre = is_array($p) ? ($p['name'] ?? null) : $p;
            if ($nombre) {
                $declarados[$nombre] = true;
            }
        }
        if (! $declarados) {
            return [];
        }

        $existen = DB::table('permissions')->whereIn('name', array_keys($declarados))->pluck('name')->flip();
        $faltan  = array_values(array_filter(array_keys($declarados), fn ($n) => ! isset($existen[$n])));
        if (! $faltan) {
            return [];
        }
        sort($faltan);

        return [[
            'modulo'  => $modulo,
            'tipo'    => 'spec_permiso_inexistente',
            'clase'   => 'mecanico',
            'clave'   => 'permisos#' . substr(sha1(implode('|', $faltan)), 0, 12),
            'titulo'  => "{$modulo}: module.json declara " . count($faltan) . ' permiso(s) que no existen en la tabla',
            'detalle' => "Declarados en `module.json` y ausentes de la tabla `permissions`:\n\n  · "
                . implode("\n  · ", array_slice($faltan, 0, 20)) . "\n\n"
                . "Un permiso declarado que no existe no protege nada: `CheckRoutePermission` no lo encuentra y "
                . "el gate queda sin efecto o niega a todos.\n\n"
                . "**Qué hacer:** o se siembra el permiso por migración ADITIVA (`givePermissionTo`, NUNCA "
                . "`syncPermissions`) y se asigna a `super-administrator` + `DESARROLLADOR`, o se corrige el "
                . "nombre en el `module.json` si en BD se llama distinto. Cerrar con "
                . "`php artisan permissions:sync-roles`.",
        ]];
    }

    // ── Soporte de los detectores 2B ───────────────────────────────────────────────────────────

    /**
     * FASE 2B — LA MÉTRICA DE CONVERGENCIA: **superficie declarada**.
     *
     * Cuánto del sistema tiene contra qué medirse. Mientras suba, el generador tiene trabajo; cuando
     * se acerque a su techo, el detector semántico sobre `screens[].steps/actions` ya tendrá contra
     * qué medir y ahí sí valdrá la pena el juicio del modelo.
     *
     * **El denominador son sólo las rutas ATRIBUIBLES A UN MÓDULO.** Las de controllers legacy fuera
     * de `app/Modules` no pertenecen a ningún manifiesto y jamás podrán declararse por esta vía:
     * meterlas en el denominador haría que la métrica no pudiera llegar nunca a 100 %, y una métrica
     * con techo inalcanzable se deja de mirar. Se reportan aparte, que es el techo honesto.
     *
     * @return array{declarados:int,rutas_modulo:int,rutas_sin_modulo:int,pct:float,modulos:int,modulos_con_spec:int}
     */
    public function superficieDeclarada(): array
    {
        $porModulo = [];
        $sinModulo = 0;

        foreach (RouteFacade::getRoutes() as $r) {
            $nombre = $this->moduloDeLaAccion($r->getActionName());
            $verbos = count(array_diff($r->methods(), ['HEAD']));
            if ($nombre === null) {
                $sinModulo += $verbos;
                continue;
            }
            $porModulo[$this->normalizar($nombre)] = ($porModulo[$this->normalizar($nombre)] ?? 0) + $verbos;
        }

        $declarados = $conSpec = $modulos = 0;
        foreach (glob(base_path('app/Modules/*/*/module.json')) ?: [] as $f) {
            $modulos++;
            $d = json_decode((string) file_get_contents($f), true);
            $n = is_array($d) ? count($d['api_endpoints'] ?? []) : 0;
            $declarados += $n;
            if ($n > 0) {
                $conSpec++;
            }
        }

        $rutasModulo = array_sum($porModulo);

        return [
            'declarados'       => $declarados,
            'rutas_modulo'     => $rutasModulo,
            'rutas_sin_modulo' => $sinModulo,
            'pct'              => $rutasModulo ? round(100 * $declarados / $rutasModulo, 1) : 0.0,
            'modulos'          => $modulos,
            'modulos_con_spec' => $conSpec,
        ];
    }

    /** El `module.json` del módulo, o null si no hay o no parsea. */
    private function leerManifiesto(string $modulo): ?array
    {
        $dir = $this->rutaModulo($modulo);
        if ($dir === null || ! is_file($dir . '/module.json')) {
            return null;
        }
        $d = json_decode((string) file_get_contents($dir . '/module.json'), true);

        return is_array($d) ? $d : null;
    }

    /**
     * Rutas atribuidas al módulo por el NAMESPACE de su controlador. Es la atribución exacta y
     * mecánica: `App\Modules\{Core|Addons}\{Modulo}\…`. Las rutas de controllers legacy fuera de
     * `app/Modules` (128 al medir) no pertenecen a ningún módulo y quedan fuera del denominador —
     * el techo honesto de lo que este mecanismo puede cubrir.
     *
     * @return string[] claves `MÉTODO /uri`
     */
    public function rutasDelModulo(string $modulo): array
    {
        $real = $this->nombreDirectorio($modulo);
        $out  = [];
        foreach (RouteFacade::getRoutes() as $r) {
            $nombre = $this->moduloDeLaAccion($r->getActionName());
            if ($nombre === null || $this->normalizar($nombre) !== $this->normalizar($real)) {
                continue;
            }
            foreach (array_diff($r->methods(), ['HEAD']) as $verbo) {
                $out[$this->claveRuta($verbo, $r->uri())] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Nombre del módulo dueño de una acción, o null si la ruta no vive en `app/Modules`.
     *
     * Sin regex a propósito: el patrón equivalente pide cuatro niveles de escape de `\` entre PHP y
     * PCRE y ya se rompió una vez al escribirlo (`[^\\]` se comía el cierre de la clase). Partir por
     * el separador de namespace dice lo mismo y no se puede escribir mal.
     */
    private function moduloDeLaAccion(mixed $accion): ?string
    {
        if (! is_string($accion)) {
            return null;
        }
        $p = explode('\\', $accion);

        // App \ Modules \ {Core|Addons} \ {Modulo} \ …
        if (count($p) < 4 || $p[0] !== 'App' || $p[1] !== 'Modules' || ! in_array($p[2], ['Core', 'Addons'], true)) {
            return null;
        }

        return $p[3];
    }

    /** Índice global `MÉTODO /uri` (con `{param}` colapsado) de TODAS las rutas registradas. */
    private function indiceMetodoUri(): array
    {
        static $idx = null;
        if ($idx !== null) {
            return $idx;
        }
        $idx = [];
        foreach (RouteFacade::getRoutes() as $r) {
            foreach (array_diff($r->methods(), ['HEAD']) as $verbo) {
                $idx[$this->claveRuta($verbo, $r->uri())] = true;
            }
        }

        return $idx;
    }

    /** Normaliza `MÉTODO /uri` colapsando `{param}` para que `/x/{id}` y `/x/{cliente}` sean lo mismo. */
    private function claveRuta(string $metodo, string $path): string
    {
        $p = '/' . ltrim($path, '/');

        return strtoupper($metodo) . ' ' . rtrim(preg_replace('/\{[^}]+\}/', '{}', $p), '/');
    }

    /** Todos los gaps de un módulo, ya clasificados en mecanico|producto. */
    public function detectarGaps(string $modulo): array
    {
        $det  = (array) config('circuito.auditor.detectores', []);
        $dir  = $this->rutaModulo($modulo);
        $gaps = [];

        if ($dir !== null) {
            if (($det['hueco_ruteado'] ?? true)) {
                $gaps = array_merge($gaps, $this->detHuecosRuteados($modulo, $dir));
            }
            if (($det['enlace_roto'] ?? true)) {
                $gaps = array_merge($gaps, $this->detEnlacesRotos($modulo, $dir));
            }
            if (($det['todo'] ?? true)) {
                $gaps = array_merge($gaps, $this->detTodos($modulo, $dir));
            }
            if (($det['andamiaje'] ?? true)) {
                $gaps = array_merge($gaps, $this->detAndamiaje($modulo, $dir));
            }
            if (($det['null_safety'] ?? true)) {
                $gaps = array_merge($gaps, $this->detNullSafety($modulo, $dir));
            }
        }

        if (($det['sin_clasificar'] ?? true)) {
            $gaps = array_merge($gaps, $this->detSinClasificar($modulo));
        }
        if (($det['semilla'] ?? true)) {
            $gaps = array_merge($gaps, $this->detSemilla($modulo));
        }
        if (($det['jquery_sin_off'] ?? true)) {
            $gaps = array_merge($gaps, $this->detJquerySinOff($modulo));
        }
        if (($det['env_runtime'] ?? true)) {
            $gaps = array_merge($gaps, $this->detEnvRuntime($modulo));
        }

        $gaps = array_merge($gaps, $this->medirContraSpec($modulo));

        // FRONTERA DURA — última palabra. Un gap que toque producción / borrar datos / dinero /
        // credenciales JAMÁS sale como item mecánico, diga lo que diga el detector que lo encontró.
        foreach ($gaps as &$g) {
            $frontera = $this->fronteraDura($g['titulo'] . ' ' . $g['detalle']);
            if ($frontera !== null && $g['clase'] === 'mecanico') {
                $g['clase']    = 'producto';
                $g['frontera'] = $frontera;
                $g['pregunta'] = $g['pregunta'] ?? ("Este cambio toca la frontera de «{$frontera}», así que no lo ejecuto solo. "
                    . "¿Cómo procedemos con: {$g['titulo']}?");
            }
        }
        unset($g);

        return $gaps;
    }

    // ── Detector 1: huecos ruteados (ruta activa → cuerpo vacío) ───────────────────────────────

    private function detHuecosRuteados(string $modulo, string $dir): array
    {
        $idx  = $this->rutasRegistradas();
        $gaps = [];

        foreach ($this->archivosPhp($dir . '/Controllers') as $file) {
            $code  = @file_get_contents($file);
            $fqcn  = $this->fqcnDe($code);
            $clase = basename($file, '.php');

            foreach ($this->metodosPublicos($code) as $m) {
                if ($m['vacio'] !== true) {
                    continue;
                }
                $accion = $fqcn ? "{$fqcn}@{$m['name']}" : null;
                if ($accion === null || ! isset($idx['acciones'][$accion])) {
                    continue;
                }

                $rutas = implode(', ', array_slice($idx['acciones'][$accion], 0, 4));
                $gaps[] = [
                    'modulo'  => $modulo,
                    'tipo'    => 'hueco_ruteado',
                    'clase'   => 'mecanico',
                    'clave'   => "{$clase}@{$m['name']}",
                    'titulo'  => "{$modulo}: {$clase}@{$m['name']} tiene ruta activa y cuerpo vacío",
                    'detalle' => "El método `{$m['name']}()` de `{$clase}` está vacío pero hay ruta(s) apuntándole: {$rutas}. "
                        . "El usuario dispara la acción y no pasa nada (ni error ni efecto). "
                        . "Archivo: " . $this->relativo($file) . " (línea ~{$m['linea']}).\n\n"
                        . "Cerrar el hueco = implementar el comportamiento que la ruta promete, O quitar la ruta "
                        . "si la acción ya no aplica. Ambas salidas son válidas; elige la que deje el sistema coherente.",
                ];
            }
        }

        return $gaps;
    }

    // ── Detector 2: enlaces de menú que no resuelven a ninguna ruta ────────────────────────────

    private function detEnlacesRotos(string $modulo, string $dir): array
    {
        $manifest = $dir . '/module.json';
        if (! is_file($manifest)) {
            return [];
        }
        $j = json_decode((string) @file_get_contents($manifest), true);
        if (! is_array($j)) {
            return [];
        }

        $urls = [];
        foreach (['menu', 'admin_cards', 'config_sections', 'sidebar'] as $seccion) {
            foreach ((array) ($j[$seccion] ?? []) as $entrada) {
                $this->cosecharUrls($entrada, $seccion, $urls);
            }
        }

        $gaps = [];
        foreach ($urls as $url => $origen) {
            if ($this->rutaResuelve($url)) {
                continue;
            }
            $gaps[] = [
                'modulo'  => $modulo,
                'tipo'    => 'enlace_roto',
                'clase'   => 'mecanico',
                'clave'   => 'enlace:' . $url,
                'titulo'  => "{$modulo}: el enlace {$url} del menú no resuelve a ninguna ruta (404)",
                'detalle' => "`{$modulo}/module.json` ofrece `{$url}` en la sección `{$origen}`, pero ninguna ruta GET "
                    . "registrada la atiende → el usuario hace clic desde el menú y recibe un 404.\n\n"
                    . "Dos salidas válidas: registrar la ruta + pantalla que el menú promete, o retirar la entrada del "
                    . "manifiesto si esa pantalla no va a existir. No dejes el enlace muerto.",
            ];
        }

        return $gaps;
    }

    /** Extrae recursivamente urls internas de una entrada de manifiesto (y sus hijos). */
    private function cosecharUrls($entrada, string $origen, array &$out): void
    {
        if (! is_array($entrada)) {
            return;
        }
        foreach (['url', 'route', 'path'] as $k) {
            $u = $entrada[$k] ?? null;
            if (is_string($u) && str_starts_with($u, '/')) {
                $limpia = strtok($u, '?#');
                if ($limpia !== false && $limpia !== '/' && ! isset($out[$limpia])) {
                    $out[$limpia] = $origen;
                }
            }
        }
        foreach (['children', 'items', 'submenu'] as $k) {
            foreach ((array) ($entrada[$k] ?? []) as $hijo) {
                $this->cosecharUrls($hijo, $origen, $out);
            }
        }
    }

    /** ¿Alguna ruta GET registrada atiende esta URL? Usa el router real (respeta {param}). */
    private function rutaResuelve(string $url): bool
    {
        try {
            RouteFacade::getRoutes()->match(Request::create($url, 'GET'));

            return true;
        } catch (\Throwable $e) {
            // MethodNotAllowed = la URL existe (con otro verbo) → no es enlace roto.
            return $e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
        }
    }

    // ── Detector 3: TODO / FIXME reales (no la palabra española "todo") ────────────────────────

    private function detTodos(string $modulo, string $dir): array
    {
        $porArchivo = [];

        foreach ($this->archivosPhp($dir) as $file) {
            $lineas = @file($file);
            if (! $lineas) {
                continue;
            }
            foreach ($lineas as $i => $linea) {
                // Sólo marcadores en COMENTARIO. Evita "TODO el historial" (español) y strings.
                if (! preg_match('#(?://|/\*+|\#)\s*(TODO|FIXME|HACK)\b[:\s]*(.*)$#u', $linea, $m)) {
                    continue;
                }
                $texto = trim(rtrim(trim($m[2]), '*/'));
                if ($texto === '') {
                    $texto = '(sin detalle en el comentario)';
                }
                $porArchivo[$file][] = ['linea' => $i + 1, 'marca' => $m[1], 'texto' => $texto];
            }
        }

        $gaps = [];
        foreach ($porArchivo as $file => $marcas) {
            $rel   = $this->relativo($file);
            $lista = '';
            $todoElTexto = '';
            foreach ($marcas as $mk) {
                $lista .= "  - L{$mk['linea']} [{$mk['marca']}] {$mk['texto']}\n";
                $todoElTexto .= ' ' . $mk['texto'];
            }

            $esProducto = $this->pareceDecisionDeProducto($todoElTexto);
            $n = count($marcas);

            $gaps[] = [
                'modulo'  => $modulo,
                'tipo'    => 'todo',
                'clase'   => $esProducto ? 'producto' : 'mecanico',
                'clave'   => 'todo:' . $rel,
                'titulo'  => "{$modulo}: cerrar {$n} marcador(es) TODO/FIXME en " . basename($file),
                'detalle' => "Archivo: `{$rel}`\n\nMarcadores pendientes:\n{$lista}\n"
                    . "Cada marcador es deuda que alguien dejó anotada. Ciérralos: implementa lo que falta o borra el "
                    . "marcador si ya no aplica (un TODO obsoleto es peor que ninguno, porque miente).",
                'pregunta' => $esProducto
                    ? "En `{$rel}` hay {$n} marcador(es) que piden una decisión, no sólo código:\n{$lista}\n¿Cómo procedemos?"
                    : null,
            ];
        }

        return $gaps;
    }

    // ── Detector 4: andamiaje muerto (métodos vacíos SIN ruta) ─────────────────────────────────

    private function detAndamiaje(string $modulo, string $dir): array
    {
        $idx      = $this->rutasRegistradas();
        $porClase = [];
        $total    = 0;

        foreach ($this->archivosPhp($dir . '/Controllers') as $file) {
            $code  = @file_get_contents($file);
            $fqcn  = $this->fqcnDe($code);
            $clase = basename($file, '.php');

            foreach ($this->metodosPublicos($code) as $m) {
                if ($m['vacio'] !== true || str_starts_with($m['name'], '__')) {
                    continue;
                }
                if ($fqcn && isset($idx['acciones']["{$fqcn}@{$m['name']}"])) {
                    continue; // tiene ruta → es hueco ruteado, no andamiaje (lo ve el detector 1)
                }
                $porClase[$clase][] = $m['name'];
                $total++;
            }
        }

        if ($total === 0) {
            return [];
        }

        // UN item por MÓDULO, no uno por método: Mapas solo tenía 93 métodos así. Un item por
        // método inundaría la Hoja de Ruta con basura del mismo tamaño que la basura que limpia.
        $lista = '';
        foreach ($porClase as $clase => $metodos) {
            sort($metodos);
            $lista .= "  - {$clase}: " . implode(', ', $metodos) . "\n";
        }

        return [[
            'modulo'  => $modulo,
            'tipo'    => 'andamiaje',
            'clase'   => 'mecanico',
            'clave'   => 'andamiaje:modulo',
            'titulo'  => "{$modulo}: eliminar {$total} método(s) de andamiaje resource sin ruta",
            'detalle' => "Métodos públicos con cuerpo vacío y NINGUNA ruta que los use — basura de "
                . "`make:controller --resource` en una app que es API + Vue.\n\n{$lista}\n"
                . "Limpieza acotada y reversible: borra esos métodos. NO toques los que sí tienen ruta "
                . "(esos son huecos funcionales y van en su propio item). Verifica con `php -l` y que la app "
                . "siga booteando (`php artisan --version`).",
        ]];
    }

    // ── Detector 9: null-safety — auth()->user()-> y json_decode() sin guard (#900/#973) ───────

    /**
     * Dos patrones sin guard contra null, tokenizador `token_get_all` (no regex — frágil con
     * saltos de línea/comentarios/strings), misma técnica que `EnvRuntimeScanner::llamadasEnv()`
     * (#790): tokeniza, filtra whitespace/comentarios a un array de índices significativos, camina
     * la secuencia de tokens.
     *
     *  1. `auth()->user()->` SIN `?->` inmediatamente después — el caso real que ya mordió el
     *     repo (ver CLAUDE.md "DefaultValueRepository.php:35"). Acotado ESTRICTAMENTE a esa
     *     secuencia exacta (NO se generaliza a "cualquier método que pueda devolver null").
     *  2. `$var = json_decode(...)` cuyo resultado se usa (`$var->`/`$var[`) sin comprobar null
     *     antes (caso real: `Module.php` líneas 186/190, ya corregidas). Heurística APROXIMADA:
     *     ventana de las ~15 líneas siguientes del mismo archivo, no análisis de flujo real —
     *     la limitación se documenta en el `detalle` de cada gap.
     */
    private function detNullSafety(string $modulo, string $dir): array
    {
        $porArchivo = [];

        foreach ($this->archivosPhp($dir) as $file) {
            $src = @file_get_contents($file);
            $lineasSrc = $src !== false ? @file($file) : false;
            if (! $src || ! $lineasSrc) {
                continue;
            }

            $tokens = @token_get_all($src);
            $sig    = [];
            foreach ($tokens as $i => $t) {
                if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $sig[] = $i;
            }

            $hallazgos = [];
            foreach ($this->authUserSinGuard($tokens, $sig) as $linea) {
                $hallazgos[] = ['linea' => $linea, 'patron' => 'auth()->user()-> sin ?->', 'contexto' => trim($lineasSrc[$linea - 1] ?? '')];
            }
            foreach ($this->jsonDecodeSinGuard($tokens, $sig, $lineasSrc) as $linea) {
                $hallazgos[] = ['linea' => $linea, 'patron' => 'json_decode() sin guard', 'contexto' => trim($lineasSrc[$linea - 1] ?? '')];
            }
            if (! $hallazgos) {
                continue;
            }
            usort($hallazgos, fn ($a, $b) => $a['linea'] <=> $b['linea']);
            $porArchivo[$file] = $hallazgos;
        }

        $gaps = [];
        foreach ($porArchivo as $file => $hallazgos) {
            $rel   = $this->relativo($file);
            $lista = '';
            foreach ($hallazgos as $h) {
                $lista .= "  - L{$h['linea']} [{$h['patron']}] {$h['contexto']}\n";
            }
            $n = count($hallazgos);

            $gaps[] = [
                'modulo'  => $modulo,
                'tipo'    => 'null_safety',
                'clase'   => 'mecanico',
                'clave'   => 'null-safety:' . $rel,
                'titulo'  => "{$modulo}: {$n} patrón(es) null-safety sin guard en " . basename($file),
                'detalle' => "Dos patrones sin guard contra null: `auth()->user()->` sin `?->` inmediatamente "
                    . "después (el caso real que ya mordió el repo, ver CLAUDE.md \"DefaultValueRepository.php:35\"), "
                    . "y `\$var = json_decode(...)` cuyo resultado se usa (`\$var->`/`\$var[`) sin comprobar null "
                    . "antes (caso real: `Module.php` líneas 186/190, ya corregidas). El patrón de json_decode es "
                    . "una heurística APROXIMADA (ventana de las ~15 líneas siguientes, no análisis de flujo real): "
                    . "puede haber falsos positivos/negativos — revisar manualmente cada hallazgo antes de corregir.\n\n"
                    . "Archivo: `{$rel}`\n\nHallazgos:\n{$lista}\n"
                    . "Corrección aditiva, SIN tocar lógica de negocio: agrega `?->` en el caso 1, o un guard "
                    . "`if (\$var !== null)` (o `?->`/`??`/`isset()`) antes del uso en el caso 2.",
            ];
        }

        return $gaps;
    }

    /**
     * `auth()->user()->` SIN `?->` inmediatamente después. Descarta `->auth(`/`::auth(` (no es
     * el helper global) igual que `EnvRuntimeScanner` descarta `->env(`/`::env(`.
     *
     * @return int[] líneas del token `auth` donde se encontró el patrón
     */
    private function authUserSinGuard(array $tokens, array $sig): array
    {
        $lineas = [];
        foreach ($sig as $k => $i) {
            $t = $tokens[$i];
            if (! is_array($t) || $t[0] !== T_STRING || strtolower($t[1]) !== 'auth') {
                continue;
            }
            $prev = $k > 0 ? $tokens[$sig[$k - 1]] : null;
            if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR,
                T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                continue;
            }
            if (($tokens[$sig[$k + 1] ?? $i] ?? null) !== '(') {
                continue;
            }
            if (($tokens[$sig[$k + 2] ?? $i] ?? null) !== ')') {
                continue;
            }
            $opUser = $tokens[$sig[$k + 3] ?? $i] ?? null;
            if (! is_array($opUser) || $opUser[0] !== T_OBJECT_OPERATOR) {
                continue;
            }
            $userTok = $tokens[$sig[$k + 4] ?? $i] ?? null;
            if (! is_array($userTok) || $userTok[0] !== T_STRING || strtolower($userTok[1]) !== 'user') {
                continue;
            }
            if (($tokens[$sig[$k + 5] ?? $i] ?? null) !== '(') {
                continue;
            }
            if (($tokens[$sig[$k + 6] ?? $i] ?? null) !== ')') {
                continue;
            }
            $opFinal = $tokens[$sig[$k + 7] ?? $i] ?? null;
            if (is_array($opFinal) && $opFinal[0] === T_OBJECT_OPERATOR) {
                $lineas[] = (int) $t[2];
            }
        }

        return $lineas;
    }

    /**
     * `$var = json_decode(...)` cuyo resultado se usa sin guard en la ventana de las siguientes
     * ~15 líneas. Solo rastrea asignación DIRECTA (`$var = json_decode(`) — si el resultado se
     * pasa inline a otra expresión no se puede identificar la variable de forma mecánica, así
     * que ese caso se omite (lado seguro: no generar ruido que no se puede verificar).
     *
     * @return int[] líneas del `json_decode(` donde se encontró el patrón
     */
    private function jsonDecodeSinGuard(array $tokens, array $sig, array $lineasSrc): array
    {
        $lineas = [];
        foreach ($sig as $k => $i) {
            $t = $tokens[$i];
            if (! is_array($t) || $t[0] !== T_STRING || strtolower($t[1]) !== 'json_decode') {
                continue;
            }
            $prev = $k > 0 ? $tokens[$sig[$k - 1]] : null;
            if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR,
                T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                continue;
            }
            if (($tokens[$sig[$k + 1] ?? $i] ?? null) !== '(') {
                continue;
            }
            // ¿Asignación directa `$var = json_decode(`?
            $prevPrev = $k > 1 ? ($tokens[$sig[$k - 2]] ?? null) : null;
            if ($prev !== '=' || ! is_array($prevPrev) || $prevPrev[0] !== T_VARIABLE) {
                continue;
            }

            $var         = $prevPrev[1];
            $lineaDecode = (int) $t[2];
            if ($this->usoSinGuardEnVentana($var, $lineaDecode, $lineasSrc)) {
                $lineas[] = $lineaDecode;
            }
        }

        return $lineas;
    }

    /** ¿`$var` se usa (`->`/`[`) en la ventana de 15 líneas siguientes SIN guard previo en esa misma ventana? */
    private function usoSinGuardEnVentana(string $var, int $lineaDecode, array $lineasSrc): bool
    {
        $fin    = min(count($lineasSrc), $lineaDecode + 15);
        $varEsc = preg_quote($var, '/');

        for ($ln = $lineaDecode + 1; $ln <= $fin; $ln++) {
            $texto = $lineasSrc[$ln - 1] ?? '';

            if (preg_match("/{$varEsc}\s*(!==|===)\s*null/", $texto)
                || preg_match("/{$varEsc}\s*\?->/", $texto)
                || str_contains($texto, '??')
                || preg_match("/is_null\(\s*{$varEsc}\s*\)/", $texto)
                || preg_match("/isset\(\s*{$varEsc}\b/", $texto)) {
                return false; // guard antes del uso, dentro de la ventana → no es hallazgo
            }
            if (preg_match("/{$varEsc}\s*->/", $texto) || preg_match("/{$varEsc}\s*\[/", $texto)) {
                return true;
            }
        }

        return false;
    }

    // ── Detector 5: items de la Hoja de Ruta sin footprint ─────────────────────────────────────

    private function detSinClasificar(string $modulo): array
    {
        // Es un gap del circuito, no de un módulo de negocio: sólo se emite una vez, al auditar
        // el módulo del propio circuito.
        if ($modulo !== 'Roadmap / Circuito CC') {
            return [];
        }

        $sinFootprint = RoadmapItem::query()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
            ->where(fn ($q) => $q->whereNull('modulo')->orWhere('modulo', '')->orWhere('modulo', self::MODULO_DESCONOCIDO))
            ->orderBy('id')
            ->get(['id', 'title']);

        if ($sinFootprint->isEmpty()) {
            return [];
        }

        $lista = '';
        foreach ($sinFootprint->take(40) as $it) {
            $lista .= '  - #' . $it->id . ' ' . mb_substr((string) $it->title, 0, 90) . "\n";
        }
        $n = $sinFootprint->count();

        return [[
            'modulo'  => $modulo,
            'tipo'    => 'sin_clasificar',
            'clase'   => 'mecanico',
            'clave'   => 'sin-footprint:lote',
            'titulo'  => "Circuito: clasificar el footprint de {$n} item(s) sin módulo",
            'detalle' => "`modulo` NO es una etiqueta cosmética: es el FOOTPRINT con el que el scheduler serializa. "
                . "Un item sin módulo podría tocar cualquier archivo, así que por diseño (#432 B2) **corre SOLO y "
                . "bloquea a las 6 terminales** mientras dure. Con {$n} así, la flota se para de a uno.\n\n"
                . "Items sin footprint:\n{$lista}\n"
                . "Trabajo: corre `php artisan circuito:clasificar-modulo` (mapa determinista de "
                . "`config/circuito.clasificador`) y, para los que el mapa no alcance, asigna el módulo a mano "
                . "leyendo el item. Si de plano no se puede saber qué toca, DÉJALO sin clasificar: adivinar mal es "
                . "peor que no adivinar (dos items con footprint equivocado corren en paralelo y se pisan).",
        ]];
    }

    // ── Detector 6: semilla del inventario 2026-08-08 ──────────────────────────────────────────

    private function detSemilla(string $modulo): array
    {
        $gaps = [];
        foreach ((InventarioSemilla::porModulo()[$modulo] ?? []) as $s) {
            // AUTO-VERIFICACIÓN: una entrada de semilla describe el mundo del día que se escribió,
            // y el circuito trabaja mientras tanto. En la primera corrida, dos entradas ya estaban
            // obsoletas en cuestión de horas (Talento ya se había registrado en module_registry).
            // Un gap que ya no existe NO se crea: el motor no inventa trabajo.
            if (isset($s['vigente']) && is_callable($s['vigente'])) {
                try {
                    if (! $s['vigente']()) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    // Si la comprobación truena no podemos afirmar que el gap siga vivo → no lo
                    // creamos. Fallar hacia "no generar trabajo" es el lado seguro.
                    continue;
                }
            }

            $gaps[] = [
                'modulo'   => $modulo,
                'tipo'     => 'semilla',
                'clase'    => ($s['tipo'] ?? 'mecanico') === 'producto' ? 'producto' : 'mecanico',
                'clave'    => 'semilla:' . $s['clave'],
                'titulo'   => $s['titulo'],
                'detalle'  => $s['detalle'] . "\n\n_(Detectado en el inventario de módulos del " . InventarioSemilla::FECHA . ".)_",
                'pregunta' => $s['pregunta'] ?? null,
            ];
        }

        return $gaps;
    }

    // ── Detector 7: jQuery $(document).on() delegado sin su .off() correspondiente (#899) ─────

    /**
     * `$(document).on(...)` delega el handler en `document`. La navegación SPA sólo intercambia
     * `#init-vue` (no recarga el bundle JS), así que un componente sin `.off()` en su cleanup deja
     * vivo el handler viejo cada vez que se vuelve a montar — se acumulan sin límite.
     *
     * Cross-cutting: los componentes Vue no viven bajo el `$dir` PHP de ningún módulo de negocio
     * (viven en resources/js/components/...), así que este detector escanea ESE árbol una sola vez
     * por ciclo y emite el hallazgo bajo el ancla del circuito — mismo patrón que detSinClasificar().
     */
    private function detJquerySinOff(string $modulo): array
    {
        if ($modulo !== 'Roadmap / Circuito CC') {
            return [];
        }

        $dir = base_path('resources/js/components');
        if (! is_dir($dir)) {
            return [];
        }

        $afectados = [];
        foreach ($this->archivosVue($dir) as $file) {
            $lineas = @file($file);
            if (! $lineas) {
                continue;
            }
            $onLineas = [];
            $tieneOff = false;
            foreach ($lineas as $i => $linea) {
                if (str_contains($linea, '$(document).off(')) {
                    $tieneOff = true;
                }
                if (str_contains($linea, '$(document).on(')) {
                    $onLineas[] = $i + 1;
                }
            }
            if ($onLineas && ! $tieneOff) {
                $afectados[$this->relativo($file)] = $onLineas;
            }
        }

        if (! $afectados) {
            return [];
        }

        ksort($afectados);
        $n     = count($afectados);
        $lista = '';
        foreach ($afectados as $rel => $onLineas) {
            $lista .= "  - {$rel}: L" . implode(', L', $onLineas) . "\n";
        }

        return [[
            'modulo'  => $modulo,
            'tipo'    => 'jquery_sin_off',
            'clase'   => 'mecanico',
            'clave'   => 'jquery-sin-off:lote',
            'titulo'  => "Circuito: {$n} componente(s) Vue con \$(document).on() global sin su .off() (memory leak SPA)",
            'detalle' => "`\$(document).on(...)` delega el handler en `document` — la navegación SPA sólo "
                . "reemplaza `#init-vue` (no recarga el bundle), así que cada vez que el componente se "
                . "vuelve a montar se acumula OTRO handler vivo encima del anterior, nunca limpiado.\n\n"
                . "Patrón ya corregido de referencia (namespace de evento + `.off()` en cleanup): "
                . "`resources/js/shared/TextTemplate.vue` y `resources/js/shared/ContractTemplate.vue`.\n\n"
                . "Componentes afectados ({$n}):\n{$lista}\n"
                . "Cerrar = namespacear el evento ('click.miComponente', no 'click' a secas) y llamar "
                . "\$(document).off(ns) en onUnmounted/beforeUnmount. Verifica montando/desmontando el "
                . "componente y confirmando que la funcionalidad original sigue viva.",
        ]];
    }

    // ── Detector 8: env() en tiempo de ejecución fuera de config/ (#901) ──────────────────────

    /**
     * Cross-cutting (app/, routes/, bootstrap/, no un $dir de módulo PHP): se emite UNA vez bajo
     * el ancla 'Roadmap / Circuito CC', igual que detSinClasificar()/detJquerySinOff(). Consume el
     * mismo escaneo que `config:auditar-env` (#790) vía EnvRuntimeScanner — NO reimplementa el
     * tokenizador.
     */
    private function detEnvRuntime(string $modulo): array
    {
        if ($modulo !== 'Roadmap / Circuito CC') {
            return [];
        }

        $hallazgos = (new EnvRuntimeScanner())->escanear();
        if (! $hallazgos) {
            return [];
        }

        $porArchivo = [];
        foreach ($hallazgos as $h) {
            $porArchivo[$h['file']][] = $h;
        }
        ksort($porArchivo);

        $n     = count($hallazgos);
        $lista = '';
        foreach ($porArchivo as $file => $hits) {
            $claves = array_map(
                fn ($h) => ':' . $h['linea'] . ' ' . ($h['clave'] ?? '(clave dinámica)'),
                $hits
            );
            $lista .= "  - {$file}: " . implode(', ', $claves) . "\n";
        }

        return [[
            'modulo'  => $modulo,
            'tipo'    => 'env_runtime',
            'clase'   => 'mecanico',
            'clave'   => 'env-runtime:lote',
            'titulo'  => "Circuito: {$n} llamada(s) a env() en tiempo de ejecución fuera de config/",
            'detalle' => "Una llamada a `env()` fuera de `config/*.php` sólo funciona mientras nadie corra "
                . "`config:cache` — hoy el checklist de cierre lo evita con `php artisan config:auditar-env` "
                . "(#790), pero la lista de llamadas sigue sin vaciarse. Cada una se mueve a una clave de "
                . "`config/<archivo>.php` (donde `env()` sí es el patrón correcto) y el llamador pasa a usar "
                . "`config('...')` en su lugar — mismo cambio mecánico ya aplicado en #792/#793.\n\n"
                . "Verificación de cierre: `php artisan config:auditar-env` debe bajar de {$n} hallazgo(s) "
                . "tras el cambio; en 0 el checklist queda desbloqueado.\n\n"
                . "Llamadas ({$n}):\n{$lista}",
        ]];
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // CLASIFICACIÓN — frontera dura y olor a decisión de producto
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ¿El texto cae en una de las cuatro fronteras duras de Jarvis? Devuelve el nombre de la
     * frontera o null. Reusa `circuito.jarvis.escalamiento` a propósito: la política de qué
     * despierta a Irving debe vivir en UN solo lugar, no duplicada por cada generador de trabajo.
     */
    public function fronteraDura(string $texto): ?string
    {
        $heno = $this->normalizar($texto);
        foreach ((array) config('circuito.jarvis.escalamiento', []) as $frontera => $terminos) {
            foreach ((array) $terminos as $t) {
                if ($this->contieneTermino($heno, $this->normalizar($t))) {
                    return (string) $frontera;
                }
            }
        }

        return null;
    }

    /** ¿El texto de un TODO pide una decisión (y no sólo código)? */
    private function pareceDecisionDeProducto(string $texto): bool
    {
        $heno = $this->normalizar($texto);
        foreach ((array) config('circuito.auditor.terminos_producto', []) as $t) {
            if ($this->contieneTermino($heno, $this->normalizar($t))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match por PALABRA COMPLETA en términos de una sola palabra; substring en frases.
     *
     * La regla la enseñó a golpes el clasificador (#566): con `str_contains`, «Portal colaborador»
     * caía en el módulo del circuito porque "cola" vive dentro de "colaborador". Mismo accidente
     * que obligó a sacar 'login'/'token' del denylist del revisor (#338).
     */
    private function contieneTermino(string $heno, string $aguja): bool
    {
        $aguja = trim($aguja);
        if ($aguja === '') {
            return false;
        }
        if (str_contains($aguja, ' ')) {
            return str_contains($heno, $aguja);
        }

        return (bool) preg_match('/(?<![a-z0-9_])' . preg_quote($aguja, '/') . '(?![a-z0-9_])/u', $heno);
    }

    /** minúsculas + sin acentos + espacios colapsados. */
    private function normalizar(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return (string) preg_replace('/\s+/', ' ', $s);
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // DEDUP + CREACIÓN
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /** Huella ESTABLE del gap. Cambiarla recrea el item, así que se deriva sólo de tipo+módulo+clave. */
    public function huella(array $gap): string
    {
        return substr(sha1($gap['tipo'] . '|' . $this->normalizar($gap['modulo']) . '|' . $gap['clave']), 0, 40);
    }

    /**
     * ¿Ya existe un item para este gap? DOS capas, porque el motor no es el único que crea trabajo:
     *
     *  1. Huella exacta — cubre todo lo que creó el propio motor, ABIERTO O CERRADO. Si ya cerramos
     *     "hueco ruteado en BoxInputController@update", no se vuelve a crear nunca.
     *  2. Título parecido dentro del mismo módulo — cubre lo que crearon Irving, Cowork o una
     *     terminal. Sin esto el motor duplicaría trabajo humano: al 2026-08-08 el circuito YA
     *     estaba ejecutando #564 (Reportes), #565 (Talento en registry) y #567 (huecos de Mapas),
     *     que son exactamente gaps que este motor detecta.
     */
    public function yaExiste(array $gap): bool
    {
        if (RoadmapItem::where('auditor_fingerprint', $this->huella($gap))->exists()) {
            return true;
        }

        return $this->existeItemParecido($gap);
    }

    private function existeItemParecido(array $gap): bool
    {
        // Firma del gap = las palabras "con contenido" de su clave + título.
        $firma = $this->palabrasClave($gap['clave'] . ' ' . $gap['titulo']);
        if (count($firma) < 2) {
            return false;
        }

        // Candidatos: items del MISMO módulo **más** items cuyo título mencione alguna de las
        // palabras distintivas del gap, sin importar su módulo.
        //
        // Lo segundo no es exceso de celo: los items #564 ("Eliminar el módulo Reportes") y #565
        // ("Registrar addon-talento en module_registry") viven con `modulo = ModuleManager`, no
        // con el del módulo que arreglan. Buscando sólo por módulo, el motor los habría duplicado
        // aunque el circuito ya los estuviera ejecutando.
        $distintivas = $this->masDistintivas($firma, 3);

        $candidatos = RoadmapItem::query()
            ->where(function ($q) use ($gap, $distintivas) {
                $q->where('modulo', $gap['modulo'])
                    ->orWhere('modulo', 'like', '%' . $gap['modulo'] . '%');
                foreach ($distintivas as $w) {
                    $q->orWhere('title', 'like', '%' . $w . '%');
                }
            })
            ->whereNotIn('estado_aprobacion', ['rechazado', 'cancelado'])
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'title', 'description', 'modulo']);

        $modNorm = $this->normalizar($gap['modulo']);

        foreach ($candidatos as $c) {
            $suyas = $this->palabrasClave((string) $c->title);
            if (! $suyas) {
                continue;
            }

            // EL MÓDULO ES PARTE DE LA IDENTIDAD. Sin este guard, la rama de búsqueda por palabras
            // colapsa gaps estructuralmente idénticos de módulos DISTINTOS: "GestionRed: eliminar 1
            // método de andamiaje resource sin ruta" y "Mapas: eliminar 93 métodos de andamiaje
            // resource sin ruta" comparten 5 palabras, así que crear el primero mataba al segundo.
            // Pasó de verdad en la primera corrida en vivo: se crearon 6 items en vez de 10.
            $mismoModulo = $this->normalizar((string) $c->modulo) === $modNorm
                || str_contains($this->normalizar((string) $c->title), $modNorm)
                || str_contains($this->normalizar((string) $c->description), $modNorm);
            if (! $mismoModulo) {
                continue;
            }

            $comunes = count(array_intersect($firma, $suyas));

            // Umbral relativo al conjunto MÁS CHICO, no a la firma del gap. Medido contra la firma
            // se pierde el caso real: el gap "Reportes: el módulo está vacío y duplica /releases —
            // decidir si se llena o se elimina" (8 palabras) contra el item #564 que ya existía,
            // "Eliminar el módulo Reportes (cascarón vacío que duplica /releases)" (6 palabras),
            // comparte 4 palabras — suficiente para ser el MISMO trabajo, insuficiente para un
            // umbral calculado sobre 8. Duplicar trabajo humano es el peor modo de fallo del motor.
            // Piso en 2, no en 3: con títulos cortos (3-4 palabras significativas) un piso de 3
            // exige coincidencia total y deja pasar duplicados evidentes. Un falso positivo cuesta
            // un gap que no se crea (visible en la columna "ya existen" del reporte y el módulo
            // se queda fuera de su DoD); un falso negativo cuesta duplicar trabajo humano en vuelo.
            $base = min(count($firma), count($suyas));
            if ($comunes >= max(2, (int) ceil($base * 0.5))) {
                return true;
            }
        }

        return false;
    }

    /** Las N palabras más distintivas (heurística: las más largas) para acotar el SQL de candidatos. */
    private function masDistintivas(array $palabras, int $n): array
    {
        usort($palabras, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_slice(array_filter($palabras, fn ($w) => mb_strlen($w) >= 5), 0, $n);
    }

    /** Palabras significativas (≥4 letras, sin muletillas) para comparar objetivos. */
    private function palabrasClave(string $texto): array
    {
        $vacias = ['para', 'como', 'desde', 'hasta', 'entre', 'sobre', 'este', 'esta', 'esto', 'todos',
            'todas', 'cada', 'debe', 'tiene', 'hace', 'sino', 'pero', 'porque', 'cuando', 'donde',
            'ninguna', 'ningun', 'items', 'item', 'modulo', 'sistema', 'circuito'];
        $t = preg_replace('/[^a-z0-9\s]/', ' ', $this->normalizar($texto));
        $out = [];
        foreach (preg_split('/\s+/', (string) $t, -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (mb_strlen($w) >= 4 && ! in_array($w, $vacias, true)) {
                $out[$w] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Crea el item del gap. MECÁNICO → nivel A (reclamable por el pool). PRODUCTO → bandeja de
     * Irving con la pregunta, jamás ejecutable.
     */
    public function crear(array $gap, ?int $itemMadreId = null): RoadmapItem
    {
        $esProducto = $gap['clase'] === 'producto';

        $item = $this->intake->crear([
            'title'          => $gap['titulo'],
            'description'    => $gap['detalle'],
            'prompt'         => $esProducto ? null : $this->promptEjecutable($gap),
            'modulo'         => $gap['modulo'],
            'nivel_riesgo'   => $esProducto ? 'C' : 'A',
            'priority'       => 'media',
            'origen_item_id' => $itemMadreId,
        ], 'auditor', true);

        $item->auditor_fingerprint = $this->huella($gap);

        if ($esProducto) {
            // Bandeja de Irving. `requiere_irving: true` en la pregunta impide que el autopilot la
            // tome, y el estado `requiere_irving` lo deja fuera del pool de reclamo.
            $item->estado_aprobacion = 'requiere_irving';
            $item->preguntas = [[
                'id'              => 'q1',
                'pregunta'        => $gap['pregunta'] ?? ('¿Cómo procedemos con: ' . $gap['titulo'] . '?'),
                'fase'            => null,
                'requiere_irving' => true,
                'opciones'        => [],
                'opcion_elegida'  => null,
            ]];
        }

        $item->save();

        Log::channel('roadmap_externo')->info('auditor-item-creado', [
            'id' => $item->id, 'modulo' => $gap['modulo'], 'tipo' => $gap['tipo'],
            'clase' => $gap['clase'], 'huella' => $item->auditor_fingerprint,
            'frontera' => $gap['frontera'] ?? null,
        ]);

        return $item;
    }

    /** Spec ejecutable para la terminal que tome el item mecánico. */
    private function promptEjecutable(array $gap): string
    {
        return "Este item lo generó el MOTOR DE AUDITORÍA CONTINUA (#559) al escanear el módulo "
            . "{$gap['modulo']}. Es un gap MECÁNICO: aditivo/reversible, sin decisión de producto de por medio.\n\n"
            . "QUÉ CERRAR\n{$gap['detalle']}\n\n"
            . "CÓMO CERRARLO\n"
            . "- Cambio mínimo que resuelve el gap. Nada de refactors de paso.\n"
            . "- Si al abrirlo resulta que SÍ hay una decisión de producto detrás (qué debe hacer la "
            . "pantalla, qué política aplica), NO la inventes: consulta a Jarvis.\n"
            . "- Verifica: `php -l` de lo que toques + `php artisan --version` (que bootee). Si tocas "
            . "frontend, compila con `bash deploy/circuito/npm-build.sh`.\n"
            . "- DoD del item: el gap ya no aparece si se vuelve a correr `php artisan circuito:auditor --dry`.";
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // CICLO
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Un ciclo completo: escanea en orden de carril, dedup, respeta el cap, y crea (o simula).
     *
     * REPARTO ROUND-ROBIN entre módulos, no "vaciar el módulo 1 primero". Motivo: `modulo` es el
     * footprint con el que el scheduler serializa — 10 items del mismo módulo ocupan UNA terminal y
     * dejan 5 ociosas. Pocos items de muchos módulos llenan la flota, que es el punto del motor.
     */
    public function ciclo(bool $apply, ?int $cap = null, ?string $soloModulo = null): array
    {
        $cap        = $cap ?? $this->torreConfig->get()->auditor_max_por_corrida;
        $porModulo  = max(1, (int) config('circuito.auditor.items_por_modulo_por_ciclo', 2));
        $modulos    = $soloModulo ? [$soloModulo] : $this->modulosAAuditar();

        $porModuloGaps = [];
        $resumen       = [];

        foreach ($modulos as $m) {
            $gaps = $this->detectarGaps($m);
            $nuevos = [];
            foreach ($gaps as $g) {
                if ($this->yaExiste($g)) {
                    continue;
                }
                $nuevos[] = $g;
            }
            $resumen[$m] = [
                'detectados' => count($gaps),
                'ya_existen' => count($gaps) - count($nuevos),
                'nuevos'     => count($nuevos),
                'mecanicos'  => count(array_filter($nuevos, fn ($g) => $g['clase'] === 'mecanico')),
                'producto'   => count(array_filter($nuevos, fn ($g) => $g['clase'] === 'producto')),
                'en_dod'     => count(array_filter($gaps, fn ($g) => $g['clase'] === 'mecanico')) === 0,
            ];
            if ($nuevos) {
                $porModuloGaps[$m] = $nuevos;
            }
        }

        // Round-robin hasta el cap.
        $elegidos = [];
        $ronda    = 0;
        while (count($elegidos) < $cap && $porModuloGaps) {
            $avanzo = false;
            foreach (array_keys($porModuloGaps) as $m) {
                if (count($elegidos) >= $cap) {
                    break;
                }
                $tomados = 0;
                foreach ($porModuloGaps[$m] as $k => $g) {
                    if ($tomados >= $porModulo || count($elegidos) >= $cap) {
                        break;
                    }
                    $elegidos[] = $g;
                    unset($porModuloGaps[$m][$k]);
                    $tomados++;
                    $avanzo = true;
                }
                if (! $porModuloGaps[$m]) {
                    unset($porModuloGaps[$m]);
                }
            }
            if (! $avanzo) {
                break;
            }
            if (++$ronda > 200) {
                break; // backstop paranoico
            }
        }

        $creados = [];
        if ($apply) {
            foreach ($elegidos as $g) {
                // Segunda comprobación de dedup JUSTO antes de escribir: entre el escaneo y aquí
                // pudo entrar un item por otra vía (Irving, Cowork, una terminal).
                if ($this->yaExiste($g)) {
                    continue;
                }
                try {
                    $item = $this->crear($g);
                    $creados[] = ['id' => $item->id, 'modulo' => $g['modulo'], 'clase' => $g['clase'],
                        'tipo' => $g['tipo'], 'titulo' => $g['titulo']];
                } catch (\Throwable $e) {
                    Log::channel('roadmap_externo')->warning('auditor-alta-fallo', [
                        'modulo' => $g['modulo'], 'clave' => $g['clave'], 'error' => $e->getMessage(),
                    ]);
                }
            }
            DB::table('settings')->updateOrInsert(
                ['key' => self::SETTING_ULTIMA_CORRIDA],
                ['value' => (string) time(), 'updated_at' => now()]
            );

            // #1015 — memoria de cobertura: cada módulo que de verdad se escaneó esta corrida
            // queda con su timestamp actualizado (así deja de ser "el más viejo" la próxima vez).
            $cobertura = $this->cobertura();
            foreach ($resumen as $m => $s) {
                $cobertura[$m] = ['ultima_auditoria_at' => now()->toIso8601String(), 'nuevos' => $s['nuevos']];
            }
            DB::table('settings')->updateOrInsert(
                ['key' => self::SETTING_COBERTURA],
                ['value' => json_encode($cobertura, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
            );

            // #1015 — freno por sequía: SOLO sobre el ciclo COMPLETO (sin `--modulo`), porque un
            // escaneo parcial de un único módulo no dice nada sobre si la fuente de código en
            // general está agotada.
            if ($soloModulo === null) {
                $totalNuevos = array_sum(array_column($resumen, 'nuevos'));
                $racha       = $totalNuevos > 0 ? 0 : $this->rachaSeca() + 1;
                DB::table('settings')->updateOrInsert(
                    ['key' => self::SETTING_RACHA_SECA],
                    ['value' => (string) $racha, 'updated_at' => now()]
                );

                // #712 — Nivel 2: sólo APAGA, nunca reactiva por su cuenta (el re-arme es
                // EXCLUSIVO de `gastoApagado()`, disparado por un item real completado).
                $this->evaluarApagarGasto($racha);
            }
        }

        $reporte = [
            'ts'        => now()->toIso8601String(),
            'modo'      => $apply ? 'vivo' : 'dry-run',
            'cap'       => $cap,
            'candidatos' => count($elegidos),
            'creados'   => $creados,
            'por_modulo' => $resumen,
            'racha_seca' => $this->rachaSeca(),   // #1015 — visible sin tocar UI, ver comentario abajo
            'gasto_apagado' => $this->gastoApagadoDesde() !== null,   // #712 — ídem, Nivel 2
        ];

        // Reporte VISIBLE sin tocar UI: queda en `settings` (la Torre ya lee de ahí) y en el log
        // del circuito.
        DB::table('settings')->updateOrInsert(
            ['key' => self::SETTING_ULTIMO_REPORTE],
            ['value' => json_encode($reporte, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );
        Log::channel('roadmap_externo')->info('auditor-ciclo', [
            'modo' => $reporte['modo'], 'candidatos' => count($elegidos), 'creados' => count($creados),
        ]);

        return $reporte + ['elegidos' => $elegidos];
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // Utilidades de lectura de código
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /** Ruta en disco del módulo, o null si el nombre (ya resuelto por alias) no es un directorio real. */
    public function rutaModulo(string $modulo): ?string
    {
        $modulo = $this->nombreDirectorio($modulo);

        foreach (['Core', 'Addons'] as $tipo) {
            $p = base_path("app/Modules/{$tipo}/{$modulo}");
            if (is_dir($p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * #809 — el carril (footprint que se estampa en `modulo` de los items generados) no siempre
     * coincide con el nombre real del directorio del módulo en disco. Traduce vía
     * `circuito.auditor.alias_directorio`; sin alias declarado, el nombre no cambia.
     */
    private function nombreDirectorio(string $modulo): string
    {
        $alias = (array) config('circuito.auditor.alias_directorio', []);

        return $alias[$modulo] ?? $modulo;
    }

    private function relativo(string $abs): string
    {
        return str_replace(base_path() . '/', '', $abs);
    }

    /** @return string[] */
    private function archivosPhp(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    /** @return string[] */
    private function archivosVue(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'vue') {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    private function fqcnDe(?string $code): ?string
    {
        if (! $code) {
            return null;
        }
        if (! preg_match('/^\s*namespace\s+([^;]+);/m', $code, $ns)) {
            return null;
        }
        if (! preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', $code, $cl)) {
            return null;
        }

        return trim($ns[1]) . '\\' . $cl[1];
    }

    /**
     * Métodos PÚBLICOS con la marca de "cuerpo vacío". Balanceo de llaves (no regex del cuerpo):
     * un `return view(...)` con llaves anidadas no debe confundirse con un cuerpo vacío.
     *
     * "Vacío" = sin una sola sentencia una vez quitados comentarios. `{ // }` cuenta como vacío,
     * que es justo la forma que deja `make:controller --resource`.
     */
    private function metodosPublicos(?string $code): array
    {
        if (! $code) {
            return [];
        }
        $out    = [];
        $len    = strlen($code);
        $offset = 0;

        while (preg_match('/\b(public|protected|private)?\s*(?:static\s+)?function\s+(\w+)\s*\(/',
            $code, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $vis  = $m[1][0] !== '' ? $m[1][0] : 'public';
            $name = $m[2][0];
            $pos  = $m[0][1];
            $i    = $pos + strlen($m[0][0]);

            // cerrar los paréntesis de la firma
            $depthP = 1;
            while ($i < $len && $depthP > 0) {
                if ($code[$i] === '(') {
                    $depthP++;
                } elseif ($code[$i] === ')') {
                    $depthP--;
                }
                $i++;
            }
            // saltar el tipo de retorno hasta '{' o ';'
            while ($i < $len && $code[$i] !== '{' && $code[$i] !== ';') {
                $i++;
            }
            if ($i >= $len || $code[$i] === ';') {   // abstracta / interface
                $offset = $i + 1;
                continue;
            }

            $start = $i;
            $depth = 0;
            for (; $i < $len; $i++) {
                if ($code[$i] === '{') {
                    $depth++;
                } elseif ($code[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $i++;
                        break;
                    }
                }
            }
            $body   = substr($code, $start + 1, max(0, $i - $start - 2));
            $offset = $i;

            if ($vis !== 'public') {
                continue;
            }

            $limpio = preg_replace('#/\*.*?\*/#s', '', $body);
            $limpio = trim((string) preg_replace('#//.*$#m', '', (string) $limpio));

            $out[] = [
                'name'  => $name,
                'vacio' => $limpio === '',
                'linea' => substr_count(substr($code, 0, $pos), "\n") + 1,
            ];
        }

        return $out;
    }

    /**
     * Índice de rutas REGISTRADAS: acción "FQCN@metodo" → [métodos+uri legibles].
     * Se construye una vez por request/comando.
     */
    private function rutasRegistradas(): array
    {
        if ($this->indiceRutas !== null) {
            return $this->indiceRutas;
        }

        $acciones = [];
        foreach (RouteFacade::getRoutes() as $r) {
            $accion = $r->getActionName();
            if (! is_string($accion) || ! str_contains($accion, '@')) {
                continue;
            }
            $verbos = implode('|', array_diff($r->methods(), ['HEAD']));
            $acciones[$accion][] = $verbos . ' /' . ltrim($r->uri(), '/');
        }

        return $this->indiceRutas = ['acciones' => $acciones];
    }
}
