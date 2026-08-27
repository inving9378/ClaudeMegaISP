<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\CircuitoFrontera;
use App\Modules\Addons\Roadmap\Models\TorreCompuertaCambio;
use App\Modules\Addons\Roadmap\Models\TorreConfig;
use App\Modules\Addons\Roadmap\Services\AutopilotService;
use App\Modules\Addons\Roadmap\Services\FronterasService;
use App\Modules\Addons\Roadmap\Services\TorreConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GOBIERNO DE LAS FRONTERAS DURAS desde la pestaña «Configuración» de la Torre.
 *
 * ── LAS REGLAS QUE HEREDA (y que no se relajan aquí) ────────────────────────────────────────────
 *
 *  · **Confirmación en dos pasos, del SERVIDOR.** Ninguna acción corre con un clic suelto: el
 *    cliente debe mandar `confirmado=true` y el servidor lo exige aunque la UI ya haya preguntado.
 *    Es la lección del botón RUN MIGRATIONS: una confirmación que sólo vive en el frontend es un
 *    adorno, porque el endpoint sigue estando a un `curl` de distancia.
 *  · **La UI nunca decide autorización.** Este controller resuelve y devuelve; Vue sólo pinta. Si
 *    la decisión viviera en el cliente bastaría abrir DevTools para apagarse una frontera.
 *  · **Todo cambio deja rastro**: `torre_compuerta_cambios` (consultable desde la misma pantalla) y
 *    el canal de log de la Torre, donde AFLOJAR se registra como `warning` y endurecer como `info`.
 *
 * ── LO QUE ESTA PANTALLA CAMBIA RESPECTO DE LA DOCTRINA ANTERIOR ────────────────────────────────
 *
 * Hasta hoy los topes duros se documentaban como «no se levantan desde ninguna configuración». A
 * petición explícita de Irving (2026-08-27) ahora SÍ se gobiernan desde aquí. La contrapartida es
 * que cada perilla es auditada y viene con su número al lado: cuántos items dispararía esa
 * categoría hoy y cuántas veces la válvula la abrió. Una perilla sin ese número es una perilla a
 * ciegas, y ése era el argumento para no exponerla.
 */
class TorreFronterasController extends Controller
{
    public function __construct(
        private FronterasService $fronteras,
        private TorreConfigService $config,
    ) {
    }

    private function autorizarVer(): void
    {
        $this->authorize('torre.config.view');
    }

