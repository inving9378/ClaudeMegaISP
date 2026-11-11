<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderTemplate;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Support\EsquemaCamposOrden;
use App\Modules\Addons\Talento\Support\ReglasPlantillaAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Item #1200 Fase C — pantalla «Plantillas de orden»: tipos de orden de planta externa y sus
 * plantillas (campos y materiales). Solo planta externa: los tipos de cliente no se tocan aquí.
 *
 * - Nada se borra: tipos y plantillas se desactivan (active = 0).
 * - El formato de los campos lo valida EsquemaCamposOrden (Fase A); ReglasPlantillaAdmin agrega
 *   las claves automáticas y los topes del editor.
 * - Quién y cuándo: BaseModel ya lleva created_by/updated_by y el registro de actividad.
 */
class TalentoWorkOrderTemplateAdminController extends Controller
{
    public const PERMISO = 'talento.work_order_templates.manage';

    /** Nota que acompaña a is_billable bloqueado. */
    public const NOTA_COBRO = 'Hasta definir umbrales dBm y bono (#1203)';

    public function __construct()
    {
        $this->middleware('can:' . self::PERMISO);
    }

    /** Pantalla «Plantillas de orden» (Talento). */
    public function index()
    {
        return view('addon-talento::talento.plantillas_orden');
    }

    // ── Catálogos ───────────────────────────────────────────────────────────────────────────

