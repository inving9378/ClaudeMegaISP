<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Console\DigestCommand;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Support\FrenoCircuito;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Lógica de negocio ÚNICA de la Hoja de Ruta para el Circuito de Mejora Continua.
 *
 * Extraída de RoadmapExternalController (sub-paso 1 del conector MCP) para que
 * TANTO la API externa token-en-path COMO el conector MCP (RoadmapMcpController)
 * compartan EXACTAMENTE las mismas queries, serialización, allowlist de escritura y
 * guards server-side — sin duplicar reglas (CLAUDE.md: "servicios compartidos únicos,
 * prohibido duplicar").
 *
 * Devuelve SIEMPRE arrays/modelos (nunca JsonResponse): el transporte (HTTP JSON o
 * JSON-RPC del MCP) lo decide cada llamador.
 */
class RoadmapCircuitoService
{
    /** Llave del kill switch en la tabla key-value `settings`. */
    public const PAUSE_KEY = 'circuito_pausado';

    /** Metadata de CUÁNDO/QUIÉN pausó (#343): JSON `{at, by}`. Solo existe mientras está en pausa. */
    public const PAUSE_META_KEY = 'circuito_pausado_meta';

    /** Llave del modo de ejecución del circuito. */
    public const MODO_KEY = 'circuito_modo';

    public const MODOS = ['aviso_previo', 'autonomo'];

    /** Llave del modo de integración de ramas (#325). */
    public const MODO_INTEGRACION_KEY = 'circuito_modo_integracion';

    public const MODOS_INTEGRACION = ['auto-merge', 'revisar-y-mergear'];

    /** Flag del agente REVISOR (#338): '1' = ON. Default OFF (arranque conservador). Control de Irving. */
    public const REVISOR_KEY = 'circuito_revisor';

    /**
     * #336: modelo del ejecutor CLI (`claude -p` en vuelta.sh) para la vuelta RUTINARIA
     * (leer/triagear/ejecutar A-B). Alias del CLI (ej. 'sonnet', 'opus'), no id completo de API.
     * Default 'sonnet' — cuida la cuota de Opus del plan Max para el uso interactivo.
     */
    public const MODELO_RUTINA_KEY = 'circuito_modelo_rutina';

    /**
     * #336: modelo reservado para razonamiento difícil (decisiones nivel C). El pase CLI en sí
     * siempre corre en rutina; el razonamiento difícil lo hace el REVISOR (#338, API directa,
     * `config('circuito.revisor.model_hard')`, ya en Opus) — esta llave queda como el equivalente
     * en alias-CLI, documentada y lista para un futuro pase `claude -p --model opus` si se necesita.
     */
    public const MODELO_DIFICIL_KEY = 'circuito_modelo_dificil';

    /**
     * #336 (Opción D — escape hatch): si se setea, PISA tanto rutina como difícil para el CLI.
     * Vacío/ausente = sin forzar (usa rutina/difícil normal). Control de Irving.
     */
    public const MODELO_FORZAR_KEY = 'circuito_modelo_forzar';

    /**
     * Voz (SpeechSynthesisVoice.name) elegida por el administrador para 🔊 Escuchar en la Torre
     * de Integración (#424). Vacío/ausente = automática (la Torre elige es-MX → es-* → default).
     */
    public const VOICE_KEY = 'circuito_tts_voice';

    /** Velocidad (SpeechSynthesisUtterance.rate) de 🔊 Escuchar (#424). Vacío/ausente = 1.0. Rango [0.5, 2.0]. */
    public const RATE_KEY = 'circuito_tts_rate';

    /**
     * Estado EN VIVO de una vuelta (#335), espejeado en `settings`. Es el canal compartido:
     * lo ESCRIBE el ejecutor on-box (usuario meganet, que SÍ puede leer el log en /home/meganet)
     * y lo LEE la Torre (php-fpm = www-data, que NO puede leer ese log por permisos). Forma:
     *   { started_at, heartbeat_at, finished, log_path, log_tail, current_item, fases, ... }
     *
     * #334 Fase 0 — estado POR SESIÓN: cada worktree/vuelta escribe su propia fila
     * `circuito_live:<sid>` (LIVE_PREFIX + sid). Antes era un blob único `circuito_live`; ahora
     * varias sesiones (paralelas en Fase 1) coexisten sin pisarse. `LIVE_KEY` queda como base
     * del prefijo y como llave LEGACY que se ignora/limpia en la transición.
     */
    public const LIVE_KEY = 'circuito_live';

    /** Prefijo de las filas de estado live por-sesión: `circuito_live:<sid>` (#334). */
    public const LIVE_PREFIX = 'circuito_live:';

    /** Una sesión TERMINADA se sigue mostrando este tiempo (seg) y luego se cae del visor. */
    public const LIVE_ENDED_RETAIN_SEG = 300;

    /** Latido más frío que esto (seg) con la vuelta "corriendo" ⇒ posible circuito caído. */
    public const HEARTBEAT_STALE_SEG = 90;

    /** Cuántas líneas del final del log se espejean a BD para el panel "Ver log en vivo". */
    private const LOG_TAIL_LINES = 60;

    /** Enum canónico de fases del ejecutor (orden natural del stepper del visor #349). */
    public const FASES = ['triage', 'decision', 'rama', 'editando', 'verificando', 'integrando'];

    /**
     * Flag de DISPARO manual pendiente (#337): lo escribe la Torre (www-data) y lo consume
     * el picker on-box (meganet). Un SOLO flag ⇒ debounce natural (N disparos en la ventana
     * colapsan a una vuelta). JSON: { requested_at, by, origin, item_id }.
     */
    public const DISPARO_KEY = 'circuito_disparo_pendiente';

    /**
     * Cola de MERGE a dev (#334 F0-fix). La Torre (www-data) NO puede escribir `.git` (objetos/refs
     * los creó el ejecutor=meganet, sin group-write para www-data), así que el merge NO lo hace
     * www-data: se ENCOLA aquí y lo ejecuta el runner on-box (meganet, en el checkout PRINCIPAL,
     * donde vive `main`) — mismo patrón que el disparo. FIFO de {item_id, by, trigger, at}.
     */
    public const MERGE_QUEUE_KEY = 'circuito_merge_cola';

    /** Resultado del último intento de merge por item: `circuito_merge_result:<id>` (para la UI). */
    public const MERGE_RESULT_PREFIX = 'circuito_merge_result:';

    public function find(int $id): ?RoadmapItem
    {
        return RoadmapItem::find($id);
    }

    /**
     * KILL SWITCH del Circuito. `true` = pausado: el ejecutor NO debe ejecutar nada
     * (sigue leyendo/reportando). Persistido en `settings` para que lo respeten por
     * igual la Torre de control (botón), la API externa y el conector MCP.
     */
    /**
     * ¿Está frenado el circuito? (#170)
     *
     * ── EL ORDEN IMPORTA ────────────────────────────────────────────────────────────────────
     * 1. El CENTINELA EN ARCHIVO. Su existencia es la pausa y no necesita a MySQL.
     * 2. Sólo si no está puesto, la fila de `settings` (el freno de la Torre, que sigue siendo
     *    el que usa Irving desde la UI y el único que respeta el candado #342).
     *
     * ── FAIL-CLOSED ─────────────────────────────────────────────────────────────────────────
     * CUALQUIER excepción devuelve `true` = FRENADO. Antes, con la base caída esto lanzaba, y la
     * excepción se propagaba en unos caminos y se tragaba en otros: el único mecanismo capaz de
     * detener seis terminales con permiso de escritura sobre el repo dejaba de existir justo
     * cuando más falta hacía. Un falso "frenado" cuesta una vuelta perdida; un falso "suelto"
     * cuesta seis terminales trabajando a ciegas sobre una base que no responde.
     *
     * El fallo se registra EN ARCHIVO, nunca en base: si la base es el problema, escribir ahí el
     * motivo es perder justo el rastro que lo explica.
     */
    public function isPaused(): bool
    {
        try {
            if (FrenoCircuito::activo()) {
                return true;
            }

            return (string) DB::table('settings')->where('key', self::PAUSE_KEY)->value('value') === '1';
        } catch (\Throwable $e) {
            FrenoCircuito::registrarFallo('isPaused', $e);

            return true;   // fail-closed: ante la duda, frenado.
        }
    }

    /**
     * Escribe el KILL SWITCH. #342 (seguridad): SOLO desde la Torre — un humano autenticado
     * (HTTP) con permiso `circuito.pause`. El ejecutor on-box corre por CLI SIN sesión, así
     * que esta puerta lo bloquea: para él el flag es SOLO-LECTURA (`isPaused`). Si está en 1
     * debe ABORTAR la vuelta, nunca cambiarlo. Cualquier intento fuera del contexto UI lanza.
     */
    public function setPaused(bool $paused): void
    {
        if (! (auth()->check() && auth()->user()->can('circuito.pause'))) {
            throw new \RuntimeException(
                'circuito_pausado es de solo-lectura fuera de la Torre: el kill switch solo lo '
                . 'cambia un humano autenticado con permiso circuito.pause (el ejecutor no puede).'
            );
        }
        $u     = auth()->user();
        $quien = 'irving:' . ($u->login_user ?? $u->email ?? $u->id ?? '?');

        // El freno vive en ARCHIVO desde #170 e `isPaused()` es `archivo || base`. Si la Torre
        // sólo tocara la base, el botón no podría soltar un freno puesto desde la consola: el
        // endpoint contestaría "reanudado", la UI diría "corriendo" y las seis terminales
        // seguirían detenidas. Eso es exactamente el botón que miente que #170 vino a evitar.
        // El centinela se mueve PRIMERO al frenar (fail-closed: si falla lo de abajo, ya frenó)
        // y AL FINAL al soltar (fail-closed: nada se suelta hasta que el resto quedó consistente).
        if ($paused) {
            FrenoCircuito::poner('Freno puesto desde la Torre.', $quien);
        }

        $this->putSetting(self::PAUSE_KEY, $paused ? '1' : '0');

        // #343: sella cuándo/quién pausó (auditoría + salvaguarda de "pausa olvidada"). Al
        // reanudar se limpia — la meta solo es relevante mientras sigue en pausa.
        if ($paused) {
            $this->putSetting(self::PAUSE_META_KEY, json_encode([
                'at' => now()->timestamp,
                'by' => $quien,
            ], JSON_UNESCAPED_UNICODE));
        } else {
            DB::table('settings')->where('key', self::PAUSE_META_KEY)->delete();
            FrenoCircuito::quitar();
        }
    }

    /**
     * Info de la pausa vigente para la salvaguarda "pausa olvidada" (#343): PURO-LECTURA,
     * NO reanuda nada — el kill switch lo sigue tocando solo un humano en la Torre. Devuelve
     * null si no está pausado. `aviso` = ya pasó el umbral configurable (default 3h).
     */
    public function pausedInfo(): ?array
    {
        if (! $this->isPaused()) {
            return null;
        }
        // El freno vive en ARCHIVO desde #170, y `isPaused()` mira ahí PRIMERO. Esta info tiene
        // que seguir la misma precedencia: si el centinela está puesto, su motivo/quién/cuándo es
        // la verdad. Leer sólo la meta de `settings` dejaba el panel con todo en null —o sea,
        // "pausa olvidada" sin poder decir por qué— justo cuando el freno lo puso la consola.
        $desde = null;
        $por   = null;

        if ($det = FrenoCircuito::detalle()) {
            $por = $det['quien'] ?? null;
            if (! empty($det['cuando'])) {
                try {
                    $desde = Carbon::parse($det['cuando'])->toIso8601String();
                } catch (\Throwable $e) {
                    $desde = null;   // centinela con fecha ilegible: sigue frenado, sin fingir la hora.
                }
            }
            $motivo = $det['motivo'] ?? null;
        }

        if ($desde === null || $por === null) {
            $raw  = DB::table('settings')->where('key', self::PAUSE_META_KEY)->value('value');
            $meta = $raw ? json_decode((string) $raw, true) : null;
            $at   = is_array($meta) ? (int) ($meta['at'] ?? 0) : 0;

            $desde = $desde ?? ($at > 0 ? Carbon::createFromTimestamp($at)->toIso8601String() : null);
            $por   = $por   ?? (is_array($meta) ? ($meta['by'] ?? null) : null);
        }

        $horas  = $desde ? round((time() - Carbon::parse($desde)->timestamp) / 3600, 1) : null;
        $umbral = (float) config('circuito.pausa_aviso_horas', 3);

        return [
            'desde'       => $desde,
            'por'         => $por,
            'motivo'      => $motivo ?? null,
            'horas'       => $horas,
            'aviso_horas' => $umbral,
            // Sin meta (pausada antes de este fix, o vía CLI legacy) → no se puede calcular
            // antigüedad; se avisa igual por precaución (mejor falso-positivo que pausa olvidada).
            'olvidada'    => $horas === null || $horas >= $umbral,
        ];
    }

    /**
     * Modo de ejecución del ejecutor on-box: `aviso_previo` (default; solo propone en
     * comentarios_claude, no ejecuta código) | `autonomo` (ejecuta A/B en su rama con
     * verificación). Un valor no reconocido cae a `aviso_previo` (falla-seguro).
     */
    public function getModo(): string
    {
        $v = (string) DB::table('settings')->where('key', self::MODO_KEY)->value('value');
        return in_array($v, self::MODOS, true) ? $v : 'aviso_previo';
    }

    public function setModo(string $modo): void
    {
        if (! in_array($modo, self::MODOS, true)) {
            $modo = 'aviso_previo';
        }
        $this->putSetting(self::MODO_KEY, $modo);
    }

    /**
     * Modo de integración de ramas (#325): `auto-merge` (default; A/B se integran solas al
     * verificar) | `revisar-y-mergear` (las ramas ESPERAN el ✓ de Irving en la Torre).
     * Valor no reconocido → `auto-merge` (comportamiento actual).
     */
    public function getModoIntegracion(): string
    {
        $v = (string) DB::table('settings')->where('key', self::MODO_INTEGRACION_KEY)->value('value');
        return in_array($v, self::MODOS_INTEGRACION, true) ? $v : 'auto-merge';
    }

    public function setModoIntegracion(string $modo): void
    {
        if (! in_array($modo, self::MODOS_INTEGRACION, true)) {
            $modo = 'auto-merge';
        }
        $this->putSetting(self::MODO_INTEGRACION_KEY, $modo);
    }

    /**
     * Agente REVISOR (#338): ¿está ON? Default OFF (arranque conservador). Cuando está ON, el
     * ejecutor consulta al revisor para sus B (dentro de alcance) y EJECUTA los `aprobado_revisor`.
     * Con OFF, el revisor no participa en el ciclo y los `aprobado_revisor` NO se ejecutan solos
     * (quedan marcados esperando que Irving encienda el flag). El ejecutor NUNCA debe escribirlo.
     */
    public function revisorEnabled(): bool
    {
        return (string) DB::table('settings')->where('key', self::REVISOR_KEY)->value('value') === '1';
    }

    /** Enciende/apaga el flag del revisor (control de Irving; el ejecutor no lo toca). */
    public function setRevisorEnabled(bool $on): void
    {
        $this->putSetting(self::REVISOR_KEY, $on ? '1' : '0');
    }

    /** Modelo (alias CLI) para la vuelta rutinaria del ejecutor. Vacío/ausente → 'sonnet'. */
    public function getModeloRutina(): string
    {
        $v = trim((string) DB::table('settings')->where('key', self::MODELO_RUTINA_KEY)->value('value'));
        return $v !== '' ? $v : 'sonnet';
    }

    public function setModeloRutina(string $modelo): void
    {
        $this->putSetting(self::MODELO_RUTINA_KEY, mb_substr(trim($modelo), 0, 40));
    }

    /** Modelo (alias CLI) reservado para razonamiento difícil (nivel C). Vacío/ausente → 'opus'. */
    public function getModeloDificil(): string
    {
        $v = trim((string) DB::table('settings')->where('key', self::MODELO_DIFICIL_KEY)->value('value'));
        return $v !== '' ? $v : 'opus';
    }

    public function setModeloDificil(string $modelo): void
    {
        $this->putSetting(self::MODELO_DIFICIL_KEY, mb_substr(trim($modelo), 0, 40));
    }

    /** Override que pisa rutina/difícil (Opción D, escape hatch). '' = sin forzar. */
    public function getModeloForzar(): string
    {
        return trim((string) DB::table('settings')->where('key', self::MODELO_FORZAR_KEY)->value('value'));
    }

    public function setModeloForzar(string $modelo): void
    {
        $this->putSetting(self::MODELO_FORZAR_KEY, mb_substr(trim($modelo), 0, 40));
    }

    /**
     * Resuelve el modelo (alias CLI) que debe usar el ejecutor en ESTA vuelta: `circuito_modelo_forzar`
     * pisa todo si está seteado; si no, rutina (el `claude -p` de vuelta.sh SIEMPRE es la vuelta
     * rutinaria — el razonamiento difícil de nivel C lo hace el REVISOR #338 por API, no este pase).
     */
    public function resolveModeloCli(): string
    {
        $forzar = $this->getModeloForzar();
        return $forzar !== '' ? $forzar : $this->getModeloRutina();
    }

    /** Voz guardada para 🔊 Escuchar (#424). Null = sin preferencia → la Torre usa su fallback es-MX. */
    public function getVozTts(): ?string
    {
        $v = (string) DB::table('settings')->where('key', self::VOICE_KEY)->value('value');
        return $v !== '' ? $v : null;
    }

    /** Persiste la voz elegida por el administrador. Null/'' = borrar preferencia (vuelve a automática). */
    public function setVozTts(?string $voz): void
    {
        $this->putSetting(self::VOICE_KEY, trim((string) $voz));
    }

    /** Velocidad (rate) de 🔊 Escuchar (#424). Default 1.0; se guarda acotada a [0.5, 2.0]. */
    public function getRateTts(): float
    {
        $v = (string) DB::table('settings')->where('key', self::RATE_KEY)->value('value');
        return $v !== '' ? $this->clampRate((float) $v) : 1.0;
    }

    /** Persiste la velocidad elegida por el administrador (acotada a [0.5, 2.0]). */
    public function setRateTts(float $rate): void
    {
        $this->putSetting(self::RATE_KEY, (string) $this->clampRate($rate));
    }

    /**
     * Foto del último `circuito:digest` (#791): decisiones mudas de los últimos 7 días, despachos
     * que tocan producción (24h) y dependencia del fallback legacy — guardada por el propio comando
     * en settings para que la Torre la pinte sin recalcular ni leer el log del cron. Incluye la
     * referencia del "antes" para que el número en vivo se lea como tendencia. Null si el digest
     * nunca corrió.
     */
    public function digestSnapshot(): ?array
    {
        $raw = DB::table('settings')->where('key', DigestCommand::SETTING)->value('value');
        if (! $raw) {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }

        $data['mudas_baseline_historico'] = DigestCommand::BASELINE_MUDAS_HISTORICO;
        $data['mudas_baseline_vivos']     = DigestCommand::BASELINE_MUDAS_VIVOS;

        return $data;
    }

    // ── FASE 2A.7 (#808) — LIVENESS DE LOS PROCESOS PROGRAMADOS ─────────────────────────────────

    /** Prefijo del setting de latido cuando el proceso no declara `beat_key` propia. */
    public const BEAT_PREFIJO = 'circuito_beat_';

    /** Nombre del setting donde late un proceso programado. */
    public static function beatKey(string $comando, array $cfg = []): string
    {
        return $cfg['beat_key'] ?? self::BEAT_PREFIJO . str_replace(':', '_', $comando);
    }

    /**
     * Sella el latido de un proceso programado. Lo llama UN listener de `CommandFinished` (ver
     * `ModuleServiceProvider`), no cada comando: así un proceso nuevo sólo necesita su fila en
     * `config('circuito.procesos_programados')` y nadie puede olvidarse de instrumentarlo.
     */
    public function sellarLatido(string $comando, ?\Symfony\Component\Console\Input\InputInterface $input = null): void
    {
        $cfg = config('circuito.procesos_programados.' . $comando);
        if (! is_array($cfg)) {
            return;   // no es un proceso vigilado
        }

        // Item #875 — el latido POR CORRIDA (historial), independiente de si esta corrida en
        // particular cuenta como la "beat" oficial (dry-run/opciones excluidas más abajo SÍ
        // corrieron y terminaron bien; el pulso lo refleja, aunque no mueva la beat).
        $this->registrarPulso($comando, true, null);

        if (($cfg['formato'] ?? 'datetime') === 'unix') {
            return;   // ese proceso sella su propio latido (no duplicar el reloj)
        }

        // Sólo cuenta la corrida que HIZO EL TRABAJO. Un dry-run, o la variante por-item de un
        // barrido, sellarían un latido falso y enmascararían que el cron no existe — justo la
        // mentira que este vigilante viene a evitar.
        if ($input) {
            foreach ((array) ($cfg['exige_opciones'] ?? []) as $op) {
                if (! $input->hasParameterOption('--' . $op)) {
                    return;
                }
            }
            foreach ((array) ($cfg['excluye_opciones'] ?? []) as $op) {
                if ($input->hasParameterOption('--' . $op)) {
                    return;
                }
            }
        }

        $this->putSetting(self::beatKey($comando, $cfg), now()->toDateTimeString());
        $this->limpiarFallo($comando);
    }

    /** Prefijo del setting donde se guarda el ÚLTIMO FALLO de un proceso vigilado. */
    public const FALLO_PREFIJO = 'circuito_fallo_';

    /**
     * ENTREGA 1 — registra el ÚLTIMO FALLO de un proceso vigilado, con su mensaje.
     *
     * POR QUÉ NO BASTA EL LATIDO. Medido el 2026-08-19: `circuito:destrabar-bandeja` llevaba ocho
     * días fallando cada minuto, **pero había logrado UNA corrida buena 13 minutos antes**. Un
     * indicador que sólo mira «última ejecución exitosa» decía «hace 13 min, todo bien» mientras el
     * comando se caía en cada intento. Un fallo intermitente se esconde detrás de su éxito ocasional.
     *
     * Por eso un motor se pinta roto si **el latido está viejo O hay un fallo reciente**. Y se
     * guarda el MENSAJE: «falló» sin decir qué no sirve para decidir nada.
     */
    public function sellarFallo(string $comando, string $error): void
    {
        if (! is_array(config('circuito.procesos_programados.' . $comando))) {
            return;   // no es un proceso vigilado
        }

        // Item #875 — el latido POR CORRIDA (historial). Mismo bloque que ya traga la excepción
        // del motor (eso no cambia); esto sólo deja constancia del fallo.
        $this->registrarPulso($comando, false, $error);

        $this->putSetting(
            self::FALLO_PREFIJO . str_replace(':', '_', $comando),
            json_encode(['ts' => now()->toDateTimeString(), 'error' => mb_strimwidth($error, 0, 400, '…')],
                JSON_UNESCAPED_UNICODE)
        );
    }

    /** Limpia el fallo registrado: lo llama la propia corrida exitosa del proceso. */
    public function limpiarFallo(string $comando): void
    {
        DB::table('settings')->where('key', self::FALLO_PREFIJO . str_replace(':', '_', $comando))->delete();
    }

    /** Pila de inicios (microtime) por comando — vive solo mientras dura el proceso PHP; sirve
     *  para calcular `duracion_ms` del pulso aunque el comando corra anidado (`Artisan::call()`). */
    private static array $motorInicios = [];

    /**
     * Item #875 — sella el INICIO de un motor vigilado. Lo llama UN listener de `CommandStarting`
     * (ver `ModuleServiceProvider`), simétrico al de `CommandFinished` que ya sella el latido.
     */
    public function marcarInicioMotor(string $comando): void
    {
        if (! is_array(config('circuito.procesos_programados.' . $comando))) {
            return;   // no es un proceso vigilado
        }
        self::$motorInicios[$comando][] = microtime(true);
    }

    /**
     * Item #875 — el latido POR CORRIDA: una fila en `circuito_motor_pulsos` por cada intento de
     * un motor vigilado (ok o fallo). Complementa al latido de #808 (que sólo guarda el ÚLTIMO
     * estado): esto es el HISTORIAL, para que el semáforo de la Torre pueda mostrar "última
     * ejecución exitosa" como un hecho con evidencia, no un flag `enabled`.
     *
     * Se llama desde `sellarLatido`/`sellarFallo` — el MISMO bloque que hoy traga la excepción del
     * motor (eso sigue siendo correcto y no cambia); esto sólo deja constancia. Nunca lanza.
     */
    private function registrarPulso(string $comando, bool $ok, ?string $mensaje): void
    {
        try {
            $inicio = ! empty(self::$motorInicios[$comando]) ? array_pop(self::$motorInicios[$comando]) : null;
            $fin = microtime(true);

            \App\Modules\Addons\Roadmap\Models\CircuitoMotorPulso::create([
                'motor'       => $comando,
                'inicio_at'   => $inicio ? \Illuminate\Support\Carbon::createFromTimestamp($inicio) : now(),
                'fin_at'      => \Illuminate\Support\Carbon::createFromTimestamp($fin),
                'ok'          => $ok,
                'mensaje'     => $mensaje !== null ? mb_strimwidth($mensaje, 0, 2000, '…') : null,
                'duracion_ms' => $inicio ? (int) round(($fin - $inicio) * 1000) : null,
            ]);

            // Retención (30 días): purga OPORTUNISTA en vez de un cron propio — ningún ejecutor
            // on-box puede escribir el crontab del SO (#808), así que no depende de una línea nueva.
            if (random_int(1, 200) === 1) {
                \App\Modules\Addons\Roadmap\Models\CircuitoMotorPulso::where('inicio_at', '<', now()->subDays(30))->delete();
            }
        } catch (\Throwable) {
            // El registro jamás puede tumbar al comando que acaba de correr.
        }
    }

    /**
     * FASE 2A.7 (#808) — ¿este comando está AGENDADO en el crontab?
     *
     * "No ha latido" tiene dos causas muy distintas: no está agendado (la regla es un no-op
     * invisible) o está agendado y falla. Sin separarlas, el aviso no dice qué hacer. Esto lo mira
     * de verdad en vez de suponerlo.
     *
     * Devuelve null si no se pudo verificar (sin crontab legible) — nunca miente por omisión.
     */
    public function agendado(string $comando): ?bool
    {
        try {
            $p = \Symfony\Component\Process\Process::fromShellCommandline('crontab -l 2>/dev/null');
            $p->setTimeout(5);
            $p->run();
            $salida = $p->getOutput();
            if (trim($salida) === '') {
                return null;
            }

            foreach (preg_split('/\R/', $salida) as $linea) {
                $linea = trim($linea);
                if ($linea === '' || str_starts_with($linea, '#')) {
                    continue;
                }
                if (str_contains($linea, $comando)) {
                    // #233 — la línea existe, pero si apunta a un wrapper de deploy/circuito/ sin
                    // permiso de ejecución (o inexistente), el proceso NUNCA arranca aunque la
                    // línea esté ahí — fue exactamente el incidente de `vigilia-wrap.sh` (100644,
                    // 25-ago): "está agendado" respondía que sí a un proceso que no podía correr.
                    // Reusa CronScriptsGuard (no reinventa el chequeo del candado #233).
                    if (CronScriptsGuard::problemas($linea) !== []) {
                        return false;
                    }

                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Estado de TODOS los procesos vigilados. `at = null` significa **nunca ha corrido**, que es
     * distinto de "corrió hace mucho" y suele ser el caso interesante: la regla existe pero no está
     * agendada.
     *
     * @return array<int,array{comando:string,at:?string,horas:?float,vencido:bool,nunca:bool,si_no_corre:string,max_horas:int,linea_cron:string,cadencia:string}>
     */
    public function latidos(): array
    {
        $out = [];

        foreach ((array) config('circuito.procesos_programados', []) as $comando => $cfg) {
            $raw = DB::table('settings')->where('key', self::beatKey($comando, $cfg))->value('value');

            $at = null;
            if ($raw !== null && $raw !== '') {
                $at = ($cfg['formato'] ?? 'datetime') === 'unix'
                    ? \Illuminate\Support\Carbon::createFromTimestamp((int) $raw)
                    : \Illuminate\Support\Carbon::parse($raw);
            }

            $maxH  = (int) ($cfg['max_horas'] ?? 48);
            $horas = $at ? round($at->diffInMinutes(now()) / 60, 1) : null;

            // ÚLTIMO FALLO — un motor con latido fresco pero fallando ahora mismo NO está sano.
            $fallo = DB::table('settings')
                ->where('key', self::FALLO_PREFIJO . str_replace(':', '_', $comando))->value('value');
            $fallo = $fallo ? json_decode($fallo, true) : null;
            $falloReciente = is_array($fallo) && isset($fallo['ts'])
                && \Illuminate\Support\Carbon::parse($fallo['ts'])->gt(now()->subHours(max(1, $maxH)));

            $out[] = [
                'comando'     => $comando,
                'at'          => $at?->toDateTimeString(),
                'horas'       => $horas,
                'nunca'       => $at === null,
                // Roto = latido viejo O fallo reciente. La segunda mitad caza los intermitentes,
                // que son los que más tiempo pasan sin que nadie los vea.
                'vencido'        => $at === null || $horas > $maxH || $falloReciente,
                'fallo_reciente' => $falloReciente,
                'ultimo_fallo'   => is_array($fallo) ? $fallo : null,
                'max_horas'   => $maxH,
                'agendado'    => $this->agendado($comando),
                'si_no_corre' => (string) ($cfg['si_no_corre'] ?? ''),
                // #942 — texto humano de la cadencia (solo lectura; se edita en el crontab del SO
                // o en Kernel.php, nunca desde este panel).
                'cadencia'    => (string) ($cfg['cadencia'] ?? ''),
                // #808 — línea exacta a pegar en `crontab -e` cuando `agendado === false`. Ningún
                // ejecutor on-box puede escribir el crontab del SO (bloqueado por el sandbox); esto
                // es lo más cerca que el circuito llega: dejar la línea correcta a un copy-paste,
                // en el mismo sitio donde el digest ya delata que falta.
                'linea_cron'  => (string) ($cfg['linea_cron'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * #946 (Fase 1b, hijo de #875) — SEMÁFORO de motores para la pestaña "Semáforo" de la Torre.
     * SOLO LECTURA: interpreta `latidos()` (#808, ya sella "última ejecución EXITOSA"), no cambia
     * el comportamiento de ningún motor.
     *
     * Icono: 🟢 vivo · 🟡 tarde (pasó 2× su cadencia esperada sin un éxito) · 🔴 vencido (pasó 3×,
     * o nunca corrió, o tiene un fallo reciente aunque su latido siga fresco — ver `latidos()`) ·
     * ⚫ apagado a propósito (kill switch en pausa, o el motor trae su propio flag `activo=false`,
     * hoy solo el Auditor vía `torre_config.auditor_activo`). Un motor sin cadencia numérica fija
     * (gated/sin agendar: `cadencia_horas === null` en config) usa `max_horas` como su umbral
     * configurable en vez de un múltiplo de cadencia — ver `iconoSemaforo()`.
     *
     * Suma 2 filas DERIVADAS que no tienen cron propio (documentado en config/circuito.php: corren
     * dentro de CADA vuelta del scheduler, sin throttle) — Jarvis (reusa el latido de su propia
     * maquinaria, ya calculado por `SupervisorService::estado()`) y Terminales (reusa la salud de
     * slots de `WatchdogService`) — en vez de fabricar un reloj nuevo para cada una.
     *
     * Un motor 🔴 sube al principio de la lista: visible desde la portada sin desplegar nada.
     *
     * @return array<int,array{motor:string,comando:?string,icono:string,estado:string,
     *   ultima_ok_humano:string,ultima_ok_at:?string,ultimo_fallo:?string,cadencia:string,
     *   agendado:?bool,si_no_corre:string}>
     */
    public function semaforoMotores(): array
    {
        $pausado = $this->isPaused();
        $filas = [];

        foreach ($this->latidos() as $p) {
            $cfg = (array) config('circuito.procesos_programados.' . $p['comando'], []);
            $motor = (string) ($cfg['motor'] ?? $p['comando']);
            $cadenciaHoras = $cfg['cadencia_horas'] ?? null;

            // Hoy el ÚNICO motor con flag propio de encendido es el Auditor (`torre_config`).
            $apagadoAProposito = $pausado || ($p['comando'] === 'circuito:auditor' && ! $this->auditorActivo());

            if ($apagadoAProposito) {
                $icono = '⚫';
                $estado = $pausado ? 'circuito_en_pausa' : 'apagado_a_proposito';
            } else {
                $icono = $this->iconoSemaforo($p['nunca'], $p['horas'], $cadenciaHoras, (float) $p['max_horas'], $p['fallo_reciente']);
                $estado = match ($icono) {
                    '🔴' => 'vencido',
                    '🟡' => 'tarde',
                    default => 'vivo',
                };
            }

            $filas[] = [
                'motor'            => $motor,
                'comando'          => $p['comando'],
                'icono'            => $icono,
                'estado'           => $estado,
                'ultima_ok_humano' => $p['nunca'] ? 'nunca ha corrido' : Carbon::parse($p['at'])->diffForHumans(),
                'ultima_ok_at'     => $p['at'],
                'ultimo_fallo'     => $p['ultimo_fallo']['error'] ?? null,
                'cadencia'         => $p['cadencia'] !== '' ? $p['cadencia'] : '—',
                'agendado'         => $p['agendado'],
                'si_no_corre'      => $p['si_no_corre'],
                // #947 — Procedencia (Regla 3 de la épica #874): de dónde sale cada dato de la fila,
                // para que "confía en el semáforo" no dependa de leer el código.
                'procedencia'      => [
                    'ultima_ok_fuente'  => 'settings."' . self::beatKey($p['comando'], $cfg)
                        . '" (latido) · tabla circuito_motor_pulsos (historial por corrida, #875)',
                    'cadencia_clave'    => "config/circuito.php → procesos_programados.\"{$p['comando']}\".cadencia_horas",
                    'comando_correr'    => "php artisan {$p['comando']}",
                ],
            ];
        }

        // Jarvis — sin cron propio (ver bloque de comentario en config/circuito.php). Su salud real
        // ya la deriva SupervisorService de la MISMA maquinaria (scheduler + watchdog).
        $sup = app(SupervisorService::class)->estado(0);
        $filas[] = $this->filaSemaforoDerivada(
            motor: 'JARVIS',
            vivo: (bool) $sup['activo'],
            latidoSecs: $sup['latido_secs'],
            pausado: $pausado,
            cadencia: 'cada vuelta del scheduler (sin cron propio)',
            siNoCorre: 'nadie arbitra colisiones entre terminales ni decide items con brief ya '
                . 'respondido: la bandeja se detiene aunque el scheduler siga vivo',
            procedenciaFuente: 'SupervisorService::estado() — deriva su latido de la MISMA maquinaria '
                . 'del scheduler/watchdog, no tiene cron ni comando propio.',
        );

        // Terminales — salud real de los slots de trabajo (WatchdogService), no solo "el scheduler
        // late": un scheduler vivo con slots colgados igual deja de mover trabajo.
        $wd = app(WatchdogService::class)->estado();
        $slots = $wd['slots'] ?? [];
        $caidos = collect($slots)->filter(fn ($s) => ($s['estado'] ?? null) === 'caido')->count();
        $filas[] = $this->filaSemaforoDerivada(
            motor: 'Terminales',
            vivo: ($wd['scheduler_vivo'] ?? false) && $caidos === 0,
            latidoSecs: $this->schedulerBeatSecs(),
            pausado: $pausado,
            cadencia: 'cada minuto (late junto con el scheduler)',
            siNoCorre: $caidos > 0
                ? "{$caidos} de " . count($slots) . ' terminal(es) caída(s): esos cupos no jalan trabajo de la cola'
                : 'nadie ejecuta items de la Hoja de Ruta: el trabajo se acumula sin avanzar',
            procedenciaFuente: 'WatchdogService::estado() — salud de los slots de trabajo (worker_sid), '
                . 'no tiene cron ni comando propio.',
        );

        // #946 — un motor roto sube al principio: visible desde la portada sin desplegar nada.
        $rango = ['🔴' => 0, '🟡' => 1, '🟢' => 2, '⚫' => 3];
        usort($filas, fn ($a, $b) => ($rango[$a['icono']] ?? 9) <=> ($rango[$b['icono']] ?? 9));

        return array_values($filas);
    }

    /**
     * #947 (Fase 1c, hija de #875/#946) — "Ver último error" de un motor: mensaje completo (hasta
     * 2000 chars, ya truncado al guardarlo en #875), cuándo, y cuántas veces se repitió SEGUIDO
     * (la racha de fallos consecutivos contando desde la corrida más reciente hacia atrás, hasta el
     * primer éxito). Lee `circuito_motor_pulsos` (#875) — el historial por corrida, no el latido.
     * Solo lectura; no cambia el comportamiento de ningún motor.
     */
    public function detalleFallo(string $comando): array
    {
        if (! is_array(config('circuito.procesos_programados.' . $comando))) {
            return ['ok' => false, 'motivo' => 'comando_no_vigilado'];
        }

        $pulsos = \App\Modules\Addons\Roadmap\Models\CircuitoMotorPulso::where('motor', $comando)
            ->orderByDesc('inicio_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['inicio_at', 'ok', 'mensaje', 'duracion_ms']);

        if ($pulsos->isEmpty()) {
            return ['ok' => true, 'sin_historial' => true, 'ultimo_fallo' => null, 'repeticiones' => 0];
        }

        $ultimoFallo = $pulsos->firstWhere('ok', false);
        if (! $ultimoFallo) {
            return ['ok' => true, 'sin_fallo_reciente' => true, 'ultimo_fallo' => null, 'repeticiones' => 0];
        }

        // Racha: fallos consecutivos desde el más reciente hacia atrás, hasta el primer éxito (o el
        // límite de la muestra). Si la corrida más reciente ya fue exitosa, la racha ACTUAL es 0
        // aunque exista un fallo más viejo en la muestra (por eso se sigue mostrando ese fallo: es
        // el último que hubo, no que el motor esté fallando ahora).
        $repeticiones = 0;
        $desde = null;
        foreach ($pulsos as $p) {
            if (! $p->ok) {
                $repeticiones++;
                $desde = $p->inicio_at;
            } else {
                break;
            }
        }

        return [
            'ok'              => true,
            'ultimo_fallo'    => [
                'ts'          => $ultimoFallo->inicio_at?->toDateTimeString(),
                'mensaje'     => $ultimoFallo->mensaje,
                'duracion_ms' => $ultimoFallo->duracion_ms,
            ],
            'repeticiones'    => $repeticiones,
            'racha_desde'     => $desde?->toDateTimeString(),
            'muestra_limitada' => $pulsos->count() >= 50,
        ];
    }

    /** #946 — ¿el Auditor está encendido? Único motor con flag propio de encendido (torre_config). */
    private function auditorActivo(): bool
    {
        try {
            return (bool) app(\App\Modules\Addons\Roadmap\Services\TorreConfigService::class)->get()->auditor_activo;
        } catch (\Throwable) {
            return true;   // sin dato, no lo pintamos apagado por un fallo de lectura ajeno
        }
    }

    /**
     * #946 — icono por RATIO contra la cadencia esperada (🟡 a 2×, 🔴 a 3×). Si el motor no tiene
     * cadencia numérica fija (`cadenciaHoras === null`, gated/sin agendar), usa `maxHoras` —el
     * mismo umbral que ya define su "vencido" en `latidos()`— como el umbral configurable: 🔴 a
     * partir de `maxHoras`, 🟡 desde 2/3 de `maxHoras`.
     */
    private function iconoSemaforo(bool $nunca, ?float $horas, ?float $cadenciaHoras, float $maxHoras, bool $falloReciente): string
    {
        if ($nunca) {
            return '🔴';
        }
        if ($falloReciente) {
            return '🔴';
        }

        $horas = (float) $horas;

        if ($cadenciaHoras !== null && $cadenciaHoras > 0) {
            if ($horas > 3 * $cadenciaHoras) {
                return '🔴';
            }
            if ($horas > 2 * $cadenciaHoras) {
                return '🟡';
            }

            return '🟢';
        }

        if ($maxHoras <= 0) {
            return '🟢';
        }
        if ($horas > $maxHoras) {
            return '🔴';
        }
        if ($horas > $maxHoras * (2 / 3)) {
            return '🟡';
        }

        return '🟢';
    }

    /** #946 — fila del semáforo para un motor DERIVADO (Jarvis/Terminales): sin cron propio, su
     *  señal es booleana (vivo/no vivo), no un ratio de cadencia. */
    private function filaSemaforoDerivada(
        string $motor,
        bool $vivo,
        ?int $latidoSecs,
        bool $pausado,
        string $cadencia,
        string $siNoCorre,
        string $procedenciaFuente = '',
    ): array {
        $icono = $pausado ? '⚫' : ($vivo ? '🟢' : '🔴');

        return [
            'motor'            => $motor,
            'comando'          => null,
            'icono'            => $icono,
            'estado'           => $pausado ? 'circuito_en_pausa' : ($vivo ? 'vivo' : 'vencido'),
            'ultima_ok_humano' => $latidoSecs === null ? 'nunca ha corrido' : now()->subSeconds($latidoSecs)->diffForHumans(),
            'ultima_ok_at'     => $latidoSecs === null ? null : now()->subSeconds($latidoSecs)->toDateTimeString(),
            'ultimo_fallo'     => null,
            'cadencia'         => $cadencia,
            'agendado'         => null,
            'si_no_corre'      => $siNoCorre,
            // #947 — Procedencia: no tiene comando propio (motor derivado de otro servicio), así que
            // no hay "Correr ahora" que ofrecer aquí.
            'procedencia'      => [
                'ultima_ok_fuente' => $procedenciaFuente,
                'cadencia_clave'   => null,
                'comando_correr'   => null,
            ],
        ];
    }

    private function clampRate(float $rate): float
    {
        return max(0.5, min(2.0, $rate));
    }

    private function putSetting(string $key, string $val): void
    {
        if (DB::table('settings')->where('key', $key)->exists()) {
            DB::table('settings')->where('key', $key)->update(['value' => $val, 'updated_at' => now()]);
        } else {
            DB::table('settings')->insert(['key' => $key, 'value' => $val, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Panorama global (todos los items, sin filtrar) — conteos por estado y por nivel. */
    public function resumen(): array
    {
        return [
            'total'      => RoadmapItem::count(),
            'por_estado' => RoadmapItem::selectRaw('estado_aprobacion, count(*) as n')
                ->groupBy('estado_aprobacion')->pluck('n', 'estado_aprobacion'),
            'por_nivel'  => RoadmapItem::selectRaw("COALESCE(nivel_riesgo,'sin_clasificar') as nivel, count(*) as n")
                ->groupBy('nivel')->pluck('n', 'nivel'),
            // Kill switch expuesto al ejecutor (la Rutina lo consulta para no ejecutar en pausa).
            'circuito_pausado' => $this->isPaused(),
        ];
    }

    /**
     * Lista compacta filtrada + paginada. Devuelve el NÚCLEO del payload
     * (generated_at, filtros_aplicados, meta, items) — sin resumen/leyenda/ayuda,
     * que son presentación del llamador.
     */
    public function listar(?string $estado, ?string $nivel, ?string $modulo, int $page, int $perPage): array
    {
        $q = RoadmapItem::query();
        if ($estado) $q->where('estado_aprobacion', $estado);
        if ($nivel)  $q->where('nivel_riesgo', $nivel);
        if ($modulo !== null && $modulo !== '') $q->where('modulo', 'like', '%' . $modulo . '%');

        $total = (clone $q)->count();
        // #878 — `compact()` sólo usa campos ligeros; pedir las 96 columnas para ordenarlas
        // reventaba MySQL con 1038 (Out of sort memory) y tumbaba este endpoint entero.
        $items = $q->ordered()->forPage($page, $perPage)
            ->get(RoadmapItem::COLUMNAS_COMPACT)
            ->map(fn ($i) => $this->compact($i));

        $filtros = array_filter(
            ['estado' => $estado, 'nivel' => $nivel, 'modulo' => $modulo],
            fn ($val) => $val !== null && $val !== ''
        );

        return [
            'generated_at'      => now()->toIso8601String(),
            'filtros_aplicados' => (object) $filtros,
            'meta'              => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil(($total ?: 0) / $perPage),
                'count'       => $items->count(),
            ],
            'items'             => $items,
        ];
    }

    /** Fila compacta (sin description/prompt) — para listas. El detalle va por serialize(). */
    public function compact(RoadmapItem $i): array
    {
        return [
            'id'                => $i->id,
            'title'             => $i->title,
            'modulo'            => $i->modulo,
            'nivel_riesgo'      => $i->nivel_riesgo,
            'estado_aprobacion' => $i->estado_aprobacion,
            'priority'          => $i->priority,
            'status'            => $i->status,
            'urgente'           => (bool) $i->urgente,
            'estacion'          => $i->estacion,   // #432: intake|bandeja|listo|terminal|integracion|done
            // TORRE V2 — el reparto, visible ya en la LISTA (no hay que abrir item por item para
            // saber qué está en manos de quién).
            'estado_cola'       => $i->estado_cola,
            'terminal_asignada' => $i->worker_sid,
            'item_padre'        => $i->origen_item_id ? (int) $i->origen_item_id : null,
            'consulta_viva'     => $i->tieneConsultaViva(),
        ];
    }

    /** Detalle completo de un item (incluye comentarios_claude + log). */
    public function serialize(RoadmapItem $i): array
    {
        return [
            'id'                 => $i->id,
            'title'              => $i->title,
            'modulo'             => $i->modulo,
            'description'        => $i->description,
            'status'             => $i->status,
            'priority'           => $i->priority,
            'nivel_riesgo'       => $i->nivel_riesgo,
            'estado_aprobacion'  => $i->estado_aprobacion,
            'target_version'     => $i->target_version,
            'prompt_para_claude' => $i->prompt,
            'comentarios_claude' => $i->comentarios_claude,
            // TORRE V2 — estado de COLA y ASIGNACIÓN. Sin esto, quien define el trabajo desde
            // fuera no puede saber si un item ya está en manos de una terminal, terminado y
            // esperando merge, o parado esperando a Irving — y volvía a preguntar por chat.
            'estado_cola'        => $i->estado_cola,
            'terminal_asignada'  => $i->worker_sid,
            'asignado_at'        => optional($i->claimed_at)->toIso8601String(),
            'rama'               => $i->branch,
            'merge_commit'       => $i->merge_commit,
            'item_padre'         => $i->origen_item_id ? (int) $i->origen_item_id : null,
            'eta_minutos'        => $i->eta_minutos !== null ? (int) $i->eta_minutos : null,
            // Consulta viva a Jarvis (la terminal preguntó y espera respuesta).
            'consulta_supervisor' => $i->tieneConsultaViva() ? [
                'pregunta' => $i->consulta_supervisor,
                'terminal' => $i->consulta_supervisor_sid,
                'at'       => optional($i->consulta_supervisor_at)->toIso8601String(),
                'opciones' => $i->consulta_opciones,
            ] : null,
            'subtasks'           => $i->subtasks,
            'log'                => $i->log,
            'started_at'         => optional($i->started_at)->toIso8601String(),
            'completed_at'       => optional($i->completed_at)->toIso8601String(),
            'revisado_at'        => optional($i->revisado_at)->toIso8601String(),
            'aprobado_por'       => $i->aprobado_por,
            'created_at'         => optional($i->created_at)->toIso8601String(),
            'updated_at'         => optional($i->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Allowlist de validación de la escritura acotada (los ÚNICOS 3 campos escribibles).
     * Fuente única compartida por la API externa y el conector MCP.
     */
    public function writeFieldRules(): array
    {
        return [
            'estado_aprobacion'  => ['sometimes', 'string', 'in:' . implode(',', RoadmapItem::ESTADOS_APROBACION)],
            'nivel_riesgo'       => ['sometimes', 'nullable', 'string', 'in:' . implode(',', RoadmapItem::NIVELES_RIESGO)],
            'comentarios_claude' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Candados server-side que ninguna vía externa puede saltar (hallazgos del auditor).
     * Devuelve el motivo del rechazo (string) o null si pasa.
     *
     *  a) NO degradar nivel_riesgo hacia menos restrictivo (A<B<C): solo endurecer.
     *  b) SOLO un item nivel A puede quedar 'aprobado_claude'. B, C y sin clasificar
     *     topan en 'requiere_irving'. Se evalúa contra el nivel EFECTIVO tras el update.
     *  c) (#260) El nivel A que habilita 'aprobado_claude' debe haber sido fijado por un
     *     actor INTERNO (Claude Code / Irving). Si ESTA misma escritura externa es la que
     *     sube/fija nivel_riesgo (aunque termine en A), o el item quedó en A por una subida
     *     externa previa (nivel_riesgo_origen='externo'), el máximo alcanzable es
     *     'requiere_irving' — el mismo actor externo no puede fijar el riesgo Y la
     *     aprobación de ejecución automática en el mismo lazo.
     */
    public function guard(RoadmapItem $item, array $data): ?string
    {
        // (0.a) aprobado_irving es aprobación HUMANA: SOLO el endpoint autenticado de Irving
        // (Torre de control) puede fijarlo. El ejecutor (vía externa/MCP) JAMÁS puede
        // otorgarse a sí mismo la aprobación humana.
        if (($data['estado_aprobacion'] ?? null) === 'aprobado_irving') {
            return "La vía externa/MCP no puede fijar 'aprobado_irving': la aprobación humana "
                . 'es exclusiva de Irving desde la Torre de control.';
        }

        // (0.a-bis) #338: 'aprobado_revisor' lo otorga SOLO el revisor interno on-box
        // (RevisorService). La vía externa/MCP no puede fijarlo (ni Cowork ni nadie de fuera).
        if (($data['estado_aprobacion'] ?? null) === 'aprobado_revisor') {
            return "La vía externa/MCP no puede fijar 'aprobado_revisor': lo otorga exclusivamente "
                . 'el revisor adversarial interno on-box (#338).';
        }

        // (0.b) KILL SWITCH: en pausa, NADIE puede aprobar/ejecutar (aprobado_claude / aprobado_revisor).
        // Leer y reportar (comentarios_claude, requiere_irving, etc.) sigue permitido.
        if (in_array($data['estado_aprobacion'] ?? null, ['aprobado_claude', 'aprobado_revisor'], true) && $this->isPaused()) {
            return 'Circuito en PAUSA (kill switch activo): no se puede aprobar ni ejecutar '
                . '(aprobado_claude). Se permite leer y reportar (comentarios_claude, requiere_irving). '
                . 'Reactiva el circuito desde la Torre de control para volver a ejecutar.';
        }

        $rank = fn (?string $n): int => match ($n) {
            'A'     => 0,
            'B'     => 1,
            'C'     => 2,
            default => -1, // sin clasificar = lo menos restrictivo
        };

        // (a) No degradar el nivel de riesgo.
        if (array_key_exists('nivel_riesgo', $data) && $rank($data['nivel_riesgo']) < $rank($item->nivel_riesgo)) {
            return "La vía externa no puede degradar nivel_riesgo ({$item->nivel_riesgo} → "
                . ($data['nivel_riesgo'] ?? 'null') . "); solo puede endurecer (A→B→C).";
        }

        // Nivel efectivo tras aplicar este update.
        $nivelEfectivo = array_key_exists('nivel_riesgo', $data) ? $data['nivel_riesgo'] : $item->nivel_riesgo;

        // (b) Solo un item nivel A puede quedar aprobado_claude por esta vía.
        if (($data['estado_aprobacion'] ?? null) === 'aprobado_claude' && $nivelEfectivo !== 'A') {
            $n = $nivelEfectivo ?? 'sin clasificar';
            return "Solo un item nivel A puede quedar 'aprobado_claude' por la vía externa "
                . "(este es nivel {$n}); para B/C el máximo es 'requiere_irving'.";
        }

        // (c) #260: el A que habilita aprobado_claude debe venir de origen interno. Si esta
        // MISMA escritura toca nivel_riesgo, applyWrite() la va a sellar como 'externo' →
        // origen efectivo = externo. Si no la toca, el origen efectivo es el ya persistido.
        if (($data['estado_aprobacion'] ?? null) === 'aprobado_claude') {
            $origenEfectivo = array_key_exists('nivel_riesgo', $data) ? 'externo' : ($item->nivel_riesgo_origen ?? 'interno');
            if ($origenEfectivo !== 'interno') {
                return "El nivel A de este item fue fijado/subido por la vía externa: no puede "
                    . "quedar 'aprobado_claude' en el mismo lazo (el mismo actor no puede fijar "
                    . "nivel_riesgo Y la aprobación). Máximo alcanzable: 'requiere_irving'.";
            }
        }

        return null;
    }

    /**
     * Aplica la escritura acotada: sella revisado_at + aprobado_por (autor según la vía)
     * y persiste. Devuelve el item fresco. El llamador YA validó (writeFieldRules) y pasó
     * el guard(). #260: toda llamada a applyWrite() viene de una vía EXTERNA (Cowork/MCP,
     * únicos consumidores) → si toca nivel_riesgo, sella nivel_riesgo_origen='externo'
     * para que el guard() lo detecte en escrituras futuras.
     */
    public function applyWrite(RoadmapItem $item, array $data, string $actor): RoadmapItem
    {
        $data['revisado_at']  = now();
        $data['aprobado_por'] = $actor;

        if (array_key_exists('nivel_riesgo', $data)) {
            $data['nivel_riesgo_origen'] = 'externo';
        }

        $item->update($data);

        return $item->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ESTADO EN VIVO DE LA VUELTA (#335) — heartbeat + log espejeado por BD.
    // Las escrituras (liveStart/liveBeat/liveEnd) las llama el wrapper como meganet.
    // Las lecturas (liveState/liveLogTail) las llama la Torre como www-data.
    // ─────────────────────────────────────────────────────────────────────────

    /** Lee el JSON crudo de estado en vivo de UNA sesión (o null si no hay). */
    private function readLive(string $sid): ?array
    {
        $raw = DB::table('settings')->where('key', self::LIVE_PREFIX . $sid)->value('value');
        if (! $raw) {
            return null;
        }
        $d = json_decode((string) $raw, true);

        return is_array($d) ? $d : null;
    }

    private function writeLive(string $sid, array $d): void
    {
        $this->putSetting(self::LIVE_PREFIX . $sid, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Todas las sesiones live vivas o recién terminadas: [sid => data]. Descarta las TERMINADAS
     * cuyo último latido excede LIVE_ENDED_RETAIN_SEG (ya no aportan al visor). Solo lee filas
     * `circuito_live:<sid>` (ignora la llave legacy `circuito_live` sin sufijo).
     */
    /**
     * IDs de items que AHORA MISMO toca alguna terminal viva (current_item de cada sesión live).
     * Fuente única para excluirlos del backlog de la Hoja de ruta (pipeline por estado).
     */
    public function idsEnCurso(): array
    {
        $ids = [];
        foreach ($this->allLiveSessions() as $d) {
            $cur = $d['current_item'] ?? null;
            if ($cur) {
                $ids[] = (int) $cur;
            }
        }

        return array_values(array_unique($ids));
    }

    private function allLiveSessions(): array
    {
        $rows = DB::table('settings')
            ->where('key', 'like', self::LIVE_PREFIX . '%')
            ->get(['key', 'value']);

        $now = time();
        $out = [];
        foreach ($rows as $row) {
            $sid = substr($row->key, strlen(self::LIVE_PREFIX));
            if ($sid === '') {
                continue;
            }
            $d = json_decode((string) $row->value, true);
            if (! is_array($d) || empty($d['started_at'])) {
                continue;
            }
            $finished = (bool) ($d['finished'] ?? false);
            $hb       = (int) ($d['heartbeat_at'] ?? 0);
            if ($finished && ($now - $hb) > self::LIVE_ENDED_RETAIN_SEG) {
                continue; // terminada hace rato → fuera del visor
            }
            $out[$sid] = $d;
        }

        return $out;
    }

    /** Marca el ARRANQUE de una vuelta de la sesión $sid (lo llama el wrapper como meganet). */
    public function liveStart(string $sid, string $logPath): void
    {
        // Transición #334: limpia la llave legacy `circuito_live` (blob único sin sufijo) la
        // primera vez que arranca una sesión con sufijo, para que no quede colgada en el visor.
        DB::table('settings')->where('key', self::LIVE_KEY)->delete();

        $now  = time();
        $prev = $this->readLive($sid) ?? [];
        $this->writeLive($sid, [
            'started_at'   => $now,
            'heartbeat_at' => $now,
            'finished'     => false,
            'log_path'     => $logPath,
            'log_tail'     => $this->tailFile($logPath),
            'current_item' => null,
            'fases'        => [],   // migas CIRCUITO_FASE de ESTA vuelta (#349)
            'artefactos'   => [],   // rama/commits/archivos best-effort de ESTA vuelta (#349)
            // El resumen (CIRCUITO_META) de la vuelta ANTERIOR se conserva visible hasta que
            // esta vuelta cierre con el suyo (#349: "queda visible hasta la siguiente").
            'meta'         => $prev['meta'] ?? null,
        ]);
    }

    /** Latido: refresca heartbeat + tail del log + #item en curso (best-effort). */
    public function liveBeat(string $sid, ?string $logPath = null): void
    {
        $d = $this->readLive($sid) ?? [];
        $log = $logPath ?: ($d['log_path'] ?? null);

        $d['heartbeat_at'] = time();
        // Un latido SIEMPRE significa "viva": el --watch solo corre entre --start y --end.
        // Forzar finished=false lo hace auto-sanable (si algo dejó finished=true colgado).
        $d['finished']     = false;
        if ($log) {
            $tail = $this->tailFile($log);
            $d['log_path']     = $log;
            $d['log_tail']     = $tail;
            // Fases y artefactos se parsean del log COMPLETO (no del tail) para no perder los
            // pasos tempranos cuando el tail se desplaza. Corre como meganet (lee el archivo). (#349)
            $lines             = $this->readLogLines($log);
            $d['fases']        = $this->parseFases($lines, (array) ($d['fases'] ?? []));
            $d['artefactos']   = $this->parseArtefactos($lines);
            $d['current_item'] = $this->lastFaseItem($d['fases'])
                ?? $this->parseCurrentItem($tail)
                ?? ($d['current_item'] ?? null);
        }

        $this->writeLive($sid, $d);

        // #507 sub-paso 3 — el latido RENUEVA el lease del item que trabaja este worker. Se escribe
        // con un UPDATE crudo a propósito: NO toca `updated_at`, para que "sigo vivo" (claimed_at) y
        // "escribí algo en el item" (updated_at) queden como dos señales independientes — el reaper
        // exige que ambas estén frías antes de dar por muerto al worker.
        // #640 — se acota al item_id ACTUAL de la vuelta (current_item, ya calculado arriba): si un
        // worker_sid quedó con más de un item en_progreso (el bug que #210 documentó: una vuelta
        // previa murió/timeouteó sin soltar el suyo), el latido de la vuelta NUEVA ya NO renueva el
        // claimed_at del huérfano, solo el del item que de verdad se está trabajando ahora. Si aún
        // no hay current_item conocido (arranque, antes de la primera miga CIRCUITO_FASE), se cae al
        // comportamiento previo (renueva todos los en_progreso del sid) para no matar por error un
        // lease legítimo en ese instante inicial.
        $this->renovarLease($sid, $d['current_item'] ?? null);
    }

    /**
     * Renueva el lease del item reclamado por este worker. Best-effort: un fallo aquí no debe tumbar
     * el latido (la Torre seguiría mostrando la vuelta viva; a lo sumo el reaper la libera después).
     *
     * #640: si $currentItemId viene informado, el UPDATE se acota también por `id` — así un
     * worker_sid con más de un item en_progreso (huérfano + el que de verdad se trabaja) no le
     * renueva el lease al huérfano solo por compartir sid y estado.
     */
    public function renovarLease(string $sid, ?int $currentItemId = null): void
    {
        if (! $this->normalizaSid($sid)) {
            return;   // 'main'/'wt-exec' y demás sesiones sin slot no tienen lease que renovar
        }
        try {
            DB::table('roadmap_items')
                ->where('worker_sid', $sid)
                ->where('estado_aprobacion', 'en_progreso')
                ->when($currentItemId !== null, fn ($q) => $q->where('id', $currentItemId))
                ->update(['claimed_at' => now()]);
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /** Marca el FIN de la vuelta de la sesión $sid (deja de reportar "corriendo"). */
    public function liveEnd(string $sid): void
    {
        $d = $this->readLive($sid);
        if (! $d) {
            return;
        }
        $d['finished']     = true;
        $d['heartbeat_at'] = time();
        if (! empty($d['log_path'])) {
            $d['log_tail'] = $this->tailFile($d['log_path']);
            $lines         = $this->readLogLines($d['log_path']);
            $d['fases']    = $this->parseFases($lines, (array) ($d['fases'] ?? []));
            // Captura el resumen de la vuelta (CIRCUITO_META) para dejarlo visible hasta la
            // siguiente vuelta (#349, punto "resumen al terminar").
            $meta = $this->parseMeta($lines);
            if ($meta !== null) {
                $meta['at'] = time();
                $d['meta']  = $meta;
            }
        }
        $this->writeLive($sid, $d);
    }

    /** ¿Hay ALGUNA sesión live corriendo (no terminada)? Barrera anti-solape del picker (#337). */
    public function anyRunning(): bool
    {
        foreach ($this->allLiveSessions() as $d) {
            if (! ($d['finished'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Estado derivado AGREGADO para la Torre (badge "corriendo" + overlap del picker). PURO-LECTURA
     * de BD (no toca archivos) → seguro para www-data. Con #334 puede haber varias sesiones a la
     * vez: `running` = alguna corre; los campos escalares reflejan la sesión "principal" = la
     * corriendo más reciente, o si ninguna corre la última que latió.
     */
    public function liveState(): array
    {
        $now      = time();
        $sessions = $this->allLiveSessions();

        if (! $sessions) {
            return ['running' => false, 'stale' => false, 'started_at' => null];
        }

        // Sesión principal: prioriza las corriendo; desempata por latido más reciente.
        uasort($sessions, function ($a, $b) {
            $ar = ! ($a['finished'] ?? false);
            $br = ! ($b['finished'] ?? false);
            if ($ar !== $br) {
                return $ar ? -1 : 1;
            }

            return (int) ($b['heartbeat_at'] ?? 0) <=> (int) ($a['heartbeat_at'] ?? 0);
        });
        $d = reset($sessions);

        $anyRunning = false;
        foreach ($sessions as $s) {
            if (! ($s['finished'] ?? false)) {
                $anyRunning = true;
                break;
            }
        }

        $finished  = (bool) ($d['finished'] ?? false);
        $hb        = (int) ($d['heartbeat_at'] ?? 0);
        $sinceBeat = max(0, $now - $hb);
        $running   = ! $finished;

        return [
            'running'               => $anyRunning,
            'finished'              => ! $anyRunning,
            'stale'                 => $running && $sinceBeat > self::HEARTBEAT_STALE_SEG,
            'sesiones_activas'      => count($sessions),
            'started_at'            => Carbon::createFromTimestamp((int) $d['started_at'])->toIso8601String(),
            'heartbeat_at'          => $hb ? Carbon::createFromTimestamp($hb)->toIso8601String() : null,
            'segundos_desde_latido' => $sinceBeat,
            'segundos_corriendo'    => $running ? max(0, $now - (int) $d['started_at']) : null,
            'current_item'          => isset($d['current_item']) ? ($d['current_item'] ?: null) : null,
        ];
    }

    /** Tail del log en vivo de la sesión principal (espejo en BD) para "Ver log en vivo". */
    public function liveLogTail(): string
    {
        $sessions = $this->allLiveSessions();
        if (! $sessions) {
            return '';
        }
        // Misma prioridad que liveState: corriendo primero, luego latido más reciente.
        uasort($sessions, function ($a, $b) {
            $ar = ! ($a['finished'] ?? false);
            $br = ! ($b['finished'] ?? false);
            if ($ar !== $br) {
                return $ar ? -1 : 1;
            }

            return (int) ($b['heartbeat_at'] ?? 0) <=> (int) ($a['heartbeat_at'] ?? 0);
        });

        return (string) (reset($sessions)['log_tail'] ?? '');
    }

    /**
     * Estructura para el visor "Trabajando ahora" (#349) y la rejilla de terminales (#350).
     * PURO-LECTURA de BD (no toca archivos) → seguro para www-data. Con #334 (worktrees paralelos)
     * `sesiones` trae UNA entrada por cada `circuito_live:<sid>` viva → los tabs/rejilla se
     * encienden solos. `resumen_ultima_vuelta` = CIRCUITO_META más reciente entre las sesiones.
     */
    public function trabajandoAhora(): array
    {
        // NOTA: no hay early-return con 0 sesiones — aunque el circuito esté ocioso, la rejilla
        // debe mostrar los N slots fijos como "esperando trabajo" (#334 B, relleno abajo).
        $sessions = $this->allLiveSessions();

        $now = time();

        // Títulos de TODOS los ids referenciados (item en curso + fases + resúmenes) en 1 query.
        $ids = [];
        foreach ($sessions as $d) {
            $ids[] = $d['current_item'] ?? null;
            foreach ((array) ($d['fases'] ?? []) as $f) {
                $ids[] = is_array($f) ? ($f['item_id'] ?? null) : null;
            }
            foreach ((array) (($d['meta'] ?? [])['items_tocados'] ?? []) as $mid) {
                $ids[] = $mid;
            }
        }
        $titulos = $this->titulosDe($ids);

        // #546 — reloj en regresión: eta_segundos/trabajo_iniciado_at/eta_metodo del item EN CURSO
        // de cada sesión (sellados al reclamar, ver claimNextParalelo/circuito:scheduler).
        $currentIds = array_map(fn ($d) => $d['current_item'] ?? null, $sessions);
        $etas = $this->etasDe($currentIds);

        $sesiones = [];
        $resumen  = null;
        $resumenAt = -1;
        foreach ($sessions as $sid => $d) {
            $sesiones[] = $this->buildSesion($sid, $d, $now, $titulos, $etas);

            // El resumen mostrado = el CIRCUITO_META más reciente entre las sesiones.
            $meta = is_array($d['meta'] ?? null) ? $d['meta'] : null;
            if ($meta !== null && (int) ($meta['at'] ?? 0) >= $resumenAt) {
                $resumenAt = (int) ($meta['at'] ?? 0);
                $resumen   = [
                    'items_tocados' => array_map(
                        fn ($id) => ['id' => (int) $id, 'title' => $titulos[(int) $id] ?? null],
                        array_values((array) ($meta['items_tocados'] ?? []))
                    ),
                    'n_propuestas' => (int) ($meta['n_propuestas'] ?? 0),
                    'n_decisiones' => (int) ($meta['n_decisiones'] ?? 0),
                    'ejecuto'      => (bool) ($meta['ejecuto'] ?? false),
                    'resumen'      => (string) ($meta['resumen'] ?? ''),
                    'at'           => ! empty($meta['at']) ? Carbon::createFromTimestamp((int) $meta['at'])->toIso8601String() : null,
                ];
            }
        }

        // 6 TERMINALES PERSISTENTES (#334 B): el equipo tiene N slots fijos (wt-1..wt-N). Rellena los
        // que ahora mismo no tienen sesión live con un placeholder "esperando trabajo" (idle) → la
        // rejilla SIEMPRE muestra los N slots, no desaparecen al quedar ociosos.
        $n = $this->getParalelismo();
        $presentes = [];
        foreach ($sesiones as $s) {
            $presentes[$s['sid']] = true;
        }
        for ($k = 1; $k <= $n; $k++) {
            $sid = "wt-{$k}";
            if (empty($presentes[$sid])) {
                $sesiones[] = $this->buildSesionIdle($sid);
            }
        }

        // Orden estable = por número de slot (posición fija en la rejilla: wt-3 siempre en su celda).
        // Las sesiones legacy sin forma wt-K (p.ej. wt-exec) van al final.
        usort($sesiones, function ($a, $b) {
            $ka = $this->slotNum($a['sid']);
            $kb = $this->slotNum($b['sid']);
            if ($ka !== $kb) {
                return $ka <=> $kb;
            }

            return strcmp((string) $a['sid'], (string) $b['sid']);
        });

        // Nombre del roster por slot (wt-3 → "Tokyo"): la rejilla es un ROSTER del equipo (#334).
        $nombres = $this->nombresWorkers();
        foreach ($sesiones as &$s) {
            $s['nombre'] = $nombres[$s['sid']] ?? $s['sid'];
        }
        unset($s);

        // #889 (Torre fase 5 — Terminales): reclamos huérfanos — items ya `status=done` que siguen
        // reteniendo un `worker_sid` sin más trabajo pendiente (esperan la decisión de Irving). Ese
        // `worker_sid` es lo que hoy deja la terminal "ocupada" sin forma de soltarla desde el
        // front. Se adjunta por sid; el frontend lo pinta en ámbar con el botón "Liberar reclamo".
        $huerfanos = $this->reclamosHuerfanosPorSid();
        foreach ($sesiones as &$s) {
            $s['reclamo_huerfano'] = $huerfanos[$s['sid']] ?? null;
        }
        unset($s);

        return ['sesiones' => $sesiones, 'resumen_ultima_vuelta' => $resumen];
    }

    /**
     * #889 — mapa `worker_sid => {item_id, title, estado_aprobacion}` de items YA terminados
     * (`status=done`) que siguen reteniendo un `worker_sid` con `estado_aprobacion` NO terminal
     * (no `completado`/`cancelado`/`rechazado`). Cada uno de esos `worker_sid` es una terminal
     * reservada sin trabajo real detrás. PURO-LECTURA. Si dos items compartieran el mismo
     * `worker_sid` huérfano (no debería pasar), gana el más reciente (`updated_at`).
     */
    private function reclamosHuerfanosPorSid(): array
    {
        $rows = RoadmapItem::query()
            ->where('status', 'done')
            ->whereNotNull('worker_sid')
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
            ->orderBy('updated_at')
            ->get(['id', 'title', 'worker_sid', 'estado_aprobacion']);

        $mapa = [];
        foreach ($rows as $r) {
            $mapa[$r->worker_sid] = [
                'item_id'           => (int) $r->id,
                'title'             => $r->title,
                'estado_aprobacion' => $r->estado_aprobacion,
            ];
        }

        return $mapa;
    }

    /** Nº de slot de un sid `wt-K` para ordenar (los no-wt-K se mandan al final). #334 B */
    private function slotNum(string $sid): int
    {
        return preg_match('/^wt-(\d+)$/', $sid, $m) ? (int) $m[1] : PHP_INT_MAX;
    }

    /** Placeholder de un slot OCIOSO del equipo — "esperando trabajo" (#334 B). */
    private function buildSesionIdle(string $sid): array
    {
        return [
            'sid'                   => $sid,
            'avatar_url'            => $this->avatarUrlWorker($sid),   // #854
            'item'                  => null,
            'fase_actual'           => null,
            'pasos'                 => [],
            'log_tail'              => '',
            'artefactos'            => [],
            'running'               => false,
            'finished'              => false,
            'idle'                  => true,   // el frontend pinta "esperando trabajo"
            'stale'                 => false,
            'started_at'            => null,
            'heartbeat_at'          => null,
            'segundos_desde_latido' => null,
            'segundos_corriendo'    => null,
            // #546 — slot ocioso: sin item, sin reloj.
            'eta_segundos'          => null,
            'eta_metodo'            => null,
            'trabajo_iniciado_at'   => null,
            'restante_segundos'     => null,
        ];
    }

    /** Construye el objeto-sesión (una terminal del visor #349/#350) a partir del blob live. */
    private function buildSesion(string $sid, array $d, int $now, array $titulos, array $etas = []): array
    {
        $finished  = (bool) ($d['finished'] ?? false);
        $hb        = (int) ($d['heartbeat_at'] ?? 0);
        $sinceBeat = max(0, $now - $hb);
        $running   = ! $finished;

        $fases  = array_values(array_filter((array) ($d['fases'] ?? []), 'is_array'));
        $itemId = isset($d['current_item']) ? ($d['current_item'] ?: null) : null;

        $pasos = array_map(function ($f) use ($titulos) {
            $id = $f['item_id'] ?? null;

            return [
                'fase'    => $f['fase'] ?? null,
                'item_id' => $id,
                'title'   => $id ? ($titulos[$id] ?? null) : null,
                'at'      => ! empty($f['at']) ? Carbon::createFromTimestamp((int) $f['at'])->toIso8601String() : null,
            ];
        }, $fases);

        // #546 — reloj en regresión: restante_segundos se computa SERVER-SIDE en este load; el
        // cliente lo va bajando con su propio tick y se re-sincroniza en cada poll (evita deriva).
        $eta               = $itemId ? ($etas[$itemId] ?? null) : null;
        $etaSegundos       = $eta['eta_segundos'] ?? null;
        $trabajoIniciadoAt = $eta['trabajo_iniciado_at'] ?? null;   // Carbon|null
        $restante          = ($etaSegundos !== null && $trabajoIniciadoAt)
            ? $etaSegundos - $trabajoIniciadoAt->diffInSeconds(now())
            : null;

        return [
            'sid'                   => $sid,
            'avatar_url'            => $this->avatarUrlWorker($sid),   // #854
            'item'                  => $itemId ? ['id' => (int) $itemId, 'title' => $titulos[$itemId] ?? null] : null,
            'fase_actual'           => $fases ? ($fases[count($fases) - 1]['fase'] ?? null) : null,
            'pasos'                 => $pasos,
            'log_tail'              => (string) ($d['log_tail'] ?? ''),
            'artefactos'            => (array) ($d['artefactos'] ?? []),
            'running'               => $running,
            'finished'              => $finished,
            'idle'                  => false,
            'stale'                 => $running && $sinceBeat > self::HEARTBEAT_STALE_SEG,
            'started_at'            => Carbon::createFromTimestamp((int) $d['started_at'])->toIso8601String(),
            'heartbeat_at'          => $hb ? Carbon::createFromTimestamp($hb)->toIso8601String() : null,
            'segundos_desde_latido' => $sinceBeat,
            'segundos_corriendo'    => $running ? max(0, $now - (int) $d['started_at']) : null,
            // #546 — reloj en regresión del item en curso (null si aún no tiene ETA sellado).
            'eta_segundos'          => $etaSegundos,
            'eta_metodo'            => $eta['eta_metodo'] ?? null,
            'trabajo_iniciado_at'   => $trabajoIniciadoAt?->toIso8601String(),
            'restante_segundos'     => $restante,
            // El techo real de la vuelta viaja con el reloj (2026-08-26): el ETA ya sale topado a
            // este número, y tenerlo aquí permite rotular el reloj como lo que es —un límite— sin
            // que nadie tenga que recordar cuánto vale.
            'techo_segundos'        => (int) config('circuito.vuelta_timeout_seg', 600),
        ];
    }

    /** Mapa `id => title` para un set de ids de roadmap_items (una sola query). */
    private function titulosDe(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (! $ids) {
            return [];
        }

        return RoadmapItem::whereIn('id', $ids)->pluck('title', 'id')->toArray();
    }

    /**
     * #546 — Mapa `id => {eta_segundos, trabajo_iniciado_at, eta_metodo}` para un set de items
     * EN CURSO (el reloj en regresión de cada terminal). PURO-LECTURA.
     */
    private function etasDe(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (! $ids) {
            return [];
        }

        return RoadmapItem::whereIn('id', $ids)
            ->get(['id', 'eta_segundos', 'trabajo_iniciado_at', 'eta_metodo'])
            ->keyBy('id')
            ->map(fn (RoadmapItem $i) => [
                'eta_segundos'        => $i->eta_segundos,
                'trabajo_iniciado_at' => $i->trabajo_iniciado_at,   // Carbon|null (cast)
                'eta_metodo'          => $i->eta_metodo,
            ])->all();
    }

    /** #507 sub-paso 3 — ¿el circuito corre en modo CONTINUO (sin rondas)? Flag reversible. */
    public function esContinuo(): bool
    {
        return (bool) config('circuito.continuo', true);
    }

    /**
     * Próxima vuelta estimada a partir del intervalo del cron (config `circuito.interval_min`,
     * ESPEJO del crontab cada-30-min). No controla el cron real; solo informa a la Torre.
     *
     * #507 sub-paso 3 — En modo CONTINUO devuelve null: no hay "próxima vuelta" que anunciar porque
     * no hay rondas. Las terminales jalan trabajo en cuanto quedan libres, así que la pregunta útil
     * dejó de ser "¿cuándo es la próxima vuelta?" y pasó a ser "¿qué está corriendo ahora?" (el
     * panel de Terminales del sub-paso 4). Apagar `circuito.continuo` devuelve la estimación vieja.
     */
    public function proximaVueltaAt(): ?string
    {
        if ($this->esContinuo()) {
            return null;
        }

        $min = (int) config('circuito.interval_min', 30);
        if ($min < 1 || $min > 60) {
            $min = 30;
        }

        $now     = Carbon::now()->second(0);
        $nextMin = (intdiv($now->minute, $min) + 1) * $min;

        $next = $now->copy();
        if ($nextMin >= 60) {
            $next->addHour()->minute($nextMin - 60);
        } else {
            $next->minute($nextMin);
        }

        return $next->toIso8601String();
    }

    /** Últimas N líneas de un archivo de log (logs de vuelta son pequeños, ~pocos KB). */
    private function tailFile(string $path, int $lines = self::LOG_TAIL_LINES): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }
        $f = @file($path, FILE_IGNORE_NEW_LINES);
        if ($f === false) {
            return '';
        }

        return $this->maskSecrets(implode("\n", array_slice($f, -$lines)));
    }

    /**
     * #934 — enmascara valores sensibles ANTES de que el tail del log salga por HTTP hacia el
     * navegador (nunca en el cliente: lo que sale del servidor ya debe ir limpio). Dos capas:
     *   1) Valores REALES de las env sensibles cargadas ahora mismo (KEY/SECRET/TOKEN/PASSWORD/PWD),
     *      buscados literalmente en el texto — cubre credenciales del propio .env que se filtren
     *      a la salida de una vuelta (prompt, error, eco de config).
     *   2) Patrones genéricos `ALGO_KEY=valor` / `Authorization: Bearer xxx` / bloques de llave
     *      privada — cubre secretos de terceros que no viven en nuestro .env.
     * Best-effort (defensa en profundidad), no un parser — nunca debe tronar el tail completo.
     */
    private function maskSecrets(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        foreach ($this->secretEnvValues() as $valor) {
            $text = str_replace($valor, '•••REDACTADO•••', $text);
        }

        $patronesClave = '/\b((?:[A-Z0-9_]*(?:API[_-]?KEY|SECRET|TOKEN|PASSWORD|PWD)[A-Z0-9_]*)\s*[=:]\s*)(["\']?)([^\s"\'\n]{4,})(\2)/i';
        $text = preg_replace($patronesClave, '$1$2•••REDACTADO•••$4', $text) ?? $text;

        $text = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9\-_.~+\/=]{8,}/', '$1 •••REDACTADO•••', $text) ?? $text;

        $text = preg_replace('/-----BEGIN [A-Z ]*PRIVATE KEY-----[\s\S]*?-----END [A-Z ]*PRIVATE KEY-----/', '•••CLAVE PRIVADA REDACTADA•••', $text) ?? $text;

        return $text;
    }

    /**
     * Valores actuales de env sensibles (nombre de variable con pinta de secreto), tal como están
     * cargados en este proceso — no lee `.env` de disco, así que no pelea con `config:cache`.
     */
    private function secretEnvValues(): array
    {
        // getenv() sin argumento lee el entorno real (poblado por putenv() de Dotenv) sin depender
        // de `variables_order`; $_ENV suele venir vacío en FPM aunque el valor sí exista.
        $entorno = array_merge((array) getenv(), $_SERVER, $_ENV);

        $valores = [];
        foreach ($entorno as $clave => $valor) {
            if (! is_string($valor) || ! preg_match('/(KEY|SECRET|TOKEN|PASSWORD|PWD)/i', (string) $clave)) {
                continue;
            }
            // Solo enmascara valores con pinta REAL de secreto: un placeholder débil de dev
            // (ej. `DB_RADIUS_PASSWORD=password`) es una palabra común y NO se enmascara — si no,
            // cualquier mención normal de "password" en el log saldría redactada por error.
            if ($this->pareceSecretoReal($valor)) {
                $valores[] = $valor;
            }
        }

        // Ordena por longitud descendente: si un secreto corto es substring de uno largo, se
        // enmascara primero el largo para no dejar residuos parciales sin redactar.
        usort($valores, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_values(array_unique($valores));
    }

    /** Placeholders débiles conocidos que NO se enmascaran por ser palabras comunes, no secretos. */
    private const PLACEHOLDERS_DEBILES = ['password', 'secret', 'admin', 'changeme', 'test', 'null', 'none', 'default', 'root', ''];

    /** ¿Este valor tiene pinta de secreto real (alta entropía) y no de placeholder débil? */
    private function pareceSecretoReal(string $valor): bool
    {
        if (in_array(mb_strtolower($valor), self::PLACEHOLDERS_DEBILES, true)) {
            return false;
        }

        return mb_strlen($valor) >= 12
            || (mb_strlen($valor) >= 8 && preg_match('/[0-9]/', $valor) && preg_match('/[a-zA-Z]/', $valor))
            || (bool) preg_match('/^[A-Za-z0-9+\/_=-]{10,}$/', $valor);
    }

    /** Extrae el #item más reciente mencionado en el tail (best-effort, para "tocando #NNN"). */
    private function parseCurrentItem(string $tail): ?int
    {
        if (preg_match_all('/#(\d{1,6})\b/', $tail, $m) && ! empty($m[1])) {
            return (int) end($m[1]);
        }

        return null;
    }

    /** Lee TODAS las líneas del log de la vuelta (archivos pequeños). Solo meganet lo puede leer. */
    private function readLogLines(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }
        $f = @file($path, FILE_IGNORE_NEW_LINES);

        return $f === false ? [] : $f;
    }

    /**
     * Parser DETERMINISTA de las migas `CIRCUITO_FASE: <fase> #<id>` (#349). Devuelve la
     * secuencia de fases en orden de aparición; a cada fase NUEVA (no vista en $prev) le sella
     * `at` = ahora (granularidad = intervalo del latido; honesto). El log solo crece, así que
     * el prefijo de $prev es estable → los timestamps ya sellados se conservan por índice.
     * Ignora tokens fuera del enum self::FASES.
     */
    private function parseFases(array $lines, array $prev): array
    {
        $seen = [];
        foreach ($lines as $ln) {
            if (! preg_match('/CIRCUITO_FASE:\s*([a-záéíóúñ]+)\s*(?:#(\d{1,6}))?/iu', $ln, $m)) {
                continue;
            }
            $fase = mb_strtolower($m[1]);
            if (! in_array($fase, self::FASES, true)) {
                continue;
            }
            $seen[] = ['fase' => $fase, 'item_id' => isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null];
        }

        $now = time();
        $out = [];
        foreach ($seen as $i => $ev) {
            $out[] = [
                'fase'    => $ev['fase'],
                'item_id' => $ev['item_id'],
                'at'      => (int) ($prev[$i]['at'] ?? $now),
            ];
        }

        return $out;
    }

    /** Último item_id de la secuencia de fases (para "tocando #NNN" determinista). */
    private function lastFaseItem(array $fases): ?int
    {
        for ($i = count($fases) - 1; $i >= 0; $i--) {
            if (! empty($fases[$i]['item_id'])) {
                return (int) $fases[$i]['item_id'];
            }
        }

        return null;
    }

    /**
     * Artefactos best-effort del log (#349): rama del circuito, commits y archivos mencionados.
     * INFORMATIVO — el log no siempre los expone; nunca es autoritativo.
     */
    private function parseArtefactos(array $lines): array
    {
        $text = implode("\n", $lines);

        preg_match_all('/circuito\/item-[\w.\/-]+/', $text, $mr);
        $ramas = array_values(array_unique($mr[0] ?? []));

        preg_match_all('/\bcommit[a-z]*\s+`?([0-9a-f]{7,40})`?/i', $text, $mc);
        $commits = array_values(array_unique($mc[1] ?? []));

        preg_match_all('/\b[\w][\w.\/-]*\.(?:blade\.php|php|vue|js|json|css|scss|sh)\b/', $text, $mf);
        $archivos = array_values(array_unique($mf[0] ?? []));

        return [
            'rama'     => $ramas ? $ramas[count($ramas) - 1] : null,   // la más reciente
            'commits'  => array_slice($commits, -6),
            'archivos' => array_slice($archivos, 0, 12),
        ];
    }

    /** Extrae el último bloque `CIRCUITO_META: {...}` del log como array (o null). (#349) */
    private function parseMeta(array $lines): ?array
    {
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (preg_match('/CIRCUITO_META:\s*(\{.*\})\s*$/', $lines[$i], $m)) {
                $j = json_decode($m[1], true);

                return is_array($j) ? $j : null;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DISPARO MANUAL / INMEDIATO (#337) — flag en BD + auditoría.
    // La Torre (www-data) SOLICITA; el picker on-box (meganet) CONSUME y lanza vuelta.sh.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Solicita una vuelta inmediata. Decisión de Irving: EN PAUSA se BLOQUEA (no encola).
     * Si ya corre una vuelta, el flag queda pendiente y el picker la dispara al terminar
     * (= "encola", nunca solapa). `origin` = 'boton' | 'urgente'.
     */
    public function requestDisparo(string $by, string $origin = 'boton', ?int $itemId = null): array
    {
        if ($this->isPaused()) {
            return [
                'ok'      => false,
                'motivo'  => 'pausado',
                'mensaje' => 'El circuito está en pausa (kill switch). Reanúdalo para poder ejecutar.',
            ];
        }

        $origin = in_array($origin, ['boton', 'urgente'], true) ? $origin : 'boton';
        $now = now();

        // Un solo flag = debounce natural (N disparos en la ventana → 1 vuelta).
        $this->putSetting(self::DISPARO_KEY, json_encode([
            'requested_at' => $now->timestamp,
            'by'           => $by,
            'origin'       => $origin,
            'item_id'      => $itemId,
        ], JSON_UNESCAPED_UNICODE));

        DB::table('circuito_disparos')->insert([
            'requested_by' => mb_substr($by, 0, 190),
            'origin'       => $origin,
            'item_id'      => $itemId,
            'requested_at' => $now,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $running = (bool) ($this->liveState()['running'] ?? false);

        return [
            'ok'           => true,
            'ya_corriendo' => $running,
            'mensaje'      => $running
                ? 'Ya hay una vuelta corriendo; tu disparo quedó encolado para la siguiente.'
                : 'Disparo encolado; la vuelta arranca en segundos.',
        ];
    }

    /** Lee el flag de disparo pendiente (o null). */
    public function pendingDisparo(): ?array
    {
        $raw = DB::table('settings')->where('key', self::DISPARO_KEY)->value('value');
        if (! $raw) {
            return null;
        }
        $d = json_decode((string) $raw, true);

        return is_array($d) ? $d : null;
    }

    /**
     * El picker CONSUME el flag: lo borra (atómico-suficiente para un único picker) y sella
     * consumed_at en la auditoría pendiente. Devuelve el flag consumido (o null si no había).
     */
    public function consumeDisparo(): ?array
    {
        $flag = $this->pendingDisparo();
        if (! $flag) {
            return null;
        }

        $this->clearDisparo();
        DB::table('circuito_disparos')->whereNull('consumed_at')->update(['consumed_at' => now()]);

        return $flag;
    }

    public function clearDisparo(): void
    {
        DB::table('settings')->where('key', self::DISPARO_KEY)->delete();
    }

    /**
     * Ventana de deshacer 15s (#863): si el flag de disparo pendiente apunta exactamente a este
     * item (y el picker aún no lo consumió), lo limpia para que no se adelante una vuelta. No
     * toca el flag si es de otro item o de un disparo general (`item_id` null) — un "Jalar
     * trabajo ahora" de otro origen no debe cancelarse por deshacer un item ajeno.
     */
    public function cancelDisparoPendiente(int $itemId): bool
    {
        $flag = $this->pendingDisparo();
        if ($flag && (int) ($flag['item_id'] ?? 0) === $itemId) {
            $this->clearDisparo();

            return true;
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COLA DE MERGE (#334 F0-fix) — la Torre (www-data) encola; el runner on-box
    // (meganet, en el checkout PRINCIPAL) ejecuta el merge real. Ver MergeRunner.
    // ─────────────────────────────────────────────────────────────────────────

    /** ¿El toggle de auto-merge está ON? (default auto-merge). */
    public function autoMergeOn(): bool
    {
        return $this->getModoIntegracion() === 'auto-merge';
    }

    /** Encola un merge (idempotente por item). trigger: 'boton' (Irving, autoridad) | 'auto'. */
    public function enqueueMerge(int $itemId, string $by, string $trigger = 'boton'): void
    {
        $cola = $this->mergeQueue();
        foreach ($cola as $e) {
            if ((int) ($e['item_id'] ?? 0) === $itemId) {
                return; // ya encolado
            }
        }
        $cola[] = ['item_id' => $itemId, 'by' => $by, 'trigger' => $trigger, 'at' => time()];
        $this->putSetting(self::MERGE_QUEUE_KEY, json_encode(array_values($cola), JSON_UNESCAPED_UNICODE));
        // Marca "en cola" en el resultado para feedback inmediato en la UI.
        $this->recordMergeResult($itemId, ['estado' => 'en_cola', 'by' => $by, 'trigger' => $trigger, 'at' => time()]);
    }

    /** Cola de merge pendiente (FIFO). */
    public function mergeQueue(): array
    {
        $raw = DB::table('settings')->where('key', self::MERGE_QUEUE_KEY)->value('value');
        $d = $raw ? json_decode((string) $raw, true) : [];

        return is_array($d) ? array_values(array_filter($d, 'is_array')) : [];
    }

    /** Saca el primer merge de la cola (FIFO) y persiste el resto. */
    public function dequeueMerge(): ?array
    {
        $cola = $this->mergeQueue();
        if (! $cola) {
            return null;
        }
        $first = array_shift($cola);
        $this->putSetting(self::MERGE_QUEUE_KEY, json_encode(array_values($cola), JSON_UNESCAPED_UNICODE));

        return $first;
    }

    /** ¿Este item está en la cola de merge? (para la UI). */
    public function isMergeQueued(int $itemId): bool
    {
        foreach ($this->mergeQueue() as $e) {
            if ((int) ($e['item_id'] ?? 0) === $itemId) {
                return true;
            }
        }

        return false;
    }

    /** Guarda el resultado del último intento de merge de un item (para la UI). */
    public function recordMergeResult(int $itemId, array $result): void
    {
        $this->putSetting(self::MERGE_RESULT_PREFIX . $itemId, json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    /** Resultado del último intento de merge de un item (o null). */
    public function mergeResult(int $itemId): ?array
    {
        $raw = DB::table('settings')->where('key', self::MERGE_RESULT_PREFIX . $itemId)->value('value');
        if (! $raw) {
            return null;
        }
        $d = json_decode((string) $raw, true);

        return is_array($d) ? $d : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PARALELISMO (#334 Fase 1) — N sesiones/worktrees a la vez.
    // ─────────────────────────────────────────────────────────────────────────

    public const PARALELISMO_KEY = 'circuito_paralelismo';

    /** N = cuántas sesiones a la vez (setting runtime; default de config). Clamp 1..12. */
    public function getParalelismo(): int
    {
        $v = DB::table('settings')->where('key', self::PARALELISMO_KEY)->value('value');
        $n = $v !== null ? (int) $v : (int) config('circuito.paralelismo', 6);

        return max(1, min(12, $n));
    }

    public function setParalelismo(int $n): void
    {
        $this->putSetting(self::PARALELISMO_KEY, (string) max(1, min(12, $n)));
    }

    /**
     * Máx. builds npm simultáneos (semáforo de `deploy/circuito/npm-build.sh`, #334 Fase 1).
     * #873: única fuente de verdad — antes el script leía la env `CIRCUITO_MAX_BUILDS` directo
     * y `config('circuito.max_builds')` no lo leía nadie (control fantasma). Config-only (a
     * diferencia de `paralelismo`, sin override en `settings`; mismo patrón si algún día hace
     * falta). Clamp 1..12 igual que paralelismo.
     */
    public function maxBuilds(): int
    {
        return max(1, min(12, (int) config('circuito.max_builds', 3)));
    }

    /** Nombres de los workers (override runtime). Persisten y son renombrables por Irving. */
    public const WORKER_NOMBRES_KEY = 'circuito_worker_nombres';

    /**
     * Mapa `wt-K => Nombre` para los N slots. Default de config (circuito.worker_nombres), pisado
     * por los overrides de Irving en settings. Si faltan nombres para algún slot, cae a "wt-K".
     */
    public function nombresWorkers(): array
    {
        $defaults = (array) config('circuito.worker_nombres', []);
        $over = [];
        $raw = DB::table('settings')->where('key', self::WORKER_NOMBRES_KEY)->value('value');
        if ($raw !== null && is_array($d = json_decode((string) $raw, true))) {
            $over = $d;
        }

        $n = $this->getParalelismo();
        $map = [];
        for ($k = 1; $k <= $n; $k++) {
            $sid = "wt-{$k}";
            $nombre = trim((string) ($over[$sid] ?? ($defaults[$k - 1] ?? '')));
            $map[$sid] = $nombre !== '' ? $nombre : $sid;
        }

        return $map;
    }

    /** Nombre de un slot concreto (o el propio sid si no hay). */
    public function nombreWorker(?string $sid): ?string
    {
        $sid = trim((string) $sid);
        if ($sid === '') {
            return null;
        }

        return $this->nombresWorkers()[$sid] ?? $sid;
    }

    /** Renombra un worker (control de Irving). sid debe ser wt-K; nombre acotado. */
    public function setNombreWorker(string $sid, string $nombre): void
    {
        if (! preg_match('/^wt-\d+$/', $sid)) {
            return;
        }
        $raw = DB::table('settings')->where('key', self::WORKER_NOMBRES_KEY)->value('value');
        $map = ($raw !== null && is_array($d = json_decode((string) $raw, true))) ? $d : [];
        $nombre = mb_substr(trim($nombre), 0, 24);
        if ($nombre === '') {
            unset($map[$sid]);          // vaciar = volver al default
        } else {
            $map[$sid] = $nombre;
        }
        $this->putSetting(self::WORKER_NOMBRES_KEY, json_encode($map, JSON_UNESCAPED_UNICODE));
    }

    /**
     * #854 — Avatares por terminal (supervisor incluido). Mismo patrón que WORKER_NOMBRES_KEY:
     * mapa `{sid: ruta_relativa}` en `settings`, NO una columna en una "tabla de terminales" —
     * esa tabla no existe (los slots wt-K son virtuales, derivados de `getParalelismo()`, no filas
     * de BD). Decisión registrada en `circuito:reportar --tipo=decision` del item #854.
     * **Ratificado por Irving (item #931, 2026-08-20):** Opción 1 — mapa JSON en `settings` queda
     * como solución permanente (no se crea `roadmap_terminales`). Revisar de nuevo solo si aparece
     * un 2º/3er campo de personalización por slot (regla "tres golpes").
     * `sid` acepta `wt-K` o el literal `supervisor`. La ruta es relativa a `storage/app/public/`
     * (ej. `terminales/{uuid}.webp`); quien la sirve antepone el prefijo `/storage/`.
     */
    public const WORKER_AVATARS_KEY = 'circuito_worker_avatars';

    public function avatarsWorkers(): array
    {
        $raw = DB::table('settings')->where('key', self::WORKER_AVATARS_KEY)->value('value');

        return ($raw !== null && is_array($d = json_decode((string) $raw, true))) ? $d : [];
    }

    /** Ruta relativa del avatar de un slot (o null si no tiene). */
    public function avatarWorker(?string $sid): ?string
    {
        $sid = trim((string) $sid);
        if ($sid === '') {
            return null;
        }

        return $this->avatarsWorkers()[$sid] ?? null;
    }

    /** Fija (o limpia con null) la ruta del avatar de un slot. sid debe ser wt-K o "supervisor". */
    public function setAvatarWorker(string $sid, ?string $path): void
    {
        if (! preg_match('/^(wt-\d+|supervisor)$/', $sid)) {
            return;
        }
        $map = $this->avatarsWorkers();
        if ($path === null || $path === '') {
            unset($map[$sid]);
        } else {
            $map[$sid] = $path;
        }
        $this->putSetting(self::WORKER_AVATARS_KEY, json_encode($map, JSON_UNESCAPED_UNICODE));
    }

    /** URL pública del avatar de un slot (o null si no tiene) — para el payload del poll de 3s. */
    public function avatarUrlWorker(?string $sid): ?string
    {
        $path = $this->avatarWorker($sid);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** Segundos desde el último latido del scheduler (cron), o null si nunca latió. */
    public function schedulerBeatSecs(): ?int
    {
        $v = DB::table('settings')->where('key', 'circuito_scheduler_beat')->value('value');

        return $v !== null ? max(0, time() - (int) $v) : null;
    }

    /**
     * Reclama ATÓMICAMENTE el siguiente item elegible para un worker de pool continuo (#334 F1):
     * el primer ejecutable módulo-disjunto de lo que ya está en vuelo. Devuelve el id reclamado
     * (estado → en_progreso) o null si no hay trabajo / pausado. El llamador debe SERIALIZAR
     * (flock) para que dos workers no tomen items del mismo módulo a la vez.
     */
    /**
     * FASE 2A.5 — SEAM del candado atómico: las condiciones de elegibilidad que el UPDATE de
     * `claimNextParalelo()` re-verifica entre el SELECT y la escritura.
     *
     * Es un método aparte (y público) a propósito: así el test de coherencia puede compilar ESTE
     * camino y el del scope por separado y comparar el SQL resultante. Si los dos delegaran a la
     * misma línea inline, el test no tendría nada que comparar el día que alguien toque uno solo.
     *
     * Debe quedarse siendo una delegación a `RoadmapItem::sqlElegibleParaPool()`. Si algún día hace
     * falta que el reclamo sea MÁS estricto que el scope, el guard extra va DESPUÉS de la
     * delegación y el test lo dirá.
     */
    public static function guardReclamoAtomico($q): void
    {
        RoadmapItem::sqlElegibleParaPool($q);
    }

    public function claimNextParalelo(?string $workerSid = null): ?int
    {
        if ($this->isPaused()) {
            return null;
        }
        $items = $this->ejecutablesParalelo($this->modulosEnVuelo(), 1);
        if (! $items) {
            return null;
        }
        $id = (int) $items[0]['id'];
        // #507 sub-paso 3 — sella el LEASE al reclamar: a partir de aquí el worker debe renovarlo
        // con su latido (liveBeat) o el reaper libera el item.
        // #546 — arranca el reloj de ETA en el MISMO instante que el lease (mismo UPDATE atómico).
        $eta = $this->estimarEtaTrabajo(
            $items[0]['modulo'] ?? null,
            RoadmapItem::where('id', $id)->value('nivel_riesgo')
        );
        // ENTREGA 1 (opción B) — el OVERRIDE POR ITEM se consume AQUÍ, al reclamar, no al aprobar.
        //
        // La autorización que da Irving es «para este item, ahora». Consumirla al aprobar la
        // quemaría aunque el item nunca llegara a correr (el actor aprueba y algo falla después), y
        // además dejaría el item aprobado-y-nunca-despachable: el gate de nivel de
        // `scopeDespachable` ya no vería el `auto` que lo hacía elegible.
        //
        // `CASE` y no un valor fijo: un override `manual` NO se toca (esos items ni siquiera llegan
        // aquí, pero escribir 'hereda' a ciegas los desarmaría si algún día llegaran).
        $overridePrevio = (string) (RoadmapItem::where('id', $id)->value('automatizacion_override') ?? 'hereda');

        $update = [
            // ⚠️ ORDEN CRÍTICO: `estado_previo_claim` va ANTES de `estado_aprobacion`.
            // MySQL evalúa las asignaciones de un UPDATE de IZQUIERDA A DERECHA, así que si esta
            // línea fuera después, `estado_aprobacion` ya valdría 'en_progreso' y guardaríamos
            // ese valor en vez del que traía el item. Sale en el MISMO UPDATE atómico a propósito:
            // en dos escrituras, una caída entre ambas dejaría un item reclamado sin saber de dónde
            // vino, que es exactamente el agujero que esta columna existe para tapar.
            'estado_previo_claim' => DB::raw('estado_aprobacion'),
            'estado_aprobacion'   => 'en_progreso',
            'claimed_at'          => now(),
            'updated_at'          => now(),
            'trabajo_iniciado_at' => now(),
            'eta_segundos'        => $eta['eta_segundos'],
            'eta_metodo'          => $eta['eta_metodo'],
            'automatizacion_override' => DB::raw(
                "CASE WHEN automatizacion_override = 'auto' THEN 'hereda' ELSE automatizacion_override END"
            ),
        ];
        if ($sid = $this->normalizaSid($workerSid)) {
            $update['worker_sid'] = $sid;   // firma del worker (#334 A)
        }
        $claimed = DB::table('roadmap_items')
            ->where('id', $id)
            // #507 — CANDADO ATÓMICO anti-reclamo de un item parqueado. El candidato ya viene filtrado
            // por `elegibleParaPool`, pero el UPDATE lo re-garantiza: entre el SELECT y el UPDATE el
            // item pudo quedar parqueado (otro worker lo cerró a esperando-merge, o el anti-bucle lo
            // sacó). Sin esto, la carrera devuelve un item que nadie debía tocar.
            //
            // FASE 2A.5 — aplica el MISMO predicado que `scopeElegibleParaPool`, vía la definición
            // única `RoadmapItem::sqlElegibleParaPool()`. NO enumerar banderas a mano aquí: es
            // exactamente cómo este candado se quedó atrás del guard las veces anteriores.
            // Ver `guardReclamoAtomico()` justo abajo (seam del candado de coherencia).
            ->where(fn ($q) => static::guardReclamoAtomico($q))
            ->where(function ($q) {
                $q->whereIn('estado_aprobacion', ['aprobado_claude', 'aprobado_revisor', 'aprobado_irving'])
                    ->orWhere(fn ($x) => $x->where('nivel_riesgo', 'A')->where('estado_aprobacion', 'pendiente_revision'));
            })
            ->update($update);

        if ($claimed !== 1) {
            return null;
        }

        if ($overridePrevio === 'auto') {
            $this->anotarOverrideConsumido($id, $sid ?? null);
        }

        $this->avisarSiTocaProduccion($id, $sid ?? null);

        return $id;
    }

    /**
     * ENTREGA 1 — deja rastro de que se GASTÓ un override por item.
     *
     * El override es de un solo uso y se consume en el UPDATE atómico del reclamo. Sin esta entrada,
     * la excepción desaparecería sin dejar huella: el item pasaría de «autorizado por excepción» a
     * `hereda` y nadie sabría que hubo una. Una excepción que no se ve es un agujero.
     *
     * Falla-segura: cualquier excepción aquí se traga. Un rastro roto no puede tumbar un reclamo.
     */
    private function anotarOverrideConsumido(int $id, ?string $sid): void
    {
        try {
            $item = RoadmapItem::find($id);
            if (! $item) {
                return;
            }

            $log   = $item->log ?: [];
            $log[] = [
                'ts'         => now()->toIso8601String(),
                'por'        => 'circuito:despacho',
                'estado'     => 'en_progreso',
                'decision'   => 'override_consumido',
                'comentario' => 'Este item se despachó por un `automatizacion_override = auto` (excepción '
                    . 'explícita de Irving sobre la política base). El override queda CONSUMIDO: vuelve a '
                    . '`hereda` y la próxima vez seguirá la política vigente.'
                    . ($sid ? " Lo tomó {$sid}." : ''),
            ];
            $item->log = $log;
            $item->save();
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('override-consumido-sin-rastro', ['item' => $id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * FASE 2A.3 §5 — AVISO (no bloqueo) cuando el pool autónomo se lleva un item que referencia
     * producción.
     *
     * Decisión de Irving (2026-08-18): el clasificador nunca frena. Pero con carril autónomo hasta
     * nivel B y seis terminales, prod es lo único que no se deshace con un `git checkout`. Esto no
     * lo impide: lo hace imposible de descubrir tarde. Deja rastro en tres sitios — canal de
     * auditoría, `log` del item (de donde lo leen la Torre y el digest) — y sigue de largo.
     *
     * Falla-segura: cualquier excepción aquí se traga. Un aviso roto no puede tumbar un reclamo.
     */
    private function avisarSiTocaProduccion(int $id, ?string $sid): void
    {
        try {
            $item = RoadmapItem::find($id);
            if (! $item || ! ($senal = $item->tocaProduccion())) {
                return;
            }

            Log::channel('roadmap_externo')->warning('despacho-toca-produccion', [
                'item'   => $id,
                'worker' => $sid,
                'senal'  => $senal,
                'titulo' => mb_substr((string) $item->title, 0, 120),
            ]);

            $log   = $item->log ?: [];
            $log[] = [
                'ts'         => now()->toIso8601String(),
                'por'        => 'circuito:despacho',
                'estado'     => 'en_progreso',
                'decision'   => 'alerta_prod',
                'comentario' => "Este item referencia producción («{$senal}») y lo tomó el pool autónomo"
                    . ($sid ? " en {$sid}" : '') . '. No se bloqueó: queda avisado para revisión.',
                'senal_prod' => $senal,
            ];
            $item->log = $log;
            $item->save();
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('aviso-produccion-fallo', ['item' => $id, 'error' => $e->getMessage()]);
        }
    }

    /** #546 — Estima cuánto tardará un item (mediana histórica módulo+nivel, fallback a bucket). */
    public function estimarEtaTrabajo(?string $modulo, ?string $nivelRiesgo): array
    {
        return app(EstimadorTiempo::class)->estimar($modulo, $nivelRiesgo);
    }

    /** Normaliza un id de worker a la forma `wt-K` (o null si no viene / inválido). #334 A */
    public function normalizaSid(?string $sid): ?string
    {
        $sid = trim((string) $sid);

        return preg_match('/^wt-\d+$/', $sid) ? $sid : null;
    }

    /**
     * Módulos NO-null de items EN VUELO (en_progreso — reclamados por una sesión o por un humano).
     * Se usan como pre-filtro conservador: no paralelizar dos items del mismo módulo (#334).
     */
    /** #432 B2 — sentinel de footprint SIN CLASIFICAR (lo pone el hook #427 al crear sin modulo). */
    public const MODULO_DESCONOCIDO = 'Sin clasificar';

    /** ¿El footprint es DESCONOCIDO? = null / vacío / el sentinel 'Sin clasificar'. */
    private function esFootprintDesconocido(?string $m): bool
    {
        $m = trim((string) $m);

        return $m === '' || strcasecmp($m, self::MODULO_DESCONOCIDO) === 0;
    }

    public function modulosEnVuelo(): array
    {
        return DB::table('roadmap_items')
            ->where('estado_aprobacion', 'en_progreso')
            ->whereNotNull('modulo')
            ->where('modulo', '!=', '')
            ->where('modulo', '!=', self::MODULO_DESCONOCIDO)   // #432 B2 — 'Sin clasificar' NO es módulo conocido
            ->pluck('modulo')
            ->unique()->values()->all();
    }

    /**
     * #916 — cuántos items EN VUELO tiene cada módulo (no la lista única de `modulosEnVuelo()`).
     * Es lo que permite topar «N terminales por módulo» de verdad: sin este conteo, tres items del
     * mismo módulo en vuelo se veían como uno solo.
     *
     * @return array<string,int> modulo => items en vuelo
     */
    private function itemsEnVueloPorModulo(): array
    {
        return DB::table('roadmap_items')
            ->where('estado_aprobacion', 'en_progreso')
            ->whereNotNull('modulo')
            ->where('modulo', '!=', '')
            ->where('modulo', '!=', self::MODULO_DESCONOCIDO)
            ->selectRaw('modulo, count(*) as n')
            ->groupBy('modulo')
            ->pluck('n', 'modulo')
            ->all();
    }

    /**
     * #432 B2 — ¿hay un item con footprint DESCONOCIDO (null/vacío/'Sin clasificar') en vuelo? Si lo
     * hay, no podemos garantizar que nada más se pise con él → nadie más se despacha hasta que integre.
     */
    public function desconocidoEnVuelo(): bool
    {
        return DB::table('roadmap_items')
            ->where('estado_aprobacion', 'en_progreso')
            ->where(fn ($q) => $q->whereNull('modulo')->orWhere('modulo', '')->orWhere('modulo', self::MODULO_DESCONOCIDO))
            ->exists();
    }

    /**
     * Items EJECUTABLES por el circuito en paralelo, módulo-disjuntos. Devuelve [{id, modulo}] hasta
     * $limit. Criterio: A `aprobado_claude` (auto) + (si revisor ON) `aprobado_revisor` (B autorizado),
     * excluyendo en_progreso/bloqueados (tomablePorCircuito, #341), urgentes primero (ordered, #337).
     * #432 B2 — REGLA ÚNICA de no-colisión (conservadora):
     *   - mismo `modulo` en vuelo o ya elegido esta ronda → SERIALIZA (se salta).
     *   - footprint DESCONOCIDO (`modulo` null/vacío) → SOLO UNO a la vez: nunca dos desconocidos
     *     en vuelo simultáneamente (no sabemos qué archivos toca ninguno → no se puede garantizar
     *     que sean disjuntos entre sí). Poblar el footprint (B3) reduce esto.
     *   - [PARKED-PROD] (frontera dura de producción) queda FUERA del pool paralelo.
     *
     * #212 (decisión de Irving, q3) — el desconocido es ADITIVO SEGURO por default: se despacha en
     * un slot dedicado aunque haya OTROS módulos conocidos en vuelo (antes exigía la flota
     * completamente quieta → los desconocidos urgentes nunca alcanzaban turno con 6 terminales en
     * pool continuo). La colisión real contra ese trabajo conocido, si la hay, la atrapa después
     * `detectarColisionesEnVuelo()` (diff de archivos real, agnóstico de módulo) y pausa al que
     * reclamó más tarde — este pre-filtro solo necesita seguir evitando DOS desconocidos a la vez.
     */
    public function ejecutablesParalelo(array $excludeModulos, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }
        $unknownEnVuelo = $this->desconocidoEnVuelo();

        $rows = RoadmapItem::query()
            // FASE 2A.3 — CONDICIÓN ÚNICA DE DESPACHO (`RoadmapItem::scopeDespachable`). Antes vivía
            // aquí en línea: quién puede reclamar (A auto, A sin triar, aprobado_irving, B del
            // revisor), el tope de nivel del autopilot, el corte de `status=done` y los frenos de
            // `elegibleParaPool` (#507: rótulos [BLOCKED-]/[PARKED-], espera de merge, anti-bucle).
            //
            // Se movió al modelo porque había un SEGUNDO lector de esa condición que la enumeraba a
            // mano y se quedó corto: el guard anti-re-aprobación de `decidir()` miraba dos banderas
            // y no el master switch, así que aprobar un item bloqueado respondía 200 sin moverlo
            // (#32: 8 aprobaciones mudas; #186: 32). Con una sola definición, cualquier freno nuevo
            // queda cubierto en los dos lados sin tocar ninguno.
            //
            // El pre-filtro de FOOTPRINT no entra aquí a propósito: es una regla de la RONDA
            // (quién colisiona con qué está en vuelo), no una propiedad del item.
            ->despachable()
            // #507 sub-paso 3 — orden de la COLA (urgente → por concluirse/reanudables → antigüedad),
            // no el de la bandeja: una terminal libre debe cerrar lo empezado antes de abrir nuevos.
            ->ordenCola()
            ->limit(200)
            // `urgente` viaja para que la ronda dedicada del footprint desconocido respete la
            // prioridad que `ordenCola()` ya le dio (ver el cierre del barrido).
            ->get(['id', 'modulo', 'urgente']);

        $taken     = array_map('strval', $excludeModulos);

        // #916 (fix) — `modulosEnVuelo()` devuelve módulos ÚNICOS: si 3 items del mismo módulo
        // están en vuelo, llega UNA sola entrada. Contar entradas de `$taken` subestimaba, y con
        // la perilla en 2 habría dejado entrar un CUARTO. Para topes > 1 se expande `$taken` al
        // conteo REAL por módulo (una entrada por item en vuelo) para que el conteo de abajo sea
        // el número de terminales que ese módulo ya tiene.
        // Con la perilla en 1 este bloque NO corre: comportamiento histórico byte-idéntico.
        if (max(1, (int) config('circuito.paralelo_mismo_modulo', 1)) > 1) {
            foreach ($this->itemsEnVueloPorModulo() as $m => $n) {
                $m       = (string) $m;
                $faltan  = (int) $n - count(array_keys($taken, $m, true));
                for ($j = 0; $j < $faltan; $j++) {
                    $taken[] = $m;
                }
            }
        }
        $out       = [];
        $diferido  = (bool) config('circuito.desconocido_diferido', true);
        $candidato = null;   // primer item sin footprint que aparece en la cola

        foreach ($rows as $r) {
            // #432 B2 — 'Sin clasificar'/null/'' = footprint DESCONOCIDO → se normaliza a '' (corre solo).
            $mod = $this->esFootprintDesconocido($r->modulo) ? '' : trim((string) $r->modulo);

            if ($mod === '') {
                // Footprint desconocido → corre SOLO (radio de impacto desconocido). NO se despacha
                // aquí: se recuerda y se resuelve al cerrar el barrido (ver abajo). Antes se tomaba en
                // cuanto ordenaba primero con la flota quieta y se cortaba la ronda con `break`, así que
                // UN item sin clasificar se llevaba las 6 terminales aunque detrás de él hubiera trabajo
                // módulo-disjunto listo para correr en paralelo.
                if ($candidato === null) {
                    $candidato = $r;
                }

                if (! $diferido) {
                    // Escape hatch sin redeploy (`circuito.desconocido_diferido=false`): comportamiento
                    // anterior, el desconocido se lleva la ronda en cuanto puede.
                    if ($unknownEnVuelo || ! empty($taken) || ! empty($out)) {
                        continue;
                    }

                    return [['id' => (int) $r->id, 'modulo' => '']];
                }

                continue;
            }

            // Módulo conocido: serializa contra mismo módulo (en vuelo/elegido) y contra un desconocido
            // EN VUELO (podría pisar cualquier archivo). Ya no existe el caso "desconocido elegido esta
            // ronda": el desconocido nunca se mezcla con trabajo conocido — o va solo, o espera.
            // #916 — el pre-filtro deja de ser booleano: cuenta cuántas terminales tiene ya ese
            // módulo (en vuelo + elegidas esta ronda) y lo compara contra la perilla
            // `circuito.paralelo_mismo_modulo`. Con la perilla en 1 el comportamiento es
            // EXACTAMENTE el histórico (in_array === conteo >= 1), así que subir la perilla es el
            // único cambio de conducta y bajarla a 1 lo revierte sin tocar código.
            $tope = max(1, (int) config('circuito.paralelo_mismo_modulo', 1));
            $yaEnEseModulo = count(array_keys($taken, $mod, true));
            if ($yaEnEseModulo >= $tope || $unknownEnVuelo) {
                continue;
            }
            $out[]   = ['id' => (int) $r->id, 'modulo' => $mod];
            $taken[] = $mod;
            if (count($out) >= $limit) {
                break;
            }
        }

        // SLOT DEDICADO del footprint desconocido (#212 — fix de inanición, decisión Irving q1+q3).
        // Ya NO exige `empty($excludeModulos)` (flota entera quieta): un desconocido puede despacharse
        // en su propio slot aunque OTROS módulos ya estén en vuelo — es aditivo seguro por default,
        // la colisión real la atrapa `detectarColisionesEnVuelo()` post-hoc. La única condición de
        // seguridad que se conserva es `! $unknownEnVuelo` (nunca dos desconocidos a la vez, ver
        // docblock de arriba). Sigue prefiriendo el trabajo módulo-disjunto ya encontrado esta ronda
        // (`empty($out)`) salvo que el candidato sea `urgente`, que conserva la prioridad de
        // `ordenCola()` y desplaza a lo elegido.
        if ($candidato !== null && $diferido && ! $unknownEnVuelo
            && (empty($out) || ! empty($candidato->urgente))) {
            return [['id' => (int) $candidato->id, 'modulo' => '']];
        }

        return $out;
    }

    /**
     * #438 — colisión EN VUELO (footprint que no se conocía al despachar): dos items `en_progreso`
     * en distinto módulo/worktree cuyas ramas terminan tocando el(los) mismo(s) archivo(s). El
     * pre-filtro de `ejecutablesParalelo` (mismo módulo / desconocido) es conservador pero NO puede
     * ver esto — se detecta hasta que ambas ramas existen y tienen commits.
     *
     * Solo lee refs (git diff), NUNCA hace checkout/toca un worktree ajeno: las ramas son visibles
     * desde el checkout PRINCIPAL (comparten el mismo .git). Seguro de correr aunque otras vueltas
     * sigan corriendo en paralelo.
     */

    /**
     * Archivos que cambia una rama vs su punto de partida en main (solo lectura, nunca falla fuerte).
     *
     * #933 — una rama YA fusionada a main tiene `merge-base(main, branch) === branch` (su propia
     * punta es ancestro de main), así que el diff de arriba da SIEMPRE vacío para ramas fusionadas
     * (verificado con git real). Ese es justo el caso de los items ya integrados (candidatos a
     * versión): $mergeCommitSiFusionado permite pedir el diff correcto (merge_commit^1..merge_commit,
     * el mainline justo antes de esa fusión) SIN tocar el comportamiento existente — si se omite,
     * el método es idéntico al de antes (usado por detectarColisionesEnVuelo() con ramas en vuelo).
     */
    public function footprintDeRama(string $branch, ?string $mergeCommitSiFusionado = null): array
    {
        $base = $this->git(['merge-base', 'main', $branch]);
        if (! $base->isSuccessful()) {
            return [];
        }
        $sha = trim($base->getOutput());

        if ($mergeCommitSiFusionado) {
            $tip = $this->git(['rev-parse', $branch]);
            if ($tip->isSuccessful() && trim($tip->getOutput()) === $sha) {
                $diff = $this->git(['diff', '--name-only', $mergeCommitSiFusionado . '^1', $mergeCommitSiFusionado]);
                return $diff->isSuccessful()
                    ? array_values(array_filter(preg_split('/\R/', trim($diff->getOutput()))))
                    : [];
            }
        }

        $diff = $this->git(['diff', '--name-only', $sha, $branch]);
        if (! $diff->isSuccessful()) {
            return [];
        }

        return array_values(array_filter(preg_split('/\R/', trim($diff->getOutput()))));
    }

    /**
     * #913 — archivos MODIFICADOS SIN COMMITEAR en el worktree de una terminal en vuelo
     * (`git status --porcelain`), para complementar el diff ya commiteado de footprintDeRama().
     * Solo lectura: nunca hace checkout/add/commit en el worktree ajeno.
     *
     * Devuelve `null` cuando el árbol NO se pudo leer (worktree inexistente, o `git status` cae a
     * mitad de un add/commit de esa terminal): la llamante DEBE tratar `null` como footprint
     * DESCONOCIDO (colisiona con todo lo que esté en vuelo), NUNCA como "no toca nada" — es el
     * requisito explícito del item, distinto de `esFootprintDesconocido()` (esa es sobre la
     * columna `modulo`; esta es la semántica local de `detectarColisionesEnVuelo()`).
     */
    public function footprintEnVivo(string $sid): ?array
    {
        $sid = trim($sid);
        // Mismo candado que slotLibre(): nunca construir un path con texto arbitrario del item.
        if (! preg_match('/^wt-\d+$/', $sid)) {
            return null;
        }

        $dir = self::RUNTIME_DIR . "/{$sid}";
        if (! is_dir($dir)) {
            return null;
        }

        try {
            $p = new \Symfony\Component\Process\Process(['git', '-C', $dir, 'status', '--porcelain'], $dir);
            $p->setTimeout(15);
            $p->run();
        } catch (\Throwable $e) {
            return null;
        }
        if (! $p->isSuccessful()) {
            return null;
        }

        $archivos = [];
        // rtrim SOLO del final: la primera columna de porcelain suele ser un espacio literal
        // ("modificado sin stage") — un trim() normal se lo come y desalinea el substr(3) de abajo.
        foreach (preg_split('/\R/', rtrim($p->getOutput(), "\r\n")) as $linea) {
            if ($linea === '') {
                continue;
            }
            // Porcelain: 2 columnas de estado + espacio + ruta (índice 3 en adelante). Renombres
            // ('R  origen -> destino') traen DOS rutas en la misma línea — hay que separarlas o el
            // archivo de destino (el que realmente existe ahora) se pierde del footprint.
            $resto = mb_substr($linea, 3);
            if (str_contains($resto, ' -> ')) {
                foreach (explode(' -> ', $resto, 2) as $ruta) {
                    $archivos[] = trim($ruta, '"');
                }
            } else {
                $archivos[] = trim($resto, '"');
            }
        }

        return array_values(array_unique(array_filter($archivos)));
    }

    /**
     * Detecta colisiones nuevas entre items `en_progreso` con rama registrada y AÚN no pausados,
     * y PAUSA (marca `colision_pausada_por`) al perdedor: regla determinística = el que reclamó
     * más tarde (`updated_at` mayor; empate → mayor id). El ganador sigue corriendo normal.
     * NO toca `estado_aprobacion` (el perdedor se autopausa solo al llegar a `circuito:integrar`,
     * su propio checkpoint natural — nunca se mata el proceso en vuelo). Devuelve las colisiones
     * detectadas en esta pasada (para log/depuración).
     */
    public function detectarColisionesEnVuelo(): array
    {
        $rows = DB::table('roadmap_items')
            ->where('estado_aprobacion', 'en_progreso')
            ->whereNotNull('branch')
            ->where('branch', '!=', '')
            ->whereNull('colision_pausada_por')
            ->get(['id', 'branch', 'updated_at', 'worker_sid']);

        if ($rows->count() < 2) {
            return [];
        }

        // #913 — footprint EN VIVO: el commiteado (footprintDeRama) UNIDO a lo sin commitear del
        // worktree de la terminal (footprintEnVivo). Si el árbol en vivo no se pudo leer, el item
        // queda marcado DESCONOCIDO — se trata como si colisionara con TODO lo que esté en vuelo
        // (nunca como "no toca nada"), tal como exige el requisito del item.
        $footprints   = [];
        $desconocidos = [];
        foreach ($rows as $r) {
            $footprints[$r->id] = $this->footprintDeRama($r->branch);

            if (! $r->worker_sid) {
                continue; // sin sid registrado no hay worktree que inspeccionar; queda solo el commiteado
            }
            $enVivo = $this->footprintEnVivo($r->worker_sid);
            if ($enVivo === null) {
                $desconocidos[$r->id] = true;
            } else {
                $footprints[$r->id] = array_values(array_unique(array_merge($footprints[$r->id], $enVivo)));
            }
        }

        $detectadas = [];
        $porId = $rows->keyBy('id');
        foreach ($rows as $a) {
            foreach ($rows as $b) {
                if ($a->id >= $b->id) {
                    continue; // cada par una sola vez
                }
                if (isset($desconocidos[$a->id]) || isset($desconocidos[$b->id])) {
                    // Conservador a propósito (#913): no se pudo leer el árbol en vivo de uno de
                    // los dos → no se puede garantizar que sean disjuntos, se trata como colisión.
                    $comunes = ['(footprint en vivo desconocido)'];
                } else {
                    $comunes = array_values(array_intersect($footprints[$a->id] ?? [], $footprints[$b->id] ?? []));
                }
                if (! $comunes) {
                    continue;
                }

                // Perdedor = el que reclamó más tarde (updated_at mayor); empate → mayor id.
                $ta = Carbon::parse($a->updated_at)->timestamp;
                $tb = Carbon::parse($b->updated_at)->timestamp;
                if ($ta === $tb) {
                    $perdedor = $a->id > $b->id ? $porId[$a->id] : $porId[$b->id];
                    $ganador  = $a->id > $b->id ? $porId[$b->id] : $porId[$a->id];
                } else {
                    $perdedor = $ta > $tb ? $a : $b;
                    $ganador  = $ta > $tb ? $b : $a;
                }

                DB::table('roadmap_items')->where('id', $perdedor->id)->whereNull('colision_pausada_por')->update([
                    'colision_pausada_por' => $ganador->id,
                    'colision_pausada_at'  => now(),
                    'updated_at'           => now(),
                ]);
                $this->appendLog((int) $perdedor->id, 'colision-check', 'colision_pausada', [
                    'ganador' => $ganador->id, 'archivos' => array_slice($comunes, 0, 10),
                ]);

                $detectadas[] = ['ganador' => (int) $ganador->id, 'perdedor' => (int) $perdedor->id, 'archivos' => $comunes];
            }
        }

        return $detectadas;
    }

    /**
     * Reanuda items pausados por colisión cuyo ganador ya no bloquea: completado (integró), o ya
     * no está en vuelo (se canceló/escaló/rechazó — no dejar al perdedor colgado para siempre).
     * Limpia el flag + libera `worker_sid` + regresa `estado_aprobacion` al estado aprobado previo
     * (el circuito lo vuelve a despachar en una vuelta futura; `circuito:rama` rebasa su rama
     * existente sobre el main ya actualizado). Devuelve los ids reanudados.
     */
    public function reanudarColisionesResueltas(): array
    {
        $pausados = DB::table('roadmap_items')->whereNotNull('colision_pausada_por')->get(['id', 'colision_pausada_por', 'nivel_riesgo', 'log']);
        if ($pausados->isEmpty()) {
            return [];
        }

        $ganadorIds = $pausados->pluck('colision_pausada_por')->unique()->all();
        $ganadores = DB::table('roadmap_items')->whereIn('id', $ganadorIds)->get(['id', 'estado_aprobacion'])->keyBy('id');

        $reanudados = [];
        foreach ($pausados as $p) {
            $ganador = $ganadores->get($p->colision_pausada_por);
            $resuelto = ! $ganador || in_array($ganador->estado_aprobacion, ['completado', 'cancelado', 'rechazado'], true);
            if (! $resuelto) {
                continue;
            }

            $item = RoadmapItem::find($p->id);
            if (! $item) {
                continue;
            }

            // #990 — el item pausado ya llegó a un estado terminal por otra vía (p.ej. el
            // MergeRunner lo fusionó en paralelo antes de que se limpiara el candado). No hay
            // nada que reanudar: solo se libera el candado, sin resetear estado_aprobacion
            // (evita reabrir espúreamente un item ya completado/cancelado/rechazado).
            if (in_array($item->estado_aprobacion, ['completado', 'cancelado', 'rechazado'], true)) {
                $item->colision_pausada_por = null;
                $item->colision_pausada_at  = null;
                $item->worker_sid           = null;
                $item->save();
                $this->appendLog((int) $item->id, 'colision-check', 'colision_candado_limpiado_terminal', ['estado' => $item->estado_aprobacion]);
                continue;
            }

            $estadoPrevio = $this->estadoAprobadoPrevio($item);
            $item->colision_pausada_por = null;
            $item->colision_pausada_at  = null;
            $item->worker_sid           = null;
            $item->estado_aprobacion    = $estadoPrevio;
            $item->save();
            $this->appendLog((int) $item->id, 'colision-check', 'colision_reanudado', ['estado' => $estadoPrevio]);
            $reanudados[] = (int) $item->id;
        }

        return $reanudados;
    }

    /**
     * A qué `estado_aprobacion` regresar un item que necesita volver a la cola sin re-triaje (tras
     * colisión resuelta o reap de huérfano, #561): el último `aprobado_*` de su propio log (respeta
     * si fue Irving quien lo aprobó); si no hay rastro, A auto-ejecutable → `aprobado_claude`; si
     * no, a la bandeja de Irving (fail-safe, nunca asumir que un B/C sin rastro es auto-ejecutable).
     */
    public function estadoAprobadoPrevio(RoadmapItem $item): string
    {
        // FUENTE 1 — lo que el item traía cuando lo reclamaron, sellado en el mismo UPDATE atómico
        // del reclamo (2026-08-20). Restaurar > adivinar: las dos fuentes de abajo son heurísticas
        // que pueden ASCENDER un item (un `aprobado_revisor` vuelve como `aprobado_irving` si esa
        // firma quedó antes en su log), y un ascenso silencioso es autorización que nadie dio.
        // Las heurísticas se quedan como respaldo para las filas anteriores a la columna.
        if (in_array($item->estado_previo_claim, ['aprobado_irving', 'aprobado_revisor', 'aprobado_claude'], true)) {
            return $item->estado_previo_claim;
        }

        $log = is_array($item->log) ? $item->log : [];
        foreach (array_reverse($log) as $entry) {
            $estado = $entry['estado'] ?? null;
            if (in_array($estado, ['aprobado_irving', 'aprobado_revisor', 'aprobado_claude'], true)) {
                return $estado;
            }
        }

        return $item->nivel_riesgo === 'A' ? 'aprobado_claude' : 'requiere_irving';
    }

    /**
     * #561 — reap de un item HUÉRFANO (worker muerto/colgado con el item en_progreso): en vez de
     * escalar directo a la bandeja de Irving, lo RE-ENCOLA al estado aprobado que tenía antes de
     * ser reclamado (mismo criterio que la colisión, `estadoAprobadoPrevio`) para que el pool lo
     * vuelva a tomar. Lleva la cuenta de reclamos fallidos en `reap_count`; solo tras superar el
     * tope (`circuito.reaper.max_reintentos`, default 3) escala a `requiere_irving` — así un item
     * genuinamente roto no cicla infinito y sí llega a la bandeja. Usado por `circuito:reap-stuck`
     * y el watchdog (#334/#526), que comparten este único camino.
     *
     * @param  string  $origen  quién detectó el huérfano ('reaper'|'watchdog', para el log/nota)
     * @param  string  $motivo  frase corta de por qué se considera huérfano (para el log/nota)
     * @return array{resultado:string,estado:string,reap_count:int,tope:int}
     */
    /** Runtime del circuito on-box (mismo path que deploy/circuito/vuelta.sh y el scheduler). */
    public const RUNTIME_DIR = '/home/meganet/circuito';

    /**
     * ¿El slot (worktree wt-K) está LIBRE? = su flock no lo tiene una vuelta viva.
     *
     * Es la señal de vida MÁS FUERTE que hay: el flock lo sostiene el proceso de `vuelta.sh`
     * mientras corre y lo suelta el kernel aunque el proceso muera de golpe. No depende de que
     * nadie escriba un timestamp, así que no miente ni por exceso ni por defecto.
     *
     * FAIL-CLOSED: si el archivo no se puede abrir (permisos, runtime ausente), devuelve `false`
     * = "ocupado". Así quien lo use para decidir si mata algo se abstiene ante la duda.
     */
    public function slotLibre(string $sid): bool
    {
        $sid = trim($sid);
        // Solo nombres de slot conocidos: nunca construir un path con texto arbitrario del item.
        if (! preg_match('/^wt-\d+$/', $sid)) {
            return false;
        }

        $path = self::RUNTIME_DIR . "/{$sid}.lock";

        // Sin archivo = ninguna vuelta usó nunca ese slot → está libre. Se abre en 'r' y no en 'c'
        // a propósito: preguntar por un slot no debe CREAR su lock (con 'c' se sembraban archivos
        // vacíos para slots inexistentes).
        if (! is_file($path)) {
            return true;
        }

        $f = @fopen($path, 'r');
        if (! $f) {
            return false;
        }
        $libre = flock($f, LOCK_EX | LOCK_NB);
        if ($libre) {
            flock($f, LOCK_UN);
        }
        fclose($f);

        return $libre;
    }

    /**
     * Archivos que una rama cambió respecto de main (`git diff --name-only main...rama`).
     *
     * Existe para que el auto-merge de Jarvis decida por el DIFF y no por el título: "el item dice
     * que no toca prod" no es verificable; "el diff no toca `deploy/`" sí. Devuelve null si no se
     * puede leer (rama inexistente, git que falla) → el llamador debe tratarlo como fail-closed.
     */
    public function archivosDeRama(string $branch): ?array
    {
        if (! preg_match('#^[\w./-]+$#', $branch)) {
            return null;   // nunca construir un comando con una ref arbitraria
        }

        $p = $this->git(['diff', '--name-only', '-z', 'main...' . $branch]);

        if (! $p->isSuccessful()) {
            return null;
        }

        return array_values(array_filter(explode("\0", $p->getOutput()), fn ($r) => $r !== ''));
    }

    /**
     * COMMITS que la rama tiene por encima de `main`. `null` si no se pudo preguntar.
     *
     * Es el criterio de AVANCE para la reanudación por timeout, y es commits y no archivos a
     * propósito (decisión de Irving, 2026-08-20): un `git diff` puede devolver archivos por ruido
     * del árbol, mientras que un commit es trabajo que alguien decidió guardar. Un item que abrió
     * rama y no commiteó nada NO avanzó, y el anti-quemado debe seguir atrapándolo.
     *
     * Se le pregunta a git y no a `branch_ahead_count`: esa bandera la sella un chequeo periódico y
     * se queda fría — es exactamente cómo el #19 se leyó como "sin trabajo" teniéndolo.
     */
    public function commitsDeRama(string $branch): ?int
    {
        if (! preg_match('#^[\w./-]+$#', $branch)) {
            return null;   // nunca construir un comando con una ref arbitraria
        }

        $p = $this->git(['rev-list', '--count', 'main..' . $branch]);

        if (! $p->isSuccessful()) {
            return null;
        }

        $n = trim($p->getOutput());

        return ctype_digit($n) ? (int) $n : null;
    }

    /**
     * Fecha del commit MÁS RECIENTE de la rama (`git log -1 --format=%aI`). `null` si no se pudo
     * preguntar (rama inexistente, git que falla) — el llamador debe tratarlo como fail-closed.
     *
     * Existe para #746: comparar contra `revisado_at` y detectar si la rama recibió commits
     * DESPUÉS de la última aprobación de Irving, antes de dejar que el auto-merge la integre a
     * ciegas. Mismo patrón de seguridad que `archivosDeRama`/`commitsDeRama` de arriba.
     */
    public function fechaUltimoCommitDeRama(string $branch): ?Carbon
    {
        if (! preg_match('#^[\w./-]+$#', $branch)) {
            return null;   // nunca construir un comando con una ref arbitraria
        }

        $p = $this->git(['log', '-1', '--format=%aI', $branch]);

        if (! $p->isSuccessful()) {
            return null;
        }

        $fecha = trim($p->getOutput());
        if ($fecha === '') {
            return null;
        }

        try {
            return Carbon::parse($fecha);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Contenido concatenado de unos archivos EN la rama (para inspeccionar qué hace una migración
     * antes de auto-mergearla). Devuelve '' si no se puede leer alguno — el llamador decide.
     */
    public function contenidoDeRama(string $branch, array $archivos): string
    {
        if (! preg_match('#^[\w./-]+$#', $branch)) {
            return '';
        }

        $out = '';
        foreach (array_slice($archivos, 0, 20) as $archivo) {
            $p = $this->git(['show', "{$branch}:{$archivo}"]);
            if ($p->isSuccessful()) {
                $out .= $p->getOutput() . "\n";
            }
        }

        return $out;
    }

    public function reencolarHuerfano(RoadmapItem $item, string $origen, string $motivo): array
    {
        $tope = max(1, (int) config('circuito.reaper.max_reintentos', 3));
        $item->reap_count = ((int) $item->reap_count) + 1;
        $item->claimed_at = null;   // el lease muere con el reclamo que lo sostenía
        $item->worker_sid = null;

        if ($item->reap_count > $tope) {
            $item->estado_aprobacion  = 'requiere_irving';
            $item->comentarios_claude = (string) $item->comentarios_claude
                . "\n[{$origen}] {$motivo} → escalado a tu bandeja tras {$item->reap_count} reclamos fallidos (tope {$tope}).";
            $item->save();
            $this->appendLog((int) $item->id, $origen, 'huerfano_escalado', [
                'reap_count' => $item->reap_count, 'tope' => $tope, 'motivo' => $motivo,
            ]);

            return ['resultado' => 'escalado', 'estado' => $item->estado_aprobacion, 'reap_count' => $item->reap_count, 'tope' => $tope];
        }

        $estadoPrevio             = $this->estadoAprobadoPrevio($item);
        $item->estado_aprobacion  = $estadoPrevio;
        $item->comentarios_claude = (string) $item->comentarios_claude
            . "\n[{$origen}] {$motivo} → re-encolado (intento {$item->reap_count}/{$tope}), vuelve a {$estadoPrevio}.";
        $item->save();
        $this->appendLog((int) $item->id, $origen, 'huerfano_reencolado', [
            'reap_count' => $item->reap_count, 'tope' => $tope, 'estado' => $estadoPrevio, 'motivo' => $motivo,
        ]);

        return ['resultado' => 'reencolado', 'estado' => $estadoPrevio, 'reap_count' => $item->reap_count, 'tope' => $tope];
    }

    /** Log entry corta y uniforme para eventos del circuito sobre un item (best-effort, nunca tumba). */
    private function appendLog(int $itemId, string $por, string $evento, array $extra = []): void
    {
        try {
            $item = RoadmapItem::find($itemId);
            if (! $item) {
                return;
            }
            $log = is_array($item->log) ? $item->log : [];
            $log[] = ['ts' => now()->toIso8601String(), 'por' => $por, 'evento' => $evento] + $extra;
            $item->log = $log;
            $item->save();
        } catch (\Throwable $e) {
            // best-effort, nunca tumba al llamador
        }
    }

    private function git(array $args): \Symfony\Component\Process\Process
    {
        $p = new \Symfony\Component\Process\Process(array_merge(['git'], $args), base_path());
        $p->setTimeout(30);
        $p->run();

        return $p;
    }

    /** Último tag publicado (orden semver descendente), o null si no hay ninguno. Solo lectura. */
    private function ultimoTag(): ?string
    {
        $p = $this->git(['tag', '--sort=-version:refname']);
        if (! $p->isSuccessful()) {
            return null;
        }
        $tags = array_values(array_filter(preg_split('/\R/', trim($p->getOutput()))));

        return $tags[0] ?? null;
    }

    /**
     * #933 Fase 2 — candidatos a "armado de versión": items YA integrados a main cuyo `merge_commit`
     * todavía NO forma parte del último tag publicado (lo no elegido en una versión queda disponible
     * para la siguiente, tal cual pidió Irving). Un solo `git log {tag}..HEAD --merges` calcula el
     * set de commits nuevos desde el tag — evita una llamada git por item (cientos de candidatos
     * posibles). Solo lectura.
     *
     * @return \Illuminate\Support\Collection<int,RoadmapItem>
     */
    public function itemsCandidatosVersion(): \Illuminate\Support\Collection
    {
        $items = RoadmapItem::whereNotNull('merge_commit')
            ->where('merge_commit', '!=', '')
            ->orderByDesc('id')
            ->get(['id', 'title', 'branch', 'merge_commit', 'marcado_version', 'origen_item_id', 'modulo']);

        $tag = $this->ultimoTag();
        if ($tag === null) {
            return $items->values(); // sin tag previo: todo lo mergeado es candidato
        }

        $log = $this->git(['log', $tag . '..HEAD', '--format=%H', '--merges']);
        if (! $log->isSuccessful()) {
            return $items->values(); // git falló: falla-abierto (mejor mostrar de más que de menos)
        }
        $hashes = array_flip(array_values(array_filter(preg_split('/\R/', trim($log->getOutput())))));

        return $items->filter(fn (RoadmapItem $i) => isset($hashes[$i->merge_commit]))->values();
    }

    /**
     * #933 Fase 3 — detector de dependencias/colisiones ANTES de construir una rama de versión
     * (la Fase 4 de cherry-pick no se implementa en este item; este detector es la pieza de
     * seguridad que la habilita). Sobre los candidatos actuales, separa marcados
     * (`marcado_version=true`) de no-marcados y busca dos señales de riesgo real:
     *
     *   a) jerarquía — `origen_item_id`: un item marcado cuyo padre es candidato pero NO está
     *      marcado probablemente depende de su código (caso real citado en el item: la cadena
     *      #199→#200→#201→#870→#871→#872).
     *   b) archivos — un candidato NO marcado que toca los mismos archivos que uno marcado y cuyo
     *      merge es CRONOLÓGICAMENTE ANTERIOR: el cherry-pick del marcado, aplicado sobre el
     *      último tag sin el commit del excluido, puede conflictuar.
     *
     * Solo lectura; no cambia ningún estado. Devuelve un array de violaciones para mostrar en
     * pantalla ANTES de permitir construir la rama (bloqueante por defecto, con opción explícita
     * de continuar — eso lo decide la UI, no este método).
     */
    public function detectarDependenciasVersion(): array
    {
        $candidatos = $this->itemsCandidatosVersion();
        $porId = $candidatos->keyBy('id');
        $marcados = $candidatos->where('marcado_version', true);
        if ($marcados->isEmpty()) {
            return [];
        }

        $violaciones = [];

        // a) jerarquía (origen_item_id)
        foreach ($marcados as $item) {
            $padreId = $item->origen_item_id;
            if (! $padreId || ! isset($porId[$padreId])) {
                continue; // sin padre, o el padre ya no es candidato (ya iba en un tag anterior)
            }
            $padre = $porId[$padreId];
            if (! $padre->marcado_version) {
                $violaciones[] = [
                    'tipo' => 'jerarquia',
                    'item_id' => (int) $item->id,
                    'item_title' => $item->title,
                    'depende_de_id' => (int) $padre->id,
                    'depende_de_title' => $padre->title,
                    'detalle' => "#{$item->id} es hijo de #{$padre->id} (origen_item_id) y #{$padre->id} NO está marcado para esta versión.",
                ];
            }
        }

        // b) archivos, respetando el orden cronológico real del merge (no el id del item)
        $conRama = $candidatos->filter(fn (RoadmapItem $i) => ! empty($i->branch) && ! empty($i->merge_commit));
        if ($conRama->count() >= 2) {
            $mergedAt = [];
            foreach ($conRama as $i) {
                $p = $this->git(['log', '-1', '--format=%ct', $i->merge_commit]);
                $mergedAt[$i->id] = $p->isSuccessful() ? (int) trim($p->getOutput()) : 0;
            }

            $footprints = [];
            $footprintDe = function (RoadmapItem $i) use (&$footprints) {
                if (! isset($footprints[$i->id])) {
                    $footprints[$i->id] = $this->footprintDeRama($i->branch, $i->merge_commit);
                }

                return $footprints[$i->id];
            };

            foreach ($marcados as $m) {
                if (empty($m->branch) || empty($m->merge_commit)) {
                    continue;
                }
                foreach ($conRama as $c) {
                    if ($c->id === $m->id || $c->marcado_version) {
                        continue; // no es "excluido": es el propio item, o ya está marcado también
                    }
                    if (($mergedAt[$c->id] ?? 0) >= ($mergedAt[$m->id] ?? PHP_INT_MAX)) {
                        continue; // el excluido no mergeó antes: no aplica el riesgo de cherry-pick
                    }
                    $comunes = array_values(array_intersect($footprintDe($m), $footprintDe($c)));
                    if (! $comunes) {
                        continue;
                    }
                    $violaciones[] = [
                        'tipo' => 'archivos',
                        'item_id' => (int) $m->id,
                        'item_title' => $m->title,
                        'depende_de_id' => (int) $c->id,
                        'depende_de_title' => $c->title,
                        'detalle' => "#{$m->id} toca los mismos archivos que #{$c->id} (mergeado antes, NO marcado): "
                            . implode(', ', array_slice($comunes, 0, 5)) . (count($comunes) > 5 ? '…' : ''),
                        'archivos' => $comunes,
                    ];
                }
            }
        }

        return $violaciones;
    }

    /**
     * #966 Fase 4 — construye la rama de release por cherry-pick de los candidatos marcados
     * (`marcado_version=true`), en orden CRONOLÓGICO de merge ascendente (mismo `%ct` de
     * `detectarDependenciasVersion()` — fuera de orden multiplica conflictos, según el propio
     * item padre). Antes de tocar nada corre `detectarDependenciasVersion()`: con violaciones y
     * `$ignorarAvisos=false` no construye nada y las devuelve para que el llamador (UI/Irving)
     * decida "continuar bajo tu responsabilidad" explícitamente.
     *
     * Manejo de conflicto — decisión explícita de Irving en el brief de #966 (pregunta "qué
     * hacer cuando un cherry-pick falla"): NO aborta la construcción entera. Aborta SOLO ese
     * pick (`cherry-pick --abort`), lo reporta en `fallidos` y CONTINÚA con el resto de los
     * marcados — la release no queda bloqueada por un item conflictivo, Irving decide después
     * qué hacer con lo caído. Si NINGÚN marcado logra aplicarse, la rama queda vacía: se borra
     * (nada que revisar) y el resultado se reporta como fallo total.
     *
     * Operación AISLADA e invocada a demanda (su propio endpoint — ver
     * `RoadmapController::integracionVersionConstruirRama`): NO forma parte del pipeline de
     * `config/deployment.php`, no hace push, no toca `git_tag`/`git_push` del pipeline existente.
     * Solo git LOCAL sobre `base_path()` (mismo patrón que `MergeRunner`/`footprintDeRama`).
     */
    public function construirRamaVersion(string $nombreRama, string $version, bool $ignorarAvisos = false): array
    {
        $violaciones = $this->detectarDependenciasVersion();
        if ($violaciones !== [] && ! $ignorarAvisos) {
            return ['ok' => false, 'motivo' => 'dependencias_sin_resolver', 'violaciones' => $violaciones];
        }

        $marcados = $this->itemsCandidatosVersion()->where('marcado_version', true)->values();
        if ($marcados->isEmpty()) {
            return ['ok' => false, 'motivo' => 'sin_candidatos_marcados'];
        }

        if ($this->git(['rev-parse', '--verify', $nombreRama])->isSuccessful()) {
            return ['ok' => false, 'motivo' => 'rama_ya_existe', 'rama' => $nombreRama];
        }

        // Orden cronológico ascendente por fecha real de merge (mismo patrón que $mergedAt en
        // detectarDependenciasVersion()).
        $mergedAt = [];
        foreach ($marcados as $i) {
            if (empty($i->merge_commit)) {
                continue;
            }
            $p = $this->git(['log', '-1', '--format=%ct', $i->merge_commit]);
            $mergedAt[$i->id] = $p->isSuccessful() ? (int) trim($p->getOutput()) : 0;
        }
        $ordenados = $marcados->sortBy(fn (RoadmapItem $i) => $mergedAt[$i->id] ?? 0)->values();

        $ramaOrigen = trim($this->git(['rev-parse', '--abbrev-ref', 'HEAD'])->getOutput());
        if ($ramaOrigen === '' || $ramaOrigen === 'HEAD') {
            $ramaOrigen = 'main';
        }

        $tag  = $this->ultimoTag();
        $base = $tag ?: 'main';

        if (! $this->git(['checkout', '-b', $nombreRama, $base])->isSuccessful()) {
            return ['ok' => false, 'motivo' => 'no_se_pudo_crear_rama', 'base' => $base];
        }

        $incluidos = [];
        $fallidos  = [];
        foreach ($ordenados as $item) {
            if (empty($item->merge_commit)) {
                $fallidos[] = ['item_id' => (int) $item->id, 'title' => $item->title, 'motivo' => 'sin_merge_commit'];
                continue;
            }

            $pick = $this->git(['cherry-pick', '-m', '1', $item->merge_commit]);
            if ($pick->isSuccessful()) {
                $incluidos[] = ['item_id' => (int) $item->id, 'title' => $item->title, 'merge_commit' => $item->merge_commit];
                continue;
            }

            $conflicto = trim($this->git(['diff', '--name-only', '--diff-filter=U'])->getOutput());
            $this->git(['cherry-pick', '--abort']);
            $fallidos[] = [
                'item_id'      => (int) $item->id,
                'title'        => $item->title,
                'merge_commit' => $item->merge_commit,
                'motivo'       => 'conflicto_cherry_pick',
                'archivos'     => array_values(array_filter(preg_split('/\R/', $conflicto))),
            ];
        }

        if ($incluidos === []) {
            $this->git(['checkout', $ramaOrigen]);
            $this->git(['branch', '-D', $nombreRama]);

            return ['ok' => false, 'motivo' => 'ningun_cherry_pick_aplico', 'fallidos' => $fallidos, 'base' => $base];
        }

        // Tag SOBRE la rama de release (HEAD sigue ahí), nunca sobre main.
        $tagResult = $this->git(['tag', '-a', $version, '-m', "Release {$version}"]);
        $this->git(['checkout', $ramaOrigen]);

        return [
            'ok'         => true,
            'rama'       => $nombreRama,
            'base'       => $base,
            'version'    => $version,
            'tag_creado' => $tagResult->isSuccessful(),
            'incluidos'  => $incluidos,
            'fallidos'   => $fallidos,
        ];
    }
}