    /** Guarda común de toda escritura: permiso de edición + el segundo paso explícito. */
    private function autorizarEscribir(Request $request): ?JsonResponse
    {
        $this->authorize('torre.config.edit');

        if (! $request->boolean('confirmado')) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Esta acción necesita confirmación explícita en un segundo paso.',
            ], 422);
        }

        return null;
    }

    /**
     * GET — la foto completa de las fronteras: categorías, términos, métricas, válvula, guardas y
     * el techo del autopilot con la simulación por nivel.
     *
     * `?refrescar=1` tira la caché de métricas (el botón «Recalcular» de la pantalla). Sin él se
     * sirve la de los últimos 10 minutos: el barrido lee el texto de todos los items vivos.
     */
    public function index(Request $request): JsonResponse
    {
        $this->autorizarVer();

        $mapa     = $this->fronteras->mapa();
        $metricas = $this->fronteras->metricas($request->boolean('refrescar'));
        $cfg      = $this->config->get();

        $categorias = [];
        foreach ($mapa as $cat => $c) {
            $terminos = [];
            foreach ($c['terminos'] as $t) {
                $terminos[] = $t + [
                    // Disparos MEDIDOS sobre los items vivos, no una estimación: es el número que
                    // contesta «¿este término es ancho de más?».
                    'disparos' => (int) ($metricas['por_termino'][$cat . '|' . $t['termino']] ?? 0),
                ];
            }
            // Los que más disparan arriba: la pregunta que trae a alguien a esta lista es casi
            // siempre «cuál me está llenando la bandeja».
            usort($terminos, fn ($a, $b) => [$b['activo'], $b['disparos']] <=> [$a['activo'], $a['disparos']]);

            $categorias[] = [
                'categoria' => $cat,
                'activa'    => $c['activa'],
                'efecto'    => $c['efecto'],
                'orden'     => $c['orden'],
                'desde'     => $c['desde'],
                'terminos'  => $terminos,
                'metricas'  => $metricas['por_categoria'][$cat] ?? [
                    'items_que_disparan' => 0, 'valvula_aperturas' => 0, 'sellos_accion' => 0,
                ],
            ];
        }

        return response()->json([
            'ok'           => true,
            'puede_editar' => (bool) auth()->user()?->can('torre.config.edit'),
            'categorias'   => $categorias,
            'efectos'      => array_map(fn ($e) => [
                'clave'       => $e,
                'descripcion' => CircuitoFrontera::EFECTO_DESCRIPCION[$e] ?? '',
            ], CircuitoFrontera::EFECTOS),
            'valvula' => [
                'activa'         => (bool) $cfg->valvula_activa,
                'modo'           => $cfg->valvulaModo(),
                'modos'          => TorreConfig::VALVULA_MODOS,
                'guarda_termino' => (bool) $cfg->valvula_guarda_termino,
                'guarda_razon'   => (bool) $cfg->valvula_guarda_razon,
                // El interruptor de código sigue existiendo y manda si está apagado: se muestra
                // para que un «activa: sí» en pantalla no mienta cuando la config la tiene apagada.
                'config_enabled' => (bool) config('circuito.valvula_contexto.enabled', true),
                'modelo'         => (string) config(
                    'circuito.valvula_contexto.model',
                    config('circuito.revisor.model_routine', 'claude-sonnet-4-6')
                ),
                'resumen'        => $metricas['valvula'] ?? [],
            ],
            'autopilot'    => app(AutopilotService::class)->simulacionTechos(),
            'metricas'     => [
                'calculado_en' => $metricas['calculado_en'] ?? null,
                'items_vivos'  => $metricas['items_vivos'] ?? 0,
            ],
            'bitacora' => $this->bitacoraFronteras(),
        ]);
    }

    /** Últimos cambios a fronteras/válvula/techo, para leerlos sin cambiar de pantalla. */
    private function bitacoraFronteras(): array
    {
        return TorreCompuertaCambio::query()
            ->where(fn ($q) => $q->where('compuerta', 'like', 'frontera:%')
                ->orWhereIn('compuerta', ['valvula', 'autopilot_techo']))
            ->orderByDesc('id')->limit(40)->get()
            ->map(fn ($c) => [
                'cuando'    => optional($c->created_at)->toDateTimeString(),
                'quien'     => $c->user_login ?: '—',
                'compuerta' => $c->compuerta,
                'accion'    => $c->accion,
                'de'        => $c->valor_antes,
                'a'         => $c->valor_despues,
                'detalle'   => $c->detalle,
            ])->all();
    }

    /** POST — enciende/apaga una categoría o cambia su efecto. */
    public function categoria(Request $request): JsonResponse
    {
        if ($r = $this->autorizarEscribir($request)) {
            return $r;
        }

        $datos = $request->validate([
            'categoria'  => ['required', 'string', 'max:40'],
            'activa'     => ['sometimes', 'boolean'],
            'efecto'     => ['sometimes', 'string', Rule::in(CircuitoFrontera::EFECTOS)],
            'confirmado' => ['required', 'boolean'],
        ]);

        if (! array_key_exists($datos['categoria'], $this->fronteras->mapa())) {
            return response()->json(['ok' => false, 'mensaje' => 'Categoría desconocida.'], 422);
        }

        $hechos = [];
        if ($request->has('activa')) {
            $r = $this->fronteras->setCategoriaActiva($datos['categoria'], (bool) $datos['activa']);
            $hechos[] = "estado: {$r['antes']} → {$r['despues']}";
        }
        if ($request->has('efecto')) {
            $r = $this->fronteras->setEfecto($datos['categoria'], (string) $datos['efecto']);
            $hechos[] = "efecto: {$r['antes']} → {$r['despues']}";
        }

        if ($hechos === []) {
            return response()->json(['ok' => false, 'mensaje' => 'No se indicó qué cambiar.'], 422);
        }

        return response()->json([
            'ok'      => true,
            'mensaje' => "{$datos['categoria']} — " . implode(' · ', $hechos),
        ]);
    }

    /** POST — agrega, quita o cambia el modo de coincidencia de un término. */
    public function termino(Request $request): JsonResponse
    {
        if ($r = $this->autorizarEscribir($request)) {
            return $r;
        }

        $datos = $request->validate([
            'categoria'        => ['required', 'string', 'max:40'],
            'termino'          => ['required', 'string', 'max:120'],
            'accion'           => ['required', Rule::in(['agregar', 'quitar', 'modo'])],
            'palabra_completa' => ['sometimes', 'boolean'],
            'confirmado'       => ['required', 'boolean'],
        ]);

        if (! array_key_exists($datos['categoria'], $this->fronteras->mapa())) {
            return response()->json(['ok' => false, 'mensaje' => 'Categoría desconocida.'], 422);
        }

        try {
            $r = match ($datos['accion']) {
                'agregar' => $this->fronteras->agregarTermino(
                    $datos['categoria'], $datos['termino'], (bool) ($datos['palabra_completa'] ?? false)
                ),
                'quitar'  => $this->fronteras->quitarTermino($datos['categoria'], $datos['termino']),
                'modo'    => $this->fronteras->setPalabraCompleta(
                    $datos['categoria'], $datos['termino'], (bool) ($datos['palabra_completa'] ?? false)
                ),
            };
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['ok' => false, 'mensaje' => 'Ese término no está en la categoría.'], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'      => true,
            'mensaje' => $r['mensaje'] ?? "«{$datos['termino']}»: {$r['antes']} → {$r['despues']}",
        ]);
    }

    /** POST — la válvula: interruptor, modo y sus dos guardas. */
    public function valvula(Request $request): JsonResponse
    {
        if ($r = $this->autorizarEscribir($request)) {
            return $r;
        }

        $datos = $request->validate([
            'activa'         => ['sometimes', 'boolean'],
            'modo'           => ['sometimes', Rule::in(TorreConfig::VALVULA_MODOS)],
            'guarda_termino' => ['sometimes', 'boolean'],
            'guarda_razon'   => ['sometimes', 'boolean'],
            'confirmado'     => ['required', 'boolean'],
        ]);
        unset($datos['confirmado']);

        $mapa = [
            'activa'         => 'valvula_activa',
            'modo'           => 'valvula_modo',
            'guarda_termino' => 'valvula_guarda_termino',
            'guarda_razon'   => 'valvula_guarda_razon',
        ];

        $cambios = [];
        foreach ($datos as $k => $v) {
            $cambios[$mapa[$k]] = $v;
        }
        if ($cambios === []) {
            return response()->json(['ok' => false, 'mensaje' => 'No se indicó qué cambiar.'], 422);
        }

        // Reusa el servicio de configuración: es quien invalida la caché y audita en el canal de la
        // Torre. Duplicar la escritura aquí sería el primer paso hacia dos verdades.
        $diff = $this->config->update($cambios, auth()->user());

        foreach ($diff as $campo => $d) {
            $antes   = is_bool($d['antes']) ? ($d['antes'] ? 'sí' : 'no') : (string) $d['antes'];
            $despues = is_bool($d['despues']) ? ($d['despues'] ? 'sí' : 'no') : (string) $d['despues'];
            TorreCompuertaCambio::registrar('valvula', $campo, $antes, $despues);
        }

        return response()->json([
            'ok'      => true,
            'cambios' => $diff,
            'mensaje' => $diff === [] ? 'Sin cambios.' : count($diff) . ' ajuste(s) aplicado(s) a la válvula.',
        ]);
    }

    /**
     * POST — el techo del autopilot. `nivel = 'config'` devuelve la perilla a «lo que diga
     * `config/circuito.php`», que es el estado de fábrica y no lo mismo que fijar un valor igual.
     */
    public function techoAutopilot(Request $request): JsonResponse
    {
        if ($r = $this->autorizarEscribir($request)) {
            return $r;
        }

        $datos = $request->validate([
            'nivel'      => ['required', Rule::in(['A', 'B', 'C', 'config'])],
            'confirmado' => ['required', 'boolean'],
        ]);

        $cfg   = $this->config->get();
        $antes = $cfg->autopilotMaxNivel() . ' (' . $cfg->autopilotMaxNivelFuente() . ')';

        $diff = $this->config->update(
            ['autopilot_max_nivel' => $datos['nivel'] === 'config' ? null : $datos['nivel']],
            auth()->user()
        );

        $cfg     = $this->config->get();
        $despues = $cfg->autopilotMaxNivel() . ' (' . $cfg->autopilotMaxNivelFuente() . ')';

        if ($diff !== []) {
            TorreCompuertaCambio::registrar('autopilot_techo', 'nivel', $antes, $despues);
        }

        return response()->json([
            'ok'        => true,
            'mensaje'   => $diff === [] ? 'Sin cambios.' : "Techo del autopilot: {$antes} → {$despues}.",
            'autopilot' => app(AutopilotService::class)->simulacionTechos(),
        ]);
    }
}
