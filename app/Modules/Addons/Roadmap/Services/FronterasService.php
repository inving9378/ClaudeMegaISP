<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\CircuitoFrontera;
use App\Modules\Addons\Roadmap\Models\CircuitoFronteraTermino;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\TorreCompuertaCambio;
use App\Modules\Addons\Roadmap\Support\DetectorTerminos;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ACCESO ÚNICO a las fronteras duras — la lista de términos, su categoría y su efecto.
 *
 * ── QUÉ CAMBIA RESPECTO DE ANTES ────────────────────────────────────────────────────────────────
 *
 * La lista vivía sólo en `config('circuito.thomas.escalamiento')`. Seguía siendo el único control
 * por contenido que no depende de la autodeclaración de un modelo, pero nadie podía verla sin abrir
 * un archivo ni ajustarla sin un cambio de código. Ahora vive en `circuito_fronteras` +
 * `circuito_frontera_terminos`, gobernable desde la Torre, y **la config es el respaldo**: si la
 * tabla no existe o está vacía, se lee la config exactamente como antes.
 *
 * Lo que NO cambió: la DETECCIÓN sigue siendo determinista y gratuita (`DetectorTerminos`, anclado
 * a palabra, con limpieza de líneas de proceso y ventana de negación). Esta clase decide QUÉ se
 * busca y QUÉ pasa al encontrarlo; no decide con un modelo.
 *
 * ── LA CACHÉ ────────────────────────────────────────────────────────────────────────────────────
 *
 * Se invalida en CADA escritura. Si no, la pantalla muestra una lista y el servidor aplica otra, y
 * ese desfase es peor que no tener panel: enseña a desconfiar del tablero, y quien deja de creerle
 * a un control deja de creerle también a los que sí eran ciertos (misma regla que `TorreConfigService`).
 */
class FronterasService
{
    public const CACHE_KEY = 'circuito_fronteras_mapa';

    /** Canal de auditoría de la Torre; el mismo que usa la política. */
    public const LOG_CANAL = 'torre_config';

    /** Dureza relativa de cada efecto: índice mayor = más suave. Sirve para saber si un cambio afloja. */
    private const DUREZA = ['bloquear' => 3, 'bandeja' => 2, 'avisar' => 1];

    // ── LECTURA ─────────────────────────────────────────────────────────────────────────────────

