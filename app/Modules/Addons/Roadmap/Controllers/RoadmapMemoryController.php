<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\RoadmapItemMemory;
use App\Modules\Addons\Roadmap\Services\RoadmapMemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoadmapMemoryController extends Controller
{
    protected RoadmapMemoryService $svc;

    public function __construct(RoadmapMemoryService $svc)
    {
        $this->svc = $svc;
    }

    // GET /api/roadmap/items/{id}/memory
    public function show(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        return response()->json([
            'item_id' => $id,
            'content' => $this->svc->read($id),
            'memory'  => RoadmapItemMemory::where('roadmap_item_id', $id)->first(),
        ]);
    }

    // GET /api/roadmap/items/{id}/memory/prompt
    public function generatePrompt(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        return response()->json([
            'item_id' => $id,
            'prompt'  => $this->svc->generateContinuationPrompt($id),
        ]);
    }

    // POST /api/roadmap/items/{id}/memory/report
    public function appendReport(int $id, Request $request): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $request->validate([
            'section' => 'required|string',
            'content' => 'required|string',
        ]);

        $this->svc->appendToSection($id, $request->section, $request->content);

        return response()->json(['ok' => true]);
    }

    // POST /api/roadmap/items/{id}/memory/raw
    public function replaceRaw(int $id, Request $request): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $request->validate(['content' => 'required|string']);

        $path = 'roadmap-memory/' . sprintf('item-%03d.md', $id);
        Storage::disk('local')->put($path, $request->content);
        $this->svc->syncIndex($id, $request->content);

        return response()->json(['ok' => true]);
    }
}
