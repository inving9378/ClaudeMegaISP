<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoHallazgo;
use App\Modules\Addons\Talento\Models\TalentoHallazgoCategoria;
use App\Modules\Addons\Talento\Models\TalentoHallazgoFoto;
use App\Modules\Addons\Talento\Models\TalentoRecorrido;
use App\Modules\Addons\Talento\Services\RecorridoImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Recorridos de planta externa, fase (a): importar la ruta y las fotos de Geo Tracker, revisar los
 * hallazgos (categoría, severidad, descripción, ubicación), recortar la ruta y enviarla (item #1311).
 * La evaluación del supervisor que convierte hallazgos en órdenes es del item #1312.
 *
 * Ver: talento.recorridos.view. Importar/editar/enviar: talento.recorridos.manage. Catálogo de
 * categorías: talento.recorridos.categorias.manage. Lo que ya se envió no se cambia.
 */
class TalentoRecorridoController extends Controller
{
    private const MSG_ENVIADO = 'El recorrido ya se envió; ya no se puede cambiar.';

    public function __construct(private RecorridoImportService $import) {}

    private function puedeVer(): void
    {
        abort_unless(auth()->user()->can('talento.recorridos.view') || auth()->user()->can('talento.recorridos.manage'), 403);
    }

    private function editable(int $id): TalentoRecorrido
    {
        $this->authorize('talento.recorridos.manage');
        $recorrido = TalentoRecorrido::findOrFail($id);
        abort_unless($recorrido->esBorrador(), 422, self::MSG_ENVIADO);

        return $recorrido;
    }

    private function hallazgoDe(TalentoRecorrido $recorrido, int $hid): TalentoHallazgo
    {
        return TalentoHallazgo::where('recorrido_id', $recorrido->id)->findOrFail($hid);
    }

    private function falla(InvalidArgumentException $e)
    {
        return response()->json(['error' => $e->getMessage()], 422);
    }

    public function index()
    {
        $this->puedeVer();

        return view('addon-talento::talento.recorridos');
    }

    public function data(Request $request)
    {
        $this->puedeVer();

        $q = TalentoRecorrido::with('colaborador.user:id,name,father_last_name')
            ->withCount([
                'hallazgos',
                'hallazgos as hallazgos_sin_ubicar_count' => fn ($h) => $h->whereNull('lat'),
            ])
            ->when($request->colaborador_id, fn ($q, $v) => $q->where('colaborador_id', $v))
            ->when($request->estado, fn ($q, $v) => $q->where('estado', $v))
            ->when($request->desde, fn ($q, $v) => $q->where(fn ($w) => $w->whereDate('iniciado_at', '>=', $v)->orWhere(fn ($s) => $s->whereNull('iniciado_at')->whereDate('created_at', '>=', $v))))
            ->when($request->hasta, fn ($q, $v) => $q->where(fn ($w) => $w->whereDate('iniciado_at', '<=', $v)->orWhere(fn ($s) => $s->whereNull('iniciado_at')->whereDate('created_at', '<=', $v))))
            ->orderByRaw('COALESCE(iniciado_at, created_at) DESC')
            ->paginate(min((int) ($request->per_page ?: 20), 100));

        $q->getCollection()->transform(fn ($r) => $r->makeHidden(['geometria', 'muestra']));

        return response()->json($q);
    }

    /** Buscador del formulario de importar: colaboradores activos por nombre (no exige talento.view). */
    public function colaboradores(Request $request)
    {
        $this->authorize('talento.recorridos.manage');
        $buscar = trim((string) $request->search);

        return response()->json(
            TalentoColaborador::with('user:id,name,father_last_name,mother_last_name')
                ->where('status', 'active')
                ->when($buscar !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                    ->where(DB::raw("CONCAT_WS(' ', name, father_last_name, mother_last_name)"), 'like', '%' . addcslashes($buscar, '%_\\') . '%')))
                ->limit(15)
                ->get(['id', 'user_id'])
        );
    }

    public function store(Request $request)
    {
        $this->authorize('talento.recorridos.manage');
        $datos = $request->validate([
            'colaborador_id' => 'required|integer|exists:talento_colaboradores,id',
            'modo'           => ['required', Rule::in(array_keys(TalentoRecorrido::MODOS))],
            'titulo'         => 'nullable|string|max:160',
            'tarea_id'       => 'nullable|integer|exists:tasks,id',
            'archivo'        => 'required|file|max:30720',
        ], [], ['archivo' => 'archivo de ruta', 'tarea_id' => 'orden']);

        try {
            $recorrido = $this->import->crear(
                TalentoColaborador::findOrFail($datos['colaborador_id']), $request->file('archivo'), $datos, auth()->user()
            );
        } catch (InvalidArgumentException $e) {
            return $this->falla($e);
        }

        return response()->json($this->detalle($recorrido), 201);
    }

    public function show(int $id)
    {
        $this->puedeVer();

        return response()->json($this->detalle(TalentoRecorrido::findOrFail($id)));
    }

    private function detalle(TalentoRecorrido $recorrido): array
    {
        $recorrido->load(['colaborador.user:id,name,father_last_name', 'hallazgos.fotos', 'hallazgos.categoria:id,nombre']);
        $hallazgos = $recorrido->hallazgos->sortBy(fn ($h) => [$h->capturado_at === null ? 1 : 0, $h->capturado_at?->getTimestamp() ?? 0, $h->id])->values();

        return [
            'recorrido'  => $recorrido->makeHidden('hallazgos'),
            'hallazgos'  => $hallazgos->map(fn (TalentoHallazgo $h) => [
                ...$h->only(['id', 'categoria_id', 'severidad', 'descripcion', 'lat', 'lng', 'precision_m', 'fuente_ubicacion', 'estado', 'motivo_descarte']),
                'capturado_at' => $h->capturado_at?->format('Y-m-d H:i:s'),
                'categoria'    => $h->categoria?->nombre,
                'faltantes'    => $h->faltantes(),
                'fotos'        => $h->fotos->map(fn (TalentoHallazgoFoto $f) => [
                    'id' => $f->id, 'nombre' => $f->nombre_original, 'ancho' => $f->ancho, 'alto' => $f->alto,
                    'url' => "/talento/api/recorridos/{$recorrido->id}/fotos/{$f->id}",
                ])->values(),
            ]),
            'categorias' => TalentoHallazgoCategoria::orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'severidad_sugerida', 'activo']),
            'puede_editar' => $recorrido->esBorrador() && auth()->user()->can('talento.recorridos.manage'),
        ];
    }

    public function update(Request $request, int $id)
    {
        $recorrido = $this->editable($id);
        $datos = $request->validate([
            'titulo'        => 'sometimes|nullable|string|max:160',
            'modo'          => ['sometimes', Rule::in(array_keys(TalentoRecorrido::MODOS))],
            'tarea_id'      => 'sometimes|nullable|integer|exists:tasks,id',
            'recorte_desde' => 'sometimes|integer|min:0',
            'recorte_hasta' => 'sometimes|integer|min:1',
        ]);

        try {
            $recorrido->fill(collect($datos)->only(['titulo', 'tarea_id', 'modo'])->all());
            $recorrido->updated_by = auth()->id();
            if ($request->has('recorte_desde') || $request->has('recorte_hasta') || $recorrido->isDirty('modo')) {
                $this->import->recortar(
                    $recorrido,
                    (int) ($datos['recorte_desde'] ?? $recorrido->recorte_desde ?? 0),
                    (int) ($datos['recorte_hasta'] ?? $recorrido->recorte_hasta ?? $recorrido->puntos_total - 1),
                    auth()->user()
                );
            } else {
                $recorrido->save();
            }
        } catch (InvalidArgumentException $e) {
            return $this->falla($e);
        }

        return response()->json($this->detalle($recorrido->fresh()));
    }

    public function destroy(int $id)
    {
        $recorrido = $this->editable($id);
        $recorrido->updated_by = auth()->id();
        $recorrido->save();
        $recorrido->delete();

        return response()->json(['ok' => true]);
    }

    public function subirFoto(Request $request, int $id)
    {
        $recorrido = $this->editable($id);
        $request->validate(['foto' => 'required|file|mimes:jpg,jpeg,png,webp|max:30720'], [], ['foto' => 'foto']);

        try {
            $r = $this->import->agregarFoto($recorrido, $request->file('foto'), auth()->user());
        } catch (InvalidArgumentException $e) {
            return $this->falla($e);
        }

        return response()->json([
            'hallazgo_id' => $r['hallazgo']->id,
            'duplicada'   => $r['duplicada'],
            'ubicacion'   => $r['hallazgo']->fuente_ubicacion,
        ], $r['duplicada'] ? 200 : 201);
    }

    public function actualizarHallazgo(Request $request, int $id, int $hid)
    {
        $recorrido = $this->editable($id);
        $hallazgo = $this->hallazgoDe($recorrido, $hid);
        $datos = $request->validate([
            'categoria_id' => ['sometimes', 'nullable', 'integer', Rule::exists('talento_hallazgo_categorias', 'id')
                ->where(fn ($q) => $q->where('activo', true)->orWhere('id', $hallazgo->categoria_id ?? 0))],
            'severidad'    => ['sometimes', 'nullable', Rule::in(array_keys(TalentoHallazgo::SEVERIDADES))],
            'descripcion'  => 'sometimes|nullable|string|max:2000',
            'lat'          => 'sometimes|nullable|numeric|between:-90,90|required_with:lng',
            'lng'          => 'sometimes|nullable|numeric|between:-180,180|required_with:lat',
        ], [], ['categoria_id' => 'categoría', 'lat' => 'latitud', 'lng' => 'longitud']);

        $hallazgo->fill(collect($datos)->only(['categoria_id', 'severidad', 'descripcion'])->all());
        if (array_key_exists('lat', $datos)) {
            $hallazgo->fill([
                'lat' => $datos['lat'], 'lng' => $datos['lng'],
                'fuente_ubicacion' => $datos['lat'] === null ? null : 'manual', 'precision_m' => null,
            ]);
        }
        if (array_key_exists('categoria_id', $datos) && $datos['categoria_id'] && ! $hallazgo->severidad) {
            $hallazgo->severidad = TalentoHallazgoCategoria::whereKey($datos['categoria_id'])->value('severidad_sugerida');
        }
        $hallazgo->updated_by = auth()->id();
        $hallazgo->save();

        return response()->json($this->detalle($recorrido));
    }

    public function eliminarHallazgo(int $id, int $hid)
    {
        $recorrido = $this->editable($id);
        $this->import->eliminar($this->hallazgoDe($recorrido, $hid));

        return response()->json($this->detalle($recorrido));
    }

    public function unirHallazgo(Request $request, int $id, int $hid)
    {
        $recorrido = $this->editable($id);
        $destinoId = (int) $request->validate(['destino_id' => 'required|integer'])['destino_id'];
        abort_if($destinoId === $hid, 422, 'Elige otro hallazgo para unir.');
        $this->import->unir($this->hallazgoDe($recorrido, $hid), $this->hallazgoDe($recorrido, $destinoId), auth()->user());

        return response()->json($this->detalle($recorrido));
    }

    public function enviar(int $id)
    {
        $recorrido = $this->editable($id);
        $incompletos = $recorrido->hallazgos()->get()
            ->mapWithKeys(fn (TalentoHallazgo $h) => [$h->id => $h->faltantes()])
            ->filter();
        if ($incompletos->isNotEmpty()) {
            return response()->json([
                'error'  => 'Antes de enviar, completa ' . $incompletos->count() . ' ' . ($incompletos->count() === 1 ? 'hallazgo' : 'hallazgos')
                    . ' (categoría, severidad, descripción y ubicación).',
                'errors' => ['hallazgos' => $incompletos],
            ], 422);
        }
        $recorrido->fill(['estado' => 'enviado', 'enviado_at' => now(), 'enviado_por' => auth()->id(), 'updated_by' => auth()->id()])->save();

        return response()->json($this->detalle($recorrido));
    }

    public function foto(int $id, int $fotoId)
    {
        $this->puedeVer();
        $foto = TalentoHallazgoFoto::whereKey($fotoId)
            ->whereHas('hallazgo', fn ($q) => $q->where('recorrido_id', $id))
            ->firstOrFail();
        abort_unless(Storage::disk(RecorridoImportService::DISCO)->exists($foto->ruta), 404);

        return response()->file(Storage::disk(RecorridoImportService::DISCO)->path($foto->ruta), [
            'Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function original(int $id)
    {
        $this->puedeVer();
        $recorrido = TalentoRecorrido::findOrFail($id);
        abort_unless($recorrido->archivo_ruta && Storage::disk(RecorridoImportService::DISCO)->exists($recorrido->archivo_ruta), 404);

        return Storage::disk(RecorridoImportService::DISCO)->download(
            $recorrido->archivo_ruta, $recorrido->archivo_nombre ?: basename($recorrido->archivo_ruta)
        );
    }

    // ── Catálogo de categorías ─────────────────────────────────────────────

    public function categorias()
    {
        $this->puedeVer();

        return response()->json(TalentoHallazgoCategoria::orderBy('orden')->orderBy('nombre')->get());
    }

    public function guardarCategoria(Request $request, ?int $id = null)
    {
        $this->authorize('talento.recorridos.categorias.manage');
        $categoria = $id ? TalentoHallazgoCategoria::findOrFail($id) : new TalentoHallazgoCategoria(['created_by' => auth()->id()]);
        $datos = $request->validate([
            'nombre'             => ['required', 'string', 'max:120', Rule::unique('talento_hallazgo_categorias', 'nombre')->ignore($id)],
            'descripcion'        => 'nullable|string|max:255',
            'severidad_sugerida' => ['nullable', Rule::in(array_keys(TalentoHallazgo::SEVERIDADES))],
            'activo'             => 'sometimes|boolean',
            'orden'              => 'sometimes|integer|min:0|max:100000',
        ]);
        $categoria->fill($datos + ['updated_by' => auth()->id()]);
        if (! $id && ! isset($datos['orden'])) {
            $categoria->orden = (int) TalentoHallazgoCategoria::max('orden') + 10;
        }
        $categoria->save();

        return response()->json($categoria, $id ? 200 : 201);
    }
}
