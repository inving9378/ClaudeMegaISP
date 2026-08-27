<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Console\CompuertasSondaCommand;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\TorreCompuertaCambio;
use App\Modules\Addons\Roadmap\Services\JarvisIconosService;
use App\Modules\Addons\Roadmap\Services\TorreConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * IDENTIDAD Y ESTADO DE JARVIS.
 *
 * Dos endpoints con públicos muy distintos, y por eso van con guardas distintas:
 *
 *  · `identidad` / `guardar` — configuración. Gate `torre.config.view` / `.edit`, confirmación en
 *    dos pasos del servidor y bitácora, igual que el resto del panel.
 *  · `estado` — lo consulta la BURBUJA desde cualquier pantalla del sistema, así que tiene que ser
 *    barato y no puede exigir permisos de la Torre: quien ve la burbuja ve su color. No expone ni
 *    un dato de negocio — sólo cuántas decisiones esperan a Irving y si el medidor sigue latiendo.
 */
class JarvisIdentidadController extends Controller
{
    public function __construct(
        private JarvisIconosService $iconos,
        private TorreConfigService $config,
    ) {
    }

    /** GET — catálogo completo + lo elegido. Para la sección «JARVIS · identidad» del panel. */
    public function identidad(): JsonResponse
    {
        $this->authorize('torre.config.view');

        return response()->json([
            'ok'            => true,
            'puede_editar'  => (bool) auth()->user()?->can('torre.config.edit'),
            'elegido'       => $this->iconos->elegido(),
            'para_burbuja'  => $this->iconos->paraBurbuja(),
            'solo_pantalla' => $this->iconos->soloPantalla(),
            'tamanos'       => JarvisIconosService::TAMANOS,
            'dir_origen'    => JarvisIconosService::DIR_ORIGEN,
            'generado_en'   => $this->iconos->manifiesto()['generado_en'] ?? null,
            'identidad'     => $this->iconos->identidad(),
            'bitacora'      => TorreCompuertaCambio::query()
                ->where('compuerta', 'jarvis_icono')->orderByDesc('id')->limit(20)->get()
                ->map(fn ($c) => [
                    'cuando' => optional($c->created_at)->toDateTimeString(),
                    'quien'  => $c->user_login ?: '—',
                    'de'     => $c->valor_antes,
                    'a'      => $c->valor_despues,
                ])->all(),
        ]);
    }

    /**
     * POST — fija el icono. GLOBAL: JARVIS se ve igual para todos.
     *
     * `slug = null` vuelve al de fábrica. Se valida contra el catálogo y no contra el disco: un
     * slug que no está en `para_burbuja` no entra ni aunque el archivo exista — los que llevan la
     * palabra JARVIS escrita están fuera a propósito.
     */
    public function guardar(Request $request): JsonResponse
    {
        $this->authorize('torre.config.edit');

        $datos = $request->validate([
            'slug'       => ['present', 'nullable', 'string', 'max:60'],
            'confirmado' => ['required', 'boolean'],
        ]);

        if (! $datos['confirmado']) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Esta acción necesita confirmación explícita en un segundo paso.',
            ], 422);
        }

        $slug = $datos['slug'] ?: null;
        if ($slug !== null && ! $this->iconos->esElegible($slug)) {
            return response()->json([
                'ok'      => false,
                'mensaje' => "«{$slug}» no está en el set disponible para la burbuja.",
            ], 422);
        }

        $antes = $this->config->get()->jarvis_icono ?: '(de fábrica)';
        $diff  = $this->config->update(['jarvis_icono' => $slug], auth()->user());

        if ($diff !== []) {
            TorreCompuertaCambio::registrar('jarvis_icono', 'elegir', $antes, $slug ?: '(de fábrica)');
        }

        return response()->json([
            'ok'        => true,
            'mensaje'   => $diff === [] ? 'Sin cambios.' : "Icono de JARVIS: {$antes} → " . ($slug ?: '(de fábrica)') . '.',
            'identidad' => app(JarvisIconosService::class)->identidad(),
        ]);
    }

    /**
     * GET — el estado que pinta el anillo de la burbuja. Barato y cacheado 20 s.
     *
     * ── EL INTERRUPTOR DE HOMBRE MUERTO ─────────────────────────────────────────────────────────
     *
     * `sin_medir` es el estado que más importa y el que ningún otro control da: si el medidor deja
     * de latir, JARVIS **lo dice solo**, en la esquina, sin que nadie entre a la Torre a buscarlo.
     * Un asistente callado y un asistente muerto se ven idénticos, y ésa es exactamente la forma de
     * fallo que este sistema ya sufrió con los motores sin arranque. El anillo lo separa.
     *
     * La edad se mide contra el snapshot que escribe `circuito:compuertas-sonda` cada minuto: es el
     * pulso real del medidor, no un booleano de configuración.
     */
    public function estado(): JsonResponse
    {
        $datos = Cache::remember('jarvis_estado_burbuja', now()->addSeconds(20), function () {
            $edad = null;
            try {
                $ruta = storage_path('app/' . CompuertasSondaCommand::SNAPSHOT);
                if (is_file($ruta)) {
                    $so   = json_decode((string) file_get_contents($ruta), true);
                    $ts   = (int) ($so['medido_ts'] ?? 0);
                    $edad = $ts > 0 ? max(0, time() - $ts) : null;
                }
            } catch (\Throwable) {
                $edad = null;
            }

            $preguntas = 0;
            $baseViva  = true;
            try {
                $preguntas = RoadmapItem::query()
                    ->whereNull('archivado_at')
                    ->where('estado_aprobacion', 'requiere_irving')
                    ->whereNotIn('status', ['done', 'cancelled'])
                    ->count();
            } catch (\Throwable) {
                $baseViva = false;
            }

            // Umbral: 5 minutos. La sonda late cada minuto, así que 5 sin noticias no es un
            // retraso normal — es que dejó de latir.
            $sinMedir = $edad === null || $edad > 300;

            $estado = match (true) {
                ! $baseViva => 'alerta',
                $sinMedir   => 'sin_medir',
                $preguntas > 0 => 'pregunta',
                default     => 'normal',
            };

            return [
                'estado'       => $estado,
                'preguntas'    => $preguntas,
                'medido_hace'  => $edad,
                'base_viva'    => $baseViva,
                'texto'        => match ($estado) {
                    'alerta'    => 'No puedo leer la base: estoy en modo mínimo.',
                    'sin_medir' => 'Llevo ' . ($edad === null ? 'un rato' : $this->humano($edad)) . ' sin medir nada.',
                    'pregunta'  => $preguntas . ' ' . ($preguntas === 1 ? 'decisión te espera' : 'decisiones te esperan'),
                    default     => 'Todo medido y al día.',
                },
            ];
        });

        return response()->json($datos + ['identidad' => $this->iconos->identidad()]);
    }

    private function humano(int $seg): string
    {
        if ($seg < 120) {
            return "{$seg} s";
        }
        if ($seg < 7200) {
            return round($seg / 60) . ' min';
        }

        return round($seg / 3600) . ' h';
    }
}