    public function catalogos(): JsonResponse
    {
        $evidencias = DB::table('talento_evidence_types')->where('active', true)->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'es_lectura_dbm'])
            ->map(fn ($e) => [
                'id'        => $e->id,
                'name'      => $e->name,
                'permitida' => ! $e->es_lectura_dbm,
                'motivo'    => $e->es_lectura_dbm ? self::NOTA_COBRO : null,
            ]);

        return response()->json([
            'proyectos'    => DB::table('projects')->whereNull('deleted_at')->orderBy('title')->get(['id', 'title']),
            'evidencias'   => $evidencias,
            'prioridades'  => TalentoWorkOrderTemplate::PRIORIDADES,
            'tipos_campo'  => EsquemaCamposOrden::TIPOS,
            'tipos_columna' => EsquemaCamposOrden::TIPOS_COLUMNA,
            'tipos_identificador' => EsquemaCamposOrden::TIPOS_IDENTIFICADOR,
            'topes'        => [
                'campos' => ReglasPlantillaAdmin::MAX_CAMPOS, 'etiqueta' => ReglasPlantillaAdmin::MAX_ETIQUETA,
                'unidad' => ReglasPlantillaAdmin::MAX_UNIDAD, 'ayuda' => ReglasPlantillaAdmin::MAX_AYUDA,
                'min_opciones' => ReglasPlantillaAdmin::MIN_OPCIONES, 'max_opciones' => ReglasPlantillaAdmin::MAX_OPCIONES,
                'materiales' => ReglasPlantillaAdmin::MAX_MATERIALES, 'filas_tabla' => EsquemaCamposOrden::MAX_FILAS_TABLA,
            ],
            'nota_cobro'   => self::NOTA_COBRO,
        ]);
    }

    // ── Tipos ───────────────────────────────────────────────────────────────────────────────

    public function tipos(Request $request): JsonResponse
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $tipos = TalentoWorkOrderType::where('area', TalentoWorkOrderType::AREA_PLANTA_EXTERNA)
            ->when($buscar !== '', fn ($q) => $q->where('name', 'like', '%' . addcslashes($buscar, '%_\\') . '%'))
            ->orderBy('name')->get();

        return response()->json($tipos->map(fn ($t) => $this->tipoJson($t))->values());
    }

    public function guardarTipo(Request $request, ?int $id = null): JsonResponse
    {
        $tipo = $id ? $this->tipoPlantaExterna($id) : null;

        $v = Validator::make($request->all(), [
            'name'          => ['required', 'string', 'max:120'],
            'points'        => ['required', 'integer', 'min:0', 'max:1000'],
            'project_id'    => ['required', 'integer'],
            'evidencias'    => ['sometimes', 'array'],
            'evidencias.*'  => ['integer'],
            'active'        => ['sometimes', 'boolean'],
            'is_billable'   => ['sometimes', 'boolean'],
        ], [], ['name' => 'nombre', 'points' => 'puntos', 'project_id' => 'proyecto']);
        $v->after(function ($v) use ($request, $tipo) {
            $nombre = trim((string) $request->input('name'));
            $repetido = TalentoWorkOrderType::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($nombre)])
                ->when($tipo, fn ($q) => $q->where('id', '!=', $tipo->id))->exists();
            if ($nombre !== '' && $repetido) {
                $v->errors()->add('name', 'Ya existe un tipo de orden con ese nombre.');
            }
            if ($request->filled('project_id') && ! DB::table('projects')->whereNull('deleted_at')->where('id', $request->input('project_id'))->exists()) {
                $v->errors()->add('project_id', 'El proyecto no existe.');
            }
            if ($request->boolean('is_billable')) {
                $v->errors()->add('is_billable', 'Un tipo de planta externa no puede cobrarse todavía: ' . self::NOTA_COBRO . '.');
            }
            foreach ((array) $request->input('evidencias', []) as $i => $evId) {
                $ev = DB::table('talento_evidence_types')->where('id', $evId)->first(['active', 'es_lectura_dbm', 'name']);
                if (! $ev || ! $ev->active) {
                    $v->errors()->add("evidencias.$i", 'La evidencia no existe o está inactiva.');
                } elseif ($ev->es_lectura_dbm) {
                    $v->errors()->add("evidencias.$i", "«{$ev->name}» no se permite en planta externa: " . self::NOTA_COBRO . '.');
                }
            }
        });
        if ($v->fails()) {
            return $this->error422('Revisa los datos del tipo.', $v->errors()->toArray());
        }

        $datos = [
            'name'        => trim((string) $request->input('name')),
            'points'      => (int) $request->input('points'),
            'project_id'  => (int) $request->input('project_id'),
            'active'      => $request->has('active') ? $request->boolean('active') : ($tipo?->active ?? true),
            // Fijos en esta pantalla: sin la categoría 'campo', Task::saving y el servicio unificado
            // ignorarían el tipo; el cobro queda apagado hasta #1203.
            'area'        => TalentoWorkOrderType::AREA_PLANTA_EXTERNA,
            'category'    => 'campo',
            'is_billable' => false,
        ];

        $tipo = DB::transaction(function () use ($tipo, $datos, $request) {
            if (! $tipo) {
                $tipo = new TalentoWorkOrderType();
                $tipo->requires_validation = true;
                $tipo->inicia_garantia = false;
            }
            $tipo->fill($datos)->save();
            if ($request->has('evidencias')) {
                $this->sincronizarEvidencias($tipo, array_map('intval', (array) $request->input('evidencias')));
            }

            return $tipo;
        });

        return response()->json($this->tipoJson($tipo->fresh()), $id ? 200 : 201);
    }

    public function activarTipo(Request $request, int $id): JsonResponse
    {
        $tipo = $this->tipoPlantaExterna($id);
        $request->validate(['active' => ['required', 'boolean']]);
        $tipo->active = $request->boolean('active');
        $tipo->save();

        return response()->json($this->tipoJson($tipo->fresh()));
    }

    // ── Plantillas ──────────────────────────────────────────────────────────────────────────

    public function plantillas(int $id): JsonResponse
    {
        $tipo = $this->tipoPlantaExterna($id);
        $plantillas = TalentoWorkOrderTemplate::where('type_id', $tipo->id)->orderBy('sort_order')->orderBy('id')->get();

        return response()->json($plantillas->map(fn ($p) => $this->resumenPlantilla($p))->values());
    }

    public function plantilla(int $id): JsonResponse
    {
        $p = $this->plantillaPlantaExterna($id);

        return response()->json($this->plantillaJson($p));
    }

    public function guardarPlantilla(Request $request, ?int $id = null): JsonResponse
    {
        $plantilla = $id ? $this->plantillaPlantaExterna($id) : null;
        $tipo = $plantilla ? $plantilla->type : $this->tipoPlantaExterna((int) $request->input('type_id'));

        [$datos, $errores] = $this->validarPlantilla($request, $tipo, $plantilla);
        if ($errores) {
            return $this->error422('Revisa los datos de la plantilla.', $errores);
        }

        if (! $plantilla) {
            $plantilla = new TalentoWorkOrderTemplate(['type_id' => $tipo->id]);
            $plantilla->sort_order = (int) TalentoWorkOrderTemplate::where('type_id', $tipo->id)->max('sort_order') + 10;
            $plantilla->active = $request->has('active') ? $request->boolean('active') : true;
        } elseif ($request->has('active')) {
            $plantilla->active = $request->boolean('active');
        }
        $plantilla->fill($datos)->save();

        return response()->json($this->plantillaJson($plantilla->fresh()), $id ? 200 : 201);
    }

    public function activarPlantilla(Request $request, int $id): JsonResponse
    {
        $p = $this->plantillaPlantaExterna($id);
        $request->validate(['active' => ['required', 'boolean']]);
        $p->active = $request->boolean('active');
        $p->save();

        return response()->json($this->resumenPlantilla($p->fresh()));
    }

    public function duplicarPlantilla(int $id): JsonResponse
    {
        $origen = $this->plantillaPlantaExterna($id);
        $nombre = $this->nombreLibre($origen->type_id, $origen->name . ' (copia)');

        $copia = new TalentoWorkOrderTemplate([
            'type_id'           => $origen->type_id,
            'name'              => $nombre,
            'title'             => $origen->title,
            'description'       => $origen->description,
            'priority'          => $origen->priority,
            'estimated_minutes' => $origen->estimated_minutes,
            'fields'            => $origen->fields,
            'checklist'         => $origen->checklist,
        ]);
        $copia->active = false; // la copia nace inactiva: se revisa antes de ofrecerla
        $copia->sort_order = (int) TalentoWorkOrderTemplate::where('type_id', $origen->type_id)->max('sort_order') + 10;
        $copia->save();

        return response()->json($this->plantillaJson($copia->fresh()), 201);
    }

    /** Sube o baja una plantilla un lugar dentro de su tipo. */
    public function moverPlantilla(Request $request, int $id): JsonResponse
    {
        $p = $this->plantillaPlantaExterna($id);
        $request->validate(['direccion' => ['required', 'in:arriba,abajo']]);

        DB::transaction(function () use ($p, $request) {
            $lista = TalentoWorkOrderTemplate::where('type_id', $p->type_id)->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get()->values();
            $i = $lista->search(fn ($x) => $x->id === $p->id);
            $j = $request->input('direccion') === 'arriba' ? $i - 1 : $i + 1;
            if ($i === false || $j < 0 || $j >= $lista->count()) {
                return;
            }
            $ordenados = $lista->all();
            [$ordenados[$i], $ordenados[$j]] = [$ordenados[$j], $ordenados[$i]];
            foreach ($ordenados as $pos => $tpl) {
                if ($tpl->sort_order !== ($pos + 1) * 10) {
                    $tpl->sort_order = ($pos + 1) * 10;
                    $tpl->save();
                }
            }
        });

        return $this->plantillas($p->type_id);
    }

    /**
     * Valida sin guardar y devuelve lo que vería quien crea la orden y el técnico: los campos ya
     * normalizados, el título y el texto de la descripción (el mismo de la app y el portal).
     */
    public function vistaPrevia(Request $request): JsonResponse
    {
        $plantilla = $request->filled('plantilla_id') ? $this->plantillaPlantaExterna((int) $request->input('plantilla_id')) : null;
        $tipoNombre = $plantilla?->type?->name
            ?? ($request->filled('type_id') ? TalentoWorkOrderType::find((int) $request->input('type_id'))?->name : null)
            ?? 'Tipo de orden';

        $r = ReglasPlantillaAdmin::normalizar(
            (array) $request->input('fields', []),
            (array) $request->input('checklist', []),
            $plantilla ? (array) $plantilla->fields : []
        );
        $valores = is_array($request->input('valores')) ? $request->input('valores') : [];
        $conocidas = array_column($r['fields'], 'key');
        $valores = array_intersect_key($valores, array_flip($conocidas));

        return response()->json([
            'fields'      => $r['fields'],
            'checklist'   => $r['checklist'],
            'errors'      => $r['errors'],
            'titulo'      => EsquemaCamposOrden::titulo($tipoNombre, (string) $request->input('title', ''), $r['fields'], $valores),
            'descripcion' => EsquemaCamposOrden::descripcion($r['fields'], $valores, (string) $request->input('description', ''), $r['checklist'], null),
        ]);
    }

    // ── Exportar / importar (C5) ────────────────────────────────────────────────────────────

    public const FORMATO_EXPORTACION = 'megaisp.plantilla_orden';

    /**
     * JSON de una plantilla para llevarla a otro sistema (p. ej. de dev a producción). Sin ids:
     * el tipo, el proyecto y las evidencias van por nombre; sin datos de clientes ni de usuarios.
     */
    public function exportar(int $id)
    {
        $p = $this->plantillaPlantaExterna($id);
        $tipo = $p->type;
        $json = [
            'formato'   => self::FORMATO_EXPORTACION,
            'version'   => 1,
            'exportado' => now()->toDateString(),
            'tipo'      => [
                'nombre'     => $tipo->name,
                'puntos'     => (int) $tipo->points,
                'proyecto'   => $tipo->project_id ? DB::table('projects')->where('id', $tipo->project_id)->value('title') : null,
                'evidencias' => DB::table('talento_ot_type_evidence_requirements as r')
                    ->join('talento_evidence_types as e', 'e.id', '=', 'r.evidence_type_id')
                    ->where('r.ot_type_id', $tipo->id)->whereNull('r.condition')->orderBy('e.name')->pluck('e.name')->values(),
            ],
            'plantilla' => [
                'nombre'          => $p->name,
                'titulo'          => $p->title,
                'prioridad'       => $p->priority,
                'tiempo_estimado' => $p->estimated_minutes,
                'descripcion'     => $p->description,
                'campos'          => array_values((array) $p->fields),
                'materiales'      => array_values((array) $p->checklist),
            ],
        ];
        $archivo = 'plantilla-' . (\Illuminate\Support\Str::slug($tipo->name . ' ' . $p->name) ?: 'orden') . '.json';

        return response()->json($json, 200, ['Content-Disposition' => 'attachment; filename="' . $archivo . '"'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Crea la plantilla del JSON exportado, INACTIVA, para revisarla y activarla a mano. El tipo y el
     * proyecto se buscan por nombre; si alguno no existe, no se crea nada.
     */
    public function importar(Request $request): JsonResponse
    {
        $j = $request->input('archivo');
        if (! is_array($j) || ($j['formato'] ?? null) !== self::FORMATO_EXPORTACION || (int) ($j['version'] ?? 0) !== 1
            || ! is_array($j['tipo'] ?? null) || ! is_array($j['plantilla'] ?? null)) {
            return $this->error422('El archivo no es una plantilla exportada de esta pantalla.', ['archivo' => 'Formato no reconocido.']);
        }
        $t = $j['tipo'];
        $p = $j['plantilla'];

        $nombreTipo = trim((string) ($t['nombre'] ?? ''));
        $tipo = TalentoWorkOrderType::where('area', TalentoWorkOrderType::AREA_PLANTA_EXTERNA)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($nombreTipo)])->first();
        if (! $tipo) {
            return $this->error422("No existe el tipo de planta externa «{$nombreTipo}» en este sistema. Créalo primero y vuelve a importar.", ['tipo' => 'Tipo no encontrado.']);
        }

        $notas = [];
        $nombreProyecto = trim((string) ($t['proyecto'] ?? ''));
        if ($nombreProyecto !== '') {
            $proyectoId = DB::table('projects')->whereNull('deleted_at')->get(['id', 'title'])
                ->first(fn ($x) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $x->title))) === mb_strtolower(preg_replace('/\s+/u', ' ', $nombreProyecto)))?->id;
            if (! $proyectoId) {
                return $this->error422("No existe el proyecto «{$nombreProyecto}» en este sistema. Créalo primero y vuelve a importar.", ['proyecto' => 'Proyecto no encontrado.']);
            }
            if ((int) $tipo->project_id !== (int) $proyectoId) {
                $notas[] = "El tipo «{$tipo->name}» de este sistema usa otro proyecto; la plantilla queda en el tipo tal como está.";
            }
        }

        $campos = is_array($p['campos'] ?? null) ? $p['campos'] : [];
        $materiales = is_array($p['materiales'] ?? null) ? $p['materiales'] : [];
        // Las claves del archivo se conservan (se pasan como «guardadas»).
        $r = ReglasPlantillaAdmin::normalizar($campos, $materiales, $campos);
        $prioridad = in_array($p['prioridad'] ?? null, TalentoWorkOrderTemplate::PRIORIDADES, true) ? $p['prioridad'] : 'Media';
        $minutos = isset($p['tiempo_estimado']) && is_numeric($p['tiempo_estimado']) && $p['tiempo_estimado'] >= 1 && $p['tiempo_estimado'] <= 1440 ? (int) $p['tiempo_estimado'] : null;
        $nombre = mb_substr(trim((string) ($p['nombre'] ?? '')), 0, 150);
        if ($nombre === '') {
            $r['errors']['nombre'] = 'La plantilla del archivo no trae nombre.';
        }
        if ($r['errors']) {
            return $this->error422('La plantilla del archivo no es válida en este sistema.', $r['errors']);
        }

        $plantilla = DB::transaction(function () use ($tipo, $p, $r, $prioridad, $minutos, $nombre) {
            $existe = TalentoWorkOrderTemplate::withTrashed()->where('type_id', $tipo->id)->where('name', $nombre)->exists();
            $nueva = new TalentoWorkOrderTemplate([
                'type_id'           => $tipo->id,
                'name'              => $existe ? $this->nombreLibre($tipo->id, $nombre . ' (importada)') : $nombre,
                'title'             => mb_substr(trim((string) ($p['titulo'] ?? '')), 0, 160) ?: $nombre,
                'description'       => mb_substr(trim((string) ($p['descripcion'] ?? '')), 0, 5000) ?: null,
                'priority'          => $prioridad,
                'estimated_minutes' => $minutos,
                'fields'            => $r['fields'],
                'checklist'         => $r['checklist'],
            ]);
            $nueva->active = false; // se revisa y se activa a mano
            $nueva->sort_order = (int) TalentoWorkOrderTemplate::where('type_id', $tipo->id)->max('sort_order') + 10;
            $nueva->save();

            return $nueva;
        });

        return response()->json(['plantilla' => $this->plantillaJson($plantilla->fresh()), 'notas' => $notas], 201);
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────────────────────

    /** @return array{0: array, 1: array<string, mixed>} datos listos para guardar y errores */
    private function validarPlantilla(Request $request, TalentoWorkOrderType $tipo, ?TalentoWorkOrderTemplate $plantilla): array
    {
        $v = Validator::make($request->all(), [
            'name'              => ['required', 'string', 'max:160'],
            'title'             => ['nullable', 'string', 'max:160'],
            'priority'          => ['required', 'in:' . implode(',', TalentoWorkOrderTemplate::PRIORIDADES)],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'fields'            => ['present', 'array'],
            'checklist'         => ['sometimes', 'array'],
            'active'            => ['sometimes', 'boolean'],
        ], [], ['name' => 'nombre', 'title' => 'título', 'priority' => 'prioridad', 'estimated_minutes' => 'tiempo estimado', 'description' => 'descripción']);
        $errores = $v->errors()->toArray();

        $nombre = trim((string) $request->input('name'));
        if ($nombre !== '' && TalentoWorkOrderTemplate::withTrashed()->where('type_id', $tipo->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($nombre)])
            ->when($plantilla, fn ($q) => $q->where('id', '!=', $plantilla->id))->exists()) {
            $errores['name'] = ['Este tipo ya tiene una plantilla con ese nombre.'];
        }

        $r = ReglasPlantillaAdmin::normalizar(
            (array) $request->input('fields', []),
            (array) $request->input('checklist', []),
            $plantilla ? (array) $plantilla->fields : []
        );
        $errores = $errores + $r['errors'];

        $titulo = trim((string) $request->input('title', ''));

        return [[
            'name'              => $nombre,
            'title'             => $titulo !== '' ? $titulo : $nombre,
            'priority'          => $request->input('priority'),
            'estimated_minutes' => $request->filled('estimated_minutes') ? (int) $request->input('estimated_minutes') : null,
            'description'       => trim((string) $request->input('description', '')) ?: null,
            'fields'            => $r['fields'],
            'checklist'         => $r['checklist'],
        ], $errores];
    }

    private function sincronizarEvidencias(TalentoWorkOrderType $tipo, array $ids): void
    {
        $ids = array_values(array_unique($ids));
        $antes = DB::table('talento_ot_type_evidence_requirements')->where('ot_type_id', $tipo->id)->whereNull('condition')
            ->pluck('evidence_type_id')->map(fn ($x) => (int) $x)->all();

        $quitar = array_diff($antes, $ids);
        $agregar = array_diff($ids, $antes);
        if (! $quitar && ! $agregar) {
            return;
        }
        if ($quitar) {
            DB::table('talento_ot_type_evidence_requirements')->where('ot_type_id', $tipo->id)->whereNull('condition')
                ->whereIn('evidence_type_id', $quitar)->delete();
        }
        foreach ($agregar as $evId) {
            DB::table('talento_ot_type_evidence_requirements')->insert([
                'ot_type_id' => $tipo->id, 'evidence_type_id' => $evId, 'condition' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Rastro de quién y cuándo, en el mismo registro de actividad que el resto de los cambios.
        activity()->performedOn($tipo)->causedBy(auth()->user())
            ->withProperties(['antes' => array_values($antes), 'despues' => $ids])
            ->log('Evidencias obligatorias del tipo de orden actualizadas');
    }

    private function tipoPlantaExterna(int $id): TalentoWorkOrderType
    {
        $tipo = TalentoWorkOrderType::find($id);
        abort_if(! $tipo || $tipo->area !== TalentoWorkOrderType::AREA_PLANTA_EXTERNA, 404, 'Tipo de orden de planta externa no encontrado.');

        return $tipo;
    }

    private function plantillaPlantaExterna(int $id): TalentoWorkOrderTemplate
    {
        $p = TalentoWorkOrderTemplate::with('type')->find($id);
        abort_if(! $p || $p->type?->area !== TalentoWorkOrderType::AREA_PLANTA_EXTERNA, 404, 'Plantilla no encontrada.');

        return $p;
    }

    private function nombreLibre(int $tipoId, string $nombre): string
    {
        $base = mb_substr($nombre, 0, 150);
        $libre = $base;
        for ($n = 2; TalentoWorkOrderTemplate::withTrashed()->where('type_id', $tipoId)->where('name', $libre)->exists(); $n++) {
            $libre = $base . ' ' . $n;
        }

        return $libre;
    }

    private function tipoJson(TalentoWorkOrderType $t): array
    {
        $plantillas = TalentoWorkOrderTemplate::where('type_id', $t->id);

        return [
            'id'                 => $t->id,
            'name'               => $t->name,
            'area'               => $t->area,
            'category'           => $t->category,
            'points'             => (int) $t->points,
            'is_billable'        => false,
            'active'             => (bool) $t->active,
            'project_id'         => $t->project_id,
            'project'            => $t->project_id ? DB::table('projects')->where('id', $t->project_id)->value('title') : null,
            'plantillas'         => (clone $plantillas)->count(),
            'plantillas_activas' => (clone $plantillas)->where('active', true)->count(),
            'ordenes'            => DB::table('tasks')->where('talento_type_id', $t->id)->count(),
            'evidencias'         => DB::table('talento_ot_type_evidence_requirements')->where('ot_type_id', $t->id)->whereNull('condition')
                ->pluck('evidence_type_id')->map(fn ($x) => (int) $x)->values(),
        ];
    }

    private function resumenPlantilla(TalentoWorkOrderTemplate $p): array
    {
        $campos = (array) $p->fields;

        return [
            'id'             => $p->id,
            'type_id'        => $p->type_id,
            'name'           => $p->name,
            'title'          => $p->title,
            'campos'         => count($campos),
            'campos_creacion' => count(EsquemaCamposOrden::camposDe($campos, EsquemaCamposOrden::MOMENTO_CREACION)),
            'campos_cierre'  => count(EsquemaCamposOrden::camposDe($campos, EsquemaCamposOrden::MOMENTO_CIERRE)),
            'materiales'     => count((array) $p->checklist),
            'active'         => (bool) $p->active,
            'sort_order'     => (int) $p->sort_order,
            'ordenes'        => DB::table('talento_work_order_data')->where('template_id', $p->id)->count(),
        ];
    }

    private function plantillaJson(TalentoWorkOrderTemplate $p): array
    {
        return $this->resumenPlantilla($p) + [
            'tipo'              => $p->type?->name,
            'description'       => $p->description,
            'priority'          => $p->priority,
            'estimated_minutes' => $p->estimated_minutes,
            'fields'            => array_values((array) $p->fields),
            'checklist'         => array_values((array) $p->checklist),
        ];
    }

    /** 422 con un mensaje por ruta («fields.3.options» → texto), igual para el Validator y la Fase A. */
    private function error422(string $mensaje, array $errores): JsonResponse
    {
        $planos = array_map(fn ($m) => is_array($m) ? (string) reset($m) : (string) $m, $errores);

        return response()->json(['error' => $mensaje, 'errors' => $planos], 422);
    }
}
