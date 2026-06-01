<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoadmapController extends Controller
{
    // GET /api/roadmap/items
    public function index(Request $request): JsonResponse
    {
        $this->authorize('roadmap_view');

        $q = RoadmapItem::ordered();

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('version')) {
            $q->where('target_version', $request->version);
        }

        return response()->json($q->get());
    }

    // POST /api/roadmap/items
    public function store(Request $request): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'priority'       => 'nullable|in:alta,media,baja',
            'target_version' => 'nullable|string|max:20',
            'prompt'         => 'nullable|string',
        ]);

        $data['status']   = 'pending';
        $data['position'] = RoadmapItem::where('status', 'pending')->max('position') + 1;

        // Si no se envían sub-tareas, sembrar las 5 por defecto
        if (! $request->has('subtasks')) {
            $data['subtasks'] = self::defaultSubtasks();
        }

        $item = RoadmapItem::create($data);

        return response()->json($item, 201);
    }

    // PATCH /api/roadmap/items/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);

        $data = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'priority'       => 'nullable|in:alta,media,baja',
            'target_version' => 'nullable|string|max:20',
            'prompt'         => 'nullable|string',
            'position'       => 'sometimes|integer',
        ]);

        $item->update($data);

        return response()->json($item->fresh());
    }

    // POST /api/roadmap/items/{id}/start
    public function start(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);

        if ($item->status === 'in_progress') {
            return response()->json($item);
        }

        $current = RoadmapItem::currentInProgress();
        if ($current && $current->id !== $id) {
            return response()->json([
                'message' => "Ya hay una tarea en progreso: \"{$current->title}\". Termínala antes de empezar otra.",
            ], 422);
        }

        $item->update([
            'status'     => 'in_progress',
            'started_at' => $item->started_at ?? now(),
        ]);

        return response()->json($item->fresh());
    }

    // POST /api/roadmap/items/{id}/complete
    public function complete(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);
        $item->update([
            'status'       => 'done',
            'completed_at' => $item->completed_at ?? now(),
        ]);

        return response()->json($item->fresh());
    }

    // POST /api/roadmap/items/{id}/cancel
    public function cancel(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);
        $item->update(['status' => 'cancelled']);

        return response()->json($item->fresh());
    }

    // DELETE /api/roadmap/items/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        RoadmapItem::findOrFail($id)->delete();

        return response()->json(['deleted' => true]);
    }

    // PATCH /api/roadmap/items/{id}/subtasks — reemplaza la lista completa
    public function updateSubtasks(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);

        $data = $request->validate([
            'subtasks'                  => 'required|array',
            'subtasks.*.title'          => 'required|string|max:255',
            'subtasks.*.completed'      => 'required|boolean',
            'subtasks.*.completed_at'   => 'nullable|string',
        ]);

        $item->update(['subtasks' => $data['subtasks']]);

        return response()->json($item->fresh());
    }

    // POST /api/roadmap/items/{id}/subtasks/{index}/toggle
    public function toggleSubtask(int $id, int $index): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);
        $subtasks = $item->subtasks ?? [];

        if (! isset($subtasks[$index])) {
            return response()->json(['message' => 'Sub-tarea no encontrada.'], 404);
        }

        $subtasks[$index]['completed'] = ! $subtasks[$index]['completed'];
        $subtasks[$index]['completed_at'] = $subtasks[$index]['completed']
            ? now()->toIso8601String()
            : null;

        $item->update(['subtasks' => $subtasks]);

        return response()->json($item->fresh());
    }

    // POST /api/roadmap/items/{id}/log — agrega una entrada
    public function addLog(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);

        $data = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $log = $item->log ?? [];
        $log[] = [
            'text'       => $data['text'],
            'created_at' => now()->toIso8601String(),
        ];

        $item->update(['log' => $log]);

        return response()->json($item->fresh());
    }

    private static function defaultSubtasks(): array
    {
        return [
            ['title' => 'Prompt enviado a Claude Code.',                            'completed' => false, 'completed_at' => null],
            ['title' => 'Reporte/plan recibido y aprobado.',                        'completed' => false, 'completed_at' => null],
            ['title' => 'Implementación reportada por Claude Code.',                'completed' => false, 'completed_at' => null],
            ['title' => 'Verificación server-side (Claude Code).',                  'completed' => false, 'completed_at' => null],
            ['title' => 'Verificación visual (admin, navegador, claro y oscuro).',  'completed' => false, 'completed_at' => null],
        ];
    }
}