    /**
     * El mapa vigente: `[categoria => ['activa'=>bool,'efecto'=>string,'terminos'=>[[t,palabra_completa,activo]]]]`.
     *
     * @return array<string,array{activa:bool,efecto:string,orden:int,desde:string,terminos:array<int,array{termino:string,palabra_completa:bool,activo:bool}>}>
     */
    public function mapa(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => $this->construirMapa());
    }

    /** @return array<string,mixed> */
    private function construirMapa(): array
    {
        // RESPALDO: sin tabla (o vacía) se lee la config, byte por byte como antes de este cambio.
        // Un panel nuevo no puede ser el motivo de que la frontera dura deje de existir.
        if (! $this->tablasListas()) {
            return $this->mapaDesdeConfig('config/circuito.php (respaldo: la tabla no está disponible)');
        }

        $categorias = CircuitoFrontera::query()->orderBy('orden')->orderBy('categoria')->get();
        if ($categorias->isEmpty()) {
            return $this->mapaDesdeConfig('config/circuito.php (respaldo: la tabla está vacía)');
        }

        $terminos = CircuitoFronteraTermino::query()->orderBy('termino')->get()->groupBy('categoria');

        $mapa = [];
        foreach ($categorias as $c) {
            $mapa[$c->categoria] = [
                'activa'   => (bool) $c->activa,
                'efecto'   => in_array($c->efecto, CircuitoFrontera::EFECTOS, true) ? $c->efecto : 'bandeja',
                'orden'    => (int) $c->orden,
                'desde'    => 'tabla circuito_fronteras',
                'terminos' => ($terminos[$c->categoria] ?? collect())->map(fn ($t) => [
                    'termino'          => (string) $t->termino,
                    'palabra_completa' => (bool) $t->palabra_completa,
                    'activo'           => (bool) $t->activo,
                ])->values()->all(),
            ];
        }

        return $mapa;
    }

    /** @return array<string,mixed> */
    private function mapaDesdeConfig(string $desde): array
    {
        $mapa = $orden = [];
        $n = 0;
        foreach ((array) config('circuito.thomas.escalamiento', []) as $categoria => $terminos) {
            $mapa[$categoria] = [
                'activa'   => true,
                'efecto'   => 'bandeja',
                'orden'    => $n++,
                'desde'    => $desde,
                'terminos' => array_map(fn ($t) => [
                    'termino'          => (string) $t,
                    'palabra_completa' => false,
                    'activo'           => true,
                ], (array) $terminos),
            ];
        }
        unset($orden);

        return $mapa;
    }

    private function tablasListas(): bool
    {
        try {
            return Schema::hasTable('circuito_fronteras') && Schema::hasTable('circuito_frontera_terminos');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * ¿Este texto dispara alguna frontera ACTIVA? Devuelve la primera coincidencia con todo lo que
     * el llamador necesita: la categoría, **el término exacto que disparó** y el efecto vigente.
     *
     * Que devuelva el término y no sólo la categoría no es un detalle: la válvula de contexto —que
     * puede ablandar esta frontera— recibía la categoría y su prompt se la presentaba al modelo como
     * si fuera el término. Se le preguntaba por una palabra que no estaba en el texto.
     *
     * @return array{categoria:?string, termino:?string, efecto:?string}
     */
    public function detectar(string $texto): array
    {
        $heno = mb_strtolower(preg_replace('/[ \t]+/', ' ', DetectorTerminos::limpiar($texto)));

        foreach ($this->mapa() as $categoria => $cfg) {
            if (! $cfg['activa']) {
                continue;   // categoría apagada por Irving: ni se busca.
            }
            foreach ($cfg['terminos'] as $t) {
                if (! $t['activo']) {
                    continue;
                }
                if (DetectorTerminos::dispara($heno, mb_strtolower($t['termino']), $t['palabra_completa'])) {
                    return [
                        'categoria' => (string) $categoria,
                        'termino'   => (string) $t['termino'],
                        'efecto'    => (string) $cfg['efecto'],
                    ];
                }
            }
        }

        return ['categoria' => null, 'termino' => null, 'efecto' => null];
    }

    /** El efecto vigente de una categoría (o `bandeja`, el de hoy, si no se conoce). */
    public function efectoDe(?string $categoria): string
    {
        if ($categoria === null) {
            return 'bandeja';
        }

        return $this->mapa()[$categoria]['efecto'] ?? 'bandeja';
    }

    // ── ESCRITURA (siempre auditada) ────────────────────────────────────────────────────────────

    public function olvidar(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_METRICAS);
    }

    /** Enciende o apaga una categoría entera. Apagarla es AFLOJAR: se audita como warning. */
    public function setCategoriaActiva(string $categoria, bool $activa): array
    {
        $c = CircuitoFrontera::query()->where('categoria', $categoria)->firstOrFail();
        $antes = $c->activa ? 'activa' : 'apagada';
        if ((bool) $c->activa === $activa) {
            return ['cambio' => false, 'antes' => $antes, 'despues' => $antes];
        }

        $c->activa = $activa;
        $c->save();
        $this->olvidar();

        $despues = $activa ? 'activa' : 'apagada';
        $this->auditar("frontera:{$categoria}", 'activa', $antes, $despues, afloja: ! $activa);

        return ['cambio' => true, 'antes' => $antes, 'despues' => $despues];
    }

    /** Cambia qué hace la categoría al dispararse. Bajar la dureza es AFLOJAR. */
    public function setEfecto(string $categoria, string $efecto): array
    {
        if (! in_array($efecto, CircuitoFrontera::EFECTOS, true)) {
            throw new \InvalidArgumentException("Efecto desconocido: {$efecto}");
        }

        $c = CircuitoFrontera::query()->where('categoria', $categoria)->firstOrFail();
        $antes = (string) $c->efecto;
        if ($antes === $efecto) {
            return ['cambio' => false, 'antes' => $antes, 'despues' => $antes];
        }

        $c->efecto = $efecto;
        $c->save();
        $this->olvidar();

        $this->auditar(
            "frontera:{$categoria}", 'efecto', $antes, $efecto,
            afloja: (self::DUREZA[$efecto] ?? 2) < (self::DUREZA[$antes] ?? 2)
        );

        return ['cambio' => true, 'antes' => $antes, 'despues' => $efecto];
    }

    /** Agrega un término a una categoría. Agregar ENDURECE (más cosas disparan). */
    public function agregarTermino(string $categoria, string $termino, bool $palabraCompleta): array
    {
        $termino = trim(mb_strtolower($termino));
        if ($termino === '') {
            throw new \InvalidArgumentException('El término está vacío.');
        }
        CircuitoFrontera::query()->where('categoria', $categoria)->firstOrFail();

        $ya = CircuitoFronteraTermino::query()
            ->where('categoria', $categoria)->where('termino', $termino)->first();

        if ($ya) {
            // Re-agregar uno desactivado lo revive; ya activo es no-op idempotente.
            if ($ya->activo) {
                return ['cambio' => false, 'mensaje' => "«{$termino}» ya estaba en {$categoria}."];
            }
            $ya->activo = true;
            $ya->palabra_completa = $palabraCompleta;
            $ya->save();
        } else {
            CircuitoFronteraTermino::create([
                'categoria'        => $categoria,
                'termino'          => $termino,
                'palabra_completa' => $palabraCompleta,
                'activo'           => true,
            ]);
        }

        $this->olvidar();
        $this->auditar("frontera:{$categoria}", 'termino_agregar', '—', $termino, afloja: false,
            detalle: $palabraCompleta ? 'palabra completa' : 'admite flexión');

        return ['cambio' => true, 'mensaje' => "«{$termino}» agregado a {$categoria}."];
    }

    /** Quita un término (baja lógica: `activo=false`). Quitar AFLOJA. */
    public function quitarTermino(string $categoria, string $termino): array
    {
        $t = CircuitoFronteraTermino::query()
            ->where('categoria', $categoria)->where('termino', $termino)->firstOrFail();

        if (! $t->activo) {
            return ['cambio' => false, 'mensaje' => "«{$termino}» ya estaba quitado."];
        }

        // Baja LÓGICA y no borrado: el término quitado sigue visible en la pantalla como
        // «quitado por ti», con su fecha. Un borrado físico dejaría la decisión sin rastro
        // consultable justo donde importa — al revisar por qué algo dejó de frenarse.
        $t->activo = false;
        $t->save();

        $this->olvidar();
        $this->auditar("frontera:{$categoria}", 'termino_quitar', $termino, '—', afloja: true);

        return ['cambio' => true, 'mensaje' => "«{$termino}» quitado de {$categoria}."];
    }

    /** Cambia el modo de coincidencia de un término (palabra completa vs. flexión). */
    public function setPalabraCompleta(string $categoria, string $termino, bool $palabraCompleta): array
    {
        $t = CircuitoFronteraTermino::query()
            ->where('categoria', $categoria)->where('termino', $termino)->firstOrFail();

        $antes = $t->palabra_completa ? 'palabra completa' : 'admite flexión';
        if ((bool) $t->palabra_completa === $palabraCompleta) {
            return ['cambio' => false, 'antes' => $antes, 'despues' => $antes];
        }

        $t->palabra_completa = $palabraCompleta;
        $t->save();
        $this->olvidar();

        $despues = $palabraCompleta ? 'palabra completa' : 'admite flexión';
        // Exigir palabra completa hace que dispare MENOS → afloja.
        $this->auditar("frontera:{$categoria}", 'termino_modo', $antes, $despues,
            afloja: $palabraCompleta, detalle: $termino);

        return ['cambio' => true, 'antes' => $antes, 'despues' => $despues];
    }

    /**
     * Rastro doble: la bitácora del tablero (consultable desde la misma pantalla) y el canal de
     * log de la Torre. **Aflojar se registra como `warning`**, endurecer como `info`: aflojar una
     * frontera es el evento donde trabajo sensible empieza a pasar sin que nadie lo mire.
     */
    private function auditar(string $compuerta, string $accion, ?string $antes, ?string $despues, bool $afloja, ?string $detalle = null): void
    {
        TorreCompuertaCambio::registrar(
            $compuerta, $accion, $antes, $despues,
            trim(($afloja ? '⚠ AFLOJA la frontera. ' : '') . (string) $detalle) ?: null
        );

        $payload = [
            'compuerta' => $compuerta, 'accion' => $accion,
            'antes' => $antes, 'despues' => $despues,
            'por' => auth()->user()?->login_user ?? auth()->user()?->name ?? 'sistema',
            'ts' => now()->toIso8601String(),
        ];

        try {
            $canal = Log::channel(self::LOG_CANAL);
            $afloja ? $canal->warning('frontera-dura-AFLOJADA', $payload)
                    : $canal->info('frontera-dura-ajustada', $payload);
        } catch (\Throwable $e) {
            Log::warning('fronteras: no se pudo auditar en su canal', $payload + ['error' => $e->getMessage()]);
        }
    }

    // ── MÉTRICAS (sin ellas la perilla es a ciegas) ─────────────────────────────────────────────

    public const CACHE_METRICAS = 'circuito_fronteras_metricas';

    /**
     * Por categoría: cuántos items VIVOS la dispararían hoy, y cuántas veces la válvula la abrió.
     *
     * Los dos números miden cosas distintas a propósito:
     *   · `items_que_disparan` se calcula EN VIVO corriendo el detector sobre el texto de cada item
     *     (es una simulación con la lista de HOY, así que responde «si muevo esto, a cuántos afecta»).
     *   · `valvula_aperturas` sale del rastro histórico en `roadmap_items.log` (eventos
     *     `valvula_nacimiento` / `valvula_contexto` con veredicto `mencion`), así que responde
     *     «cuántas veces se abrió de verdad», que no es lo mismo.
     *
     * Caché de 10 min: el barrido lee el texto de todos los items vivos. El botón «Recalcular» de
     * la pantalla la tira, así que el número nunca queda pegado sin que se pueda refrescar.
     */
    public function metricas(bool $refrescar = false): array
    {
        if ($refrescar) {
            Cache::forget(self::CACHE_METRICAS);
        }

        return Cache::remember(self::CACHE_METRICAS, now()->addMinutes(10), fn () => $this->calcularMetricas());
    }

    private function calcularMetricas(): array
    {
        $mapa = $this->mapa();

        $porCategoria = [];
        $porTermino   = [];
        foreach ($mapa as $cat => $cfg) {
            $porCategoria[$cat] = ['items_que_disparan' => 0, 'valvula_aperturas' => 0, 'sellos_accion' => 0];
            foreach ($cfg['terminos'] as $t) {
                $porTermino[$cat . '|' . $t['termino']] = 0;
            }
        }

        // ── 1. Simulación en vivo sobre los items vivos ──────────────────────────────────────────
        $vivos = 0;
        RoadmapItem::query()
            ->whereNull('archivado_at')
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
            ->select(['id', 'title', 'description', 'prompt'])
            ->chunkById(200, function ($items) use (&$porCategoria, &$porTermino, &$vivos, $mapa) {
                foreach ($items as $item) {
                    $vivos++;
                    $heno = mb_strtolower(preg_replace('/[ \t]+/', ' ', DetectorTerminos::limpiar(
                        (string) $item->title . "\n" . (string) $item->description . "\n" . (string) $item->prompt
                    )));

                    // Se cuenta CADA categoría que dispararía, no sólo la primera: la pregunta que
                    // responde esta columna es «si toco ESTA categoría, a cuántos items afecta»,
                    // y con el corte en la primera coincidencia las de abajo saldrían siempre en 0.
                    foreach ($mapa as $cat => $cfg) {
                        if (! $cfg['activa']) {
                            continue;
                        }
                        $disparoCategoria = false;
                        foreach ($cfg['terminos'] as $t) {
                            if (! $t['activo']) {
                                continue;
                            }
                            if (DetectorTerminos::dispara($heno, mb_strtolower($t['termino']), $t['palabra_completa'])) {
                                $porTermino[$cat . '|' . $t['termino']]++;
                                $disparoCategoria = true;
                            }
                        }
                        if ($disparoCategoria) {
                            $porCategoria[$cat]['items_que_disparan']++;
                        }
                    }
                }
            });

        // ── 2. Aperturas REALES de la válvula, del rastro histórico ──────────────────────────────
        $aperturas = $this->aperturasDeValvula();
        foreach ($aperturas['por_categoria'] as $cat => $n) {
            if (isset($porCategoria[$cat])) {
                $porCategoria[$cat]['valvula_aperturas'] = $n;
            }
        }
        foreach ($aperturas['sellos_accion'] as $cat => $n) {
            if (isset($porCategoria[$cat])) {
                $porCategoria[$cat]['sellos_accion'] = $n;
            }
        }

        return [
            'calculado_en'    => now()->toDateTimeString(),
            'items_vivos'     => $vivos,
            'por_categoria'   => $porCategoria,
            'por_termino'     => $porTermino,
            'valvula'         => $aperturas['resumen'],
        ];
    }

    /**
     * Aperturas de la válvula leídas del log de los items. Es el único registro que existe: la
     * válvula escribe `valvula_nacimiento` (puerta de alta) y `valvula_contexto` (triaje de nivel),
     * cada uno con su término, su veredicto y la razón del modelo.
     */
    private function aperturasDeValvula(): array
    {
        $porCategoria  = [];
        $sellosAccion  = [];
        $eventos       = [];
        $terminoACat   = [];

        foreach ($this->mapa() as $cat => $cfg) {
            foreach ($cfg['terminos'] as $t) {
                $terminoACat[mb_strtolower($t['termino'])] = $cat;
            }
            // La CATEGORÍA también se indexa: los eventos anteriores al 2026-08-27 guardaron la
            // categoría en el campo `termino` (era el bug que se arregló ese día), y omitirlos
            // dejaría la columna en cero justo para los casos que motivaron esta pantalla.
            $terminoACat[mb_strtolower((string) $cat)] = $cat;
        }

        RoadmapItem::query()
            ->whereNotNull('log')
            ->select(['id', 'log'])
            ->chunkById(200, function ($items) use (&$porCategoria, &$sellosAccion, &$eventos, $terminoACat) {
                foreach ($items as $item) {
                    $log = is_array($item->log) ? $item->log : [];
                    foreach ($log as $e) {
                        if (! is_array($e)) {
                            continue;
                        }
                        $ev = $e['evento'] ?? null;
                        if ($ev !== 'valvula_nacimiento' && $ev !== 'valvula_contexto') {
                            continue;
                        }
                        $eventos[] = $ev;
                        $cat = $terminoACat[mb_strtolower((string) ($e['termino'] ?? ''))] ?? null;
                        if ($cat === null) {
                            continue;
                        }
                        if (($e['veredicto'] ?? null) === 'mencion') {
                            $porCategoria[$cat] = ($porCategoria[$cat] ?? 0) + 1;
                        } elseif (($e['veredicto'] ?? null) === 'accion') {
                            $sellosAccion[$cat] = ($sellosAccion[$cat] ?? 0) + 1;
                        }
                    }
                }
            });

        $sellos = DB::table('roadmap_items')
            ->selectRaw('frontera_valvula, COUNT(*) as n')
            ->whereNotNull('frontera_valvula')
            ->groupBy('frontera_valvula')
            ->pluck('n', 'frontera_valvula')
            ->all();

        return [
            'por_categoria' => $porCategoria,
            'sellos_accion' => $sellosAccion,
            'resumen'       => [
                'invocaciones_registradas' => count($eventos),
                'sellados_mencion'         => (int) ($sellos['mencion'] ?? 0),
                'sellados_accion'          => (int) ($sellos['accion'] ?? 0),
            ],
        ];
    }
}
