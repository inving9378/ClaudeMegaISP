<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\RoadmapAdjunto;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapAdjuntoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * #9991163 — API de adjuntos del roadmap. Reglas duras:
 *  - El permiso Spatie se verifica ANTES de tocar el disco (punto 3).
 *  - El archivo se resuelve SIEMPRE por id de adjunto; ninguna ruta viene del cliente.
 *  - D5: HTML/SVG salen como descarga forzada `application/octet-stream`, jamás inline (un HTML
 *    subido y renderizado en el dominio de la Torre es XSS con sesión de administrador). Imágenes
 *    y PDF sí pueden ir inline (`?inline=1`) con su MIME real y `nosniff`.
 */
class RoadmapAdjuntoController extends Controller
{
    public function __construct(private RoadmapAdjuntoService $svc)
    {
    }

    // GET /api/roadmap/adjuntos?item_id=&q=&borrados=1
    public function index(Request $request): JsonResponse
    {
        $this->authorize('roadmap.adjuntos.view');

        $q = RoadmapAdjunto::query()->with(['items:id,title,estado_aprobacion', 'subidoPor:id,name,father_last_name,login_user'])
            ->orderByDesc('created_at');
        if ($request->boolean('borrados')) {
            $q->onlyTrashed();
        }
        if ($request->filled('item_id')) {
            $q->whereHas('items', fn ($i) => $i->where('roadmap_items.id', (int) $request->item_id));
        }
        if ($request->filled('q')) {
            $texto = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $request->q) . '%';
            $q->where(fn ($w) => $w->where('nombre_original', 'like', $texto)->orWhere('descripcion', 'like', $texto));
        }
        $adjuntos = $q->limit(500)->get();

        return response()->json([
            'ok'       => true,
            'adjuntos' => $adjuntos->map(fn (RoadmapAdjunto $a) => $a->toResumen())->values(),
            'limites'  => [
                'max_bytes'    => RoadmapAdjunto::MAX_BYTES,
                'extensiones'  => array_keys(RoadmapAdjunto::EXTENSIONES),
                'nunca_inline' => RoadmapAdjunto::NUNCA_INLINE,
            ],
            'puede' => [
                'subir'  => $request->user()->can('roadmap.adjuntos.upload'),
                'borrar' => $request->user()->can('roadmap.adjuntos.delete'),
            ],
        ]);
    }

    // POST /api/roadmap/adjuntos  (multipart: archivo, descripcion?, item_ids[]?)
    public function store(Request $request): JsonResponse
    {
        $this->authorize('roadmap.adjuntos.upload');

        $data = $request->validate([
            'archivo'     => ['required', 'file'],   // ext/MIME/tamaño los juzga el servicio con el motivo exacto
            'descripcion' => ['nullable', 'string', 'max:255'],
            'item_ids'    => ['nullable', 'array', 'max:20'],
            'item_ids.*'  => ['integer', 'exists:roadmap_items,id'],
        ]);

        try {
            $r = $this->svc->subir($request->file('archivo'), $data['descripcion'] ?? null, $request->user()?->id, $data['item_ids'] ?? []);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'mensaje' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json([
            'ok'        => true,
            'adjunto'   => $r['adjunto']->toResumen(),
            'duplicado' => $r['duplicado'],
            'mensaje'   => $r['duplicado']
                ? 'Ese archivo ya estaba subido (mismo contenido): se reutilizó el registro #' . $r['adjunto']->id . '.'
                : 'Archivo subido.',
        ], $r['duplicado'] ? 200 : 201);
    }

    // GET /api/roadmap/adjuntos/{id}/descargar[?inline=1]
    public function descargar(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $this->authorize('roadmap.adjuntos.view');   // ANTES de leer nada del disco

        $adjunto = RoadmapAdjunto::findOrFail($id);   // resuelto por id: la ruta jamás viene del cliente
        if (! $adjunto->existeEnDisco()) {
            return response()->json(['ok' => false, 'mensaje' => 'El archivo ya no está en disco.'], 410);
        }

        $nombre = $adjunto->nombre_original;
        if ($adjunto->debeForzarDescarga()) {
            // D5 — nunca inline, nunca con su MIME: descarga como binario opaco.
            return response()->download($adjunto->rutaAbsoluta(), $nombre, [
                'Content-Type'           => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control'          => 'private, no-store',
            ], 'attachment');
        }

        $inline = $request->boolean('inline') && $adjunto->previsualizable();

        return response()->file($adjunto->rutaAbsoluta(), [
            'Content-Type'           => $inline ? $adjunto->mime : 'application/octet-stream',
            'Content-Disposition'    => ($inline ? 'inline' : 'attachment') . '; filename="' . addslashes($nombre) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    // POST /api/roadmap/adjuntos/{id}/amarrar  {item_id}
    public function amarrar(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap.adjuntos.upload');
        $data = $request->validate(['item_id' => ['required', 'integer', 'exists:roadmap_items,id']]);
        $adjunto = RoadmapAdjunto::findOrFail($id);
        $this->svc->amarrar($adjunto, (int) $data['item_id'], $request->user()?->id);

        return response()->json(['ok' => true, 'adjunto' => $adjunto->fresh(['items', 'subidoPor'])->toResumen(), 'mensaje' => 'Amarrado al item #' . $data['item_id'] . '.']);
    }

    // POST /api/roadmap/adjuntos/{id}/desamarrar  {item_id}
    public function desamarrar(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap.adjuntos.upload');
        $data = $request->validate(['item_id' => ['required', 'integer']]);
        $adjunto = RoadmapAdjunto::findOrFail($id);
        $this->svc->desamarrar($adjunto, (int) $data['item_id']);

        return response()->json(['ok' => true, 'adjunto' => $adjunto->fresh(['items', 'subidoPor'])->toResumen(), 'mensaje' => 'Desamarrado del item #' . $data['item_id'] . '.']);
    }

    // DELETE /api/roadmap/adjuntos/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('roadmap.adjuntos.delete');
        $adjunto = RoadmapAdjunto::findOrFail($id);
        try {
            $this->svc->borrar($adjunto);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'mensaje' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Borrado. El archivo se conserva ' . RoadmapAdjunto::DIAS_RETENCION . ' días por si hay que recuperarlo.']);
    }

    // GET /api/roadmap/items/buscar?q=  — para el buscador de amarre (Fase 2): id o título.
    public function buscarItems(Request $request): JsonResponse
    {
        $this->authorize('roadmap.adjuntos.view');
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['ok' => true, 'items' => []]);
        }
        $items = RoadmapItem::query()
            ->when(ctype_digit($q), fn ($w) => $w->where('id', (int) $q), fn ($w) => $w->where('title', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%'))
            ->orderByDesc('id')->limit(15)->get(['id', 'title', 'estado_aprobacion', 'modulo']);

        return response()->json(['ok' => true, 'items' => $items]);
    }
}
