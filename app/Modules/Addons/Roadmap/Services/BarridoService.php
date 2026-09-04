<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * "MODO BARRIDO" — Torre 24/7 Pieza 5b (#908), FASE 2a (#985).
 *
 * Cuando el pool está seco y sobra una terminal, en vez de quedarse ociosa una terminal puede
 * dedicar su vuelta a explorar SOLO LECTURA un módulo del código en busca de hallazgos (la
 * exploración en sí es FASE 2b/#986; el despacho FIFO de lo que encuentre es FASE 3/#987).
 *
 * Este servicio SOLO resuelve tres preguntas:
 *   1. ¿Debe una terminal entrar en modo barrido AHORA?     → `debeBarrer()`
 *   2. ¿Puede tomar el turno (nadie más está barriendo)?    → `tomarCandado()` / `liberarCandado()`
 *   3. ¿Qué módulo le toca?                                  → `elegirModulo()` / `marcarBarrido()`
 *
 * NO barre nada él mismo — no toca código, no crea items.
 *
 * Reusa `AuditorService::slotsLibres()/rachaSeca()/profundidadCola()/modulosOrdenadosPorCobertura()`
 * en vez de reimplementarlos (#985 lo pide explícito: no esperar a #907/#980, leer esos métodos
 * públicos directo). La memoria de cobertura del barrido (`circuito_barrido_cobertura_modulos`) es
 * DELIBERADAMENTE una clave `settings` distinta de la del auditor mecánico
 * (`circuito_auditor_cobertura_modulos`, #1015): así un ciclo nunca pisa la cobertura que el otro
 * usa para decidir qué módulo le toca.
 */
class BarridoService
{
    /** Candado single-flight: sólo UNA terminal en modo barrido a la vez. JSON {worker_sid, iniciado_at}. */
    private const SETTING_EN_CURSO = 'circuito_barrido_en_curso';

    /** Memoria de cobertura PROPIA del barrido: JSON {modulo: {ultima_barrida_at}}. */
    private const SETTING_COBERTURA = 'circuito_barrido_cobertura_modulos';

    public function __construct(private AuditorService $auditor, private RoadmapIntakeService $intake)
    {
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // 1. DISPARADOR — ¿debe entrar una terminal en modo barrido?
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Diagnóstico completo (mismo estilo que `AuditorService::debeCorrer()`). Los tres parámetros
     * opcionales permiten SIMULAR el estado (verificación en tinker sin tocar cola/slots reales,
     * o sin mutar `settings` compartidos con el auditor mecánico); sin ellos usa las señales
     * reales del sistema.
     */
    public function debeBarrer(?int $cola = null, ?int $slots = null, ?int $racha = null): array
    {
        $cola  ??= $this->auditor->profundidadCola();
        $slots ??= $this->auditor->slotsLibres();
        $racha ??= $this->auditor->rachaSeca();

        $colaMax     = (int) config('circuito.barrido.cola_max_para_barrer', 0);
        $rachaMin    = (int) config('circuito.barrido.racha_seca_min', 1);
        $slotsMin    = (int) config('circuito.barrido.slots_libres_min', 1);

        $base = ['cola' => $cola, 'slots_libres' => $slots, 'racha_seca' => $racha,
            'cola_max' => $colaMax, 'racha_min' => $rachaMin, 'slots_min' => $slotsMin];

        if ($cola > $colaMax) {
            return $base + ['barre' => false, 'motivo' => "Cola con {$cola} item(s) reclamables (máximo {$colaMax} para considerar el pool seco): hay trabajo real, no hace falta barrer."];
        }
        if ($racha < $rachaMin) {
            return $base + ['barre' => false, 'motivo' => "Racha seca del auditor en {$racha} (mínimo {$rachaMin}): podría ser un valle momentáneo de la cola, todavía no se considera seco."];
        }
        if ($slots < $slotsMin) {
            return $base + ['barre' => false, 'motivo' => "Sólo {$slots} terminal(es) libre(s) (mínimo {$slotsMin}): no sobra capacidad para dedicar a explorar."];
        }
        if ($this->candadoActivo()) {
            return $base + ['barre' => false, 'motivo' => 'Ya hay una terminal en modo barrido (candado activo): single-flight, no se apilan dos.'];
        }

        return $base + ['barre' => true, 'motivo' => "Pool seco (cola {$cola}, racha {$racha}) con {$slots} terminal(es) libre(s): entra en modo barrido."];
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // 2. CANDADO SINGLE-FLIGHT
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /** ¿Hay un candado VIVO (no huérfano) ahora mismo? */
    public function candadoActivo(): bool
    {
        return $this->leerCandado() !== null;
    }

    /** Candado vigente (`worker_sid`/`iniciado_at`), o null si no hay o está vencido (huérfano por TTL). */
    public function leerCandado(): ?array
    {
        $raw = DB::table('settings')->where('key', self::SETTING_EN_CURSO)->value('value');
        if (! $raw) {
            return null;
        }
        $d = json_decode((string) $raw, true);
        if (! is_array($d) || empty($d['iniciado_at']) || empty($d['worker_sid'])) {
            return null;
        }
        $ttl = (int) config('circuito.barrido.candado_ttl_min', 25);
        if (Carbon::parse($d['iniciado_at'])->addMinutes($ttl)->isPast()) {
            // Huérfano: una terminal murió a medio barrido y nunca liberó. Se trata como libre;
            // la siguiente `tomarCandado()` lo sobrescribe con su propio dueño.
            return null;
        }

        return $d;
    }

    /**
     * Intenta tomar el candado para `$workerSid`. Devuelve true si quedó como dueño.
     *
     * No hay transacción/lock de fila explícito: la ventana de carrera entre dos terminales
     * llamando casi al mismo instante es de milisegundos, y el peor caso de perderla (dos
     * terminales barren el mismo módulo una vez) no es un daño irreversible — el barrido es
     * SOLO LECTURA sobre el código (#908). Por eso basta re-leer después de escribir y verificar
     * que el dueño que quedó grabado sea uno mismo (last-write-wins con verificación).
     */
    public function tomarCandado(string $workerSid): bool
    {
        if ($this->candadoActivo()) {
            return false;
        }

        $payload = ['worker_sid' => $workerSid, 'iniciado_at' => now()->toIso8601String()];
        DB::table('settings')->updateOrInsert(
            ['key' => self::SETTING_EN_CURSO],
            ['value' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );

        $tomado = ($this->leerCandado()['worker_sid'] ?? null) === $workerSid;
        if ($tomado) {
            Log::channel('roadmap_externo')->info('barrido-candado-tomado', $payload);
        }

        return $tomado;
    }

    /**
     * Libera el candado — SOLO si `$workerSid` sigue siendo su dueño. Si el TTL ya venció y otra
     * terminal lo retomó, este `liberarCandado()` tardío NO debe pisarle el candado al nuevo dueño.
     */
    public function liberarCandado(string $workerSid): void
    {
        $raw = DB::table('settings')->where('key', self::SETTING_EN_CURSO)->value('value');
        $d   = $raw ? json_decode((string) $raw, true) : null;
        if (! is_array($d) || ($d['worker_sid'] ?? null) !== $workerSid) {
            return;
        }

        DB::table('settings')->where('key', self::SETTING_EN_CURSO)->delete();
        Log::channel('roadmap_externo')->info('barrido-candado-liberado', ['worker_sid' => $workerSid]);
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // 3. ROTACIÓN DE MÓDULO
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /** Mapa `modulo => {ultima_barrida_at}` — cobertura PROPIA del barrido (ver docblock de clase). */
    public function cobertura(): array
    {
        $raw = DB::table('settings')->where('key', self::SETTING_COBERTURA)->value('value');
        if (! $raw) {
            return [];
        }
        $d = json_decode((string) $raw, true);

        return is_array($d) ? $d : [];
    }

    /**
     * Próximo módulo a barrer: mismo universo y prioridad de carril que el auditor mecánico
     * (`AuditorService::modulosCandidatos()`), pero ordenado por LA COBERTURA DEL BARRIDO — nunca
     * barrido primero, luego el de barrida más vieja.
     */
    public function elegirModulo(): ?string
    {
        $modulos = $this->auditor->modulosOrdenadosPorCobertura($this->cobertura(), 'ultima_barrida_at');

        return $modulos[0] ?? null;
    }

    /** Marca `$modulo` como recién barrido — avanza la rotación para la próxima corrida. */
    public function marcarBarrido(string $modulo): void
    {
        $cobertura = $this->cobertura();
        $cobertura[$modulo] = ['ultima_barrida_at' => now()->toIso8601String()];
        DB::table('settings')->updateOrInsert(
            ['key' => self::SETTING_COBERTURA],
            ['value' => json_encode($cobertura, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // 4. EXPLORACIÓN — FASE 2b-i (#9990032): hallazgos crudos, SOLO LECTURA, sin crear items
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Hallazgos CRUDOS de `$modulo` (mismo formato `$gap` que los detectores de `AuditorService`:
     * modulo/tipo/clase/clave/titulo/detalle/pregunta?) — SIN crear ningún item (eso es la Fase
     * 2b-ii, sub-item hermano) y SIN tocar el código del módulo barrido: sólo lee archivos y corre
     * `php -l` como proceso externo, nunca `require`/`include`.
     *
     * Combina:
     *   1. `AuditorService::detectoresDeCodigo()` (#9990232) — los TRES detectores de código que
     *      comparte con el auditor: `detNullSafety` (por módulo) y los dos cross-cutting
     *      `detJquerySinOff` + `detEnvRuntime`, que se autolimitan a `Roadmap / Circuito CC`
     *      porque los componentes Vue no viven bajo el `$dir` PHP de un módulo de negocio.
     *      Sustituye a la llamada anterior a `detectoresCrossCutting()`, que era un SUBCONJUNTO
     *      de éste: mantener las dos duplicaba jQuery y env_runtime.
     *   2. Grep de TODO/FIXME/deprecated + `php -l` sobre los archivos PHP reales del módulo
     *      (resuelto vía `AuditorService::rutaModulo()`).
     *
     * Capado por `circuito.barrido.hallazgos_max_por_barrida` para no generar de un jalón más
     * hallazgos de los que un solo ciclo de despacho (Fase 3/#987) pueda repartir sin dejar
     * terminales ociosas.
     */
    public function explorar(string $modulo): array
    {
        // #9990232 — UNA sola llamada. `detectoresCrossCutting()` (jQuery + env_runtime) es un
        // SUBCONJUNTO de `detectoresDeCodigo()`, que además trae `detNullSafety` — el único de los
        // tres que el barrido no alcanzaba. Llamar a los dos duplicaba jQuery y env_runtime en el
        // módulo del circuito (los únicos donde esos dos emiten: son cross-cutting y se autolimitan
        // a 'Roadmap / Circuito CC' porque los componentes Vue no viven bajo el $dir de un módulo).
        $dir       = $this->auditor->rutaModulo($modulo);
        $hallazgos = $this->auditor->detectoresDeCodigo($modulo, $dir);

        if ($dir !== null) {
            $hallazgos = array_merge(
                $hallazgos,
                $this->detTodoFixme($modulo, $dir),
                $this->detErrorSintaxis($modulo, $dir)
            );
        }

        $cap = max(0, (int) config('circuito.barrido.hallazgos_max_por_barrida', 3));

        return array_slice($hallazgos, 0, $cap);
    }

    /**
     * Grep de marcadores TODO/FIXME/deprecated EN COMENTARIO — un hallazgo por ocurrencia
     * (archivo+línea). Mismo patrón que `AuditorService::detTodos()` (comentario `//`/`/*`/`#`
     * + TODO/FIXME exactos en mayúsculas): evita el falso positivo de la palabra española "todo"
     * (ej. "Todo ya estaba sincronizado") que el `/i` sin anclar a comentario disparaba antes.
     */
    private function detTodoFixme(string $modulo, string $dir): array
    {
        $hallazgos = [];
        foreach ($this->auditor->archivosPhp($dir) as $file) {
            $lineas = @file($file);
            if (! $lineas) {
                continue;
            }
            $rel = $this->auditor->relativo($file);
            foreach ($lineas as $i => $linea) {
                if (! preg_match('#(?://|/\*+|\#)\s*(TODO|FIXME|[Dd]eprecated)\b#u', $linea, $m)) {
                    continue;
                }
                $n = $i + 1;
                $hallazgos[] = [
                    'modulo'  => $modulo,
                    'tipo'    => 'barrido_todo_fixme',
                    'clase'   => 'mecanico',
                    'clave'   => "barrido-todo:{$rel}:{$n}",
                    'titulo'  => "Barrido: {$m[1]} en {$rel}:{$n}",
                    'detalle' => "Encontrado durante el barrido exploratorio del módulo «{$modulo}»:\n\n"
                        . "  {$rel}:{$n}: " . trim($linea) . "\n\n"
                        . "Revisar si sigue vigente y resolver o eliminar el comentario.",
                ];
            }
        }

        return $hallazgos;
    }

    /** `php -l` por archivo, como proceso externo (jamás `require`/`include` del archivo). */
    private function detErrorSintaxis(string $modulo, string $dir): array
    {
        $hallazgos = [];
        foreach ($this->auditor->archivosPhp($dir) as $file) {
            $lint = new Process(['php', '-l', $file], base_path());
            $lint->setTimeout(10);
            $lint->run();
            if ($lint->isSuccessful()) {
                continue;
            }
            $rel = $this->auditor->relativo($file);
            $hallazgos[] = [
                'modulo'  => $modulo,
                'tipo'    => 'barrido_error_sintaxis',
                'clase'   => 'mecanico',
                'clave'   => "barrido-sintaxis:{$rel}",
                'titulo'  => "Barrido: error de sintaxis en {$rel}",
                'detalle' => "`php -l` falló durante el barrido exploratorio del módulo «{$modulo}»:\n\n"
                    . trim($lint->getErrorOutput() . $lint->getOutput()),
            ];
        }

        return $hallazgos;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // 5. CREACIÓN DE HALLAZGOS — FASE 2b-ii (#9990033): items reales, SIN duplicar
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Crea un item por cada hallazgo NUEVO de `$hallazgos` (formato `$gap` de `explorar()`).
     *
     * DEDUP: reusa `AuditorService::yaExiste()/huella()` TAL CUAL — misma columna
     * `auditor_fingerprint`, así un hallazgo de barrido y uno del auditor mecánico sobre el MISMO
     * gap (huella = tipo+módulo+clave, ambos motores comparten formato `$gap`) no se duplican
     * entre sí, y una segunda corrida de barrido sobre el mismo módulo sin cambios no repite nada.
     *
     * Cada hallazgo trae SU PROPIO 'modulo' (no el del módulo barrido en bloque): si algún día
     * `explorar()` mezcla hallazgos cross-cutting de otro módulo, cada item nace con el módulo real
     * del hallazgo — nunca se agrupan bajo uno solo (evita que items de módulos distintos dejen
     * terminales ociosas por compartir footprint, #986).
     */
    public function crearHallazgos(array $hallazgos, ?int $itemMadreId = null): array
    {
        $creados = [];
        foreach ($hallazgos as $gap) {
            // Segunda comprobación justo antes de escribir (igual que `AuditorService::ciclo()`):
            // entre el escaneo y aquí pudo entrar el mismo gap por otra vía (Irving, Cowork, el
            // auditor mecánico, otra terminal en barrido).
            if ($this->auditor->yaExiste($gap)) {
                continue;
            }
            try {
                $item = $this->crearItemDeHallazgo($gap, $itemMadreId);
                $creados[] = [
                    'id' => $item->id, 'modulo' => $gap['modulo'], 'tipo' => $gap['tipo'],
                    'clase' => $gap['clase'], 'titulo' => $item->title,
                ];
            } catch (\Throwable $e) {
                Log::channel('roadmap_externo')->warning('barrido-alta-fallo', [
                    'modulo' => $gap['modulo'] ?? null, 'clave' => $gap['clave'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $creados;
    }

    /**
     * Un item del hallazgo. MECÁNICO → nivel A ejecutable (mismo trato que `AuditorService::crear()`
     * — reversible/aditivo, cola normal). PRODUCTO → bandeja de Irving con la pregunta, nunca
     * autoejecutable. Título con el prefijo '[BARRIDO] ' LITERAL para que la Fase 3 (#987, despacho
     * FIFO de estos hallazgos) los identifique sin ambigüedad frente a los del auditor mecánico.
     */
    private function crearItemDeHallazgo(array $gap, ?int $itemMadreId): RoadmapItem
    {
        $esProducto = $gap['clase'] === 'producto';

        $item = $this->intake->crear([
            'title'          => '[BARRIDO] ' . $gap['titulo'],
            'description'    => $gap['detalle'],
            'prompt'         => $esProducto ? null : $this->promptEjecutable($gap),
            'modulo'         => $gap['modulo'],
            'nivel_riesgo'   => $esProducto ? 'C' : 'A',
            'priority'       => 'media',
            'origen_item_id' => $itemMadreId,
        ], 'barrido', true);

        $item->auditor_fingerprint = $this->auditor->huella($gap);

        if ($esProducto) {
            // Bandeja de Irving. `requiere_irving: true` impide que el autopilot la tome, y el
            // estado `requiere_irving` lo deja fuera del pool de reclamo.
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

        Log::channel('roadmap_externo')->info('barrido-item-creado', [
            'id' => $item->id, 'modulo' => $gap['modulo'], 'tipo' => $gap['tipo'],
            'clase' => $gap['clase'], 'huella' => $item->auditor_fingerprint,
        ]);

        return $item;
    }

    /** Spec ejecutable para la terminal que tome el item mecánico (equivalente barrido del de AuditorService). */
    private function promptEjecutable(array $gap): string
    {
        return "Este item lo generó el MODO BARRIDO (Torre 24/7 Pieza 5b, #908) al explorar SOLO "
            . "LECTURA el módulo {$gap['modulo']} durante un valle de pool seco. Es un gap MECÁNICO: "
            . "aditivo/reversible, sin decisión de producto de por medio.\n\n"
            . "QUÉ CERRAR\n{$gap['detalle']}\n\n"
            . "CÓMO CERRARLO\n"
            . "- Cambio mínimo que resuelve el gap. Nada de refactors de paso.\n"
            . "- Si al abrirlo resulta que SÍ hay una decisión de producto detrás (qué debe hacer la "
            . "pantalla, qué política aplica), NO la inventes: consulta a Jarvis.\n"
            . "- Verifica: `php -l` de lo que toques + `php artisan --version` (que bootee). Si tocas "
            . "frontend, compila con `bash deploy/circuito/npm-build.sh`.\n"
            . "- DoD del item: el gap ya no aparece si se vuelve a correr el barrido sobre este módulo.";
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // 6. CICLO COMPLETO — FASE 2b-ii (#9990033): el punto de entrada real del "modo barrido"
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * `tomarCandado` → `elegirModulo` → `explorar` → crear hallazgos (o "sin hallazgos", sin
     * inventar ruido) → `marcarBarrido` → `liberarCandado`. SIEMPRE libera el candado al salir
     * (try/finally) — un barrido que se queda sin liberar bloquea a cualquier otra terminal hasta
     * que expire el TTL (`candado_ttl_min`), y eso es justo lo que este método existe para evitar.
     *
     * `$apply=false` recorre el mismo camino (toma candado real, explora) pero NO crea items ni
     * marca cobertura — sirve para probar el cableado sin ensuciar la Hoja de Ruta.
     */
    public function ciclo(string $workerSid, bool $apply = true): array
    {
        if (! $this->tomarCandado($workerSid)) {
            return ['tomo_candado' => false, 'modulo' => null, 'hallazgos' => 0, 'creados' => [],
                'motivo' => 'Ya hay otra terminal en modo barrido (candado activo): no se toma turno.'];
        }

        try {
            $modulo = $this->elegirModulo();
            if ($modulo === null) {
                Log::channel('roadmap_externo')->info('barrido-sin-modulo', ['worker_sid' => $workerSid]);

                return ['tomo_candado' => true, 'modulo' => null, 'hallazgos' => 0, 'creados' => []];
            }

            $hallazgos = $this->explorar($modulo);

            if (! $hallazgos) {
                // Honesto: nada que reportar no es un hallazgo. Se libera igual el candado (en el
                // finally) para que la siguiente terminal libre pueda tomar el turno de barrido.
                Log::channel('roadmap_externo')->info('barrido-sin-hallazgos', [
                    'worker_sid' => $workerSid, 'modulo' => $modulo,
                ]);
                if ($apply) {
                    $this->marcarBarrido($modulo);
                }

                return ['tomo_candado' => true, 'modulo' => $modulo, 'hallazgos' => 0, 'creados' => []];
            }

            $creados = $apply ? $this->crearHallazgos($hallazgos) : [];
            if ($apply) {
                $this->marcarBarrido($modulo);
            }

            return [
                'tomo_candado' => true, 'modulo' => $modulo, 'hallazgos' => count($hallazgos),
                'creados' => $creados, 'gaps' => $hallazgos,
            ];
        } finally {
            $this->liberarCandado($workerSid);
        }
    }
}
