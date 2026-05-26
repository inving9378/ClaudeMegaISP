<?php

namespace App\Modules\Addons\Manual\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Manual\Models\ManualSection;
use App\Modules\Addons\Manual\Services\ManualGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ManualController extends Controller
{
    public function view()
    {
        return view('addon-manual::index');
    }

    /**
     * Devuelve las secciones del manual (versión más reciente por módulo)
     * agrupadas por el campo `group` de la tabla `modules`.
     */
    public function index(): JsonResponse
    {
        $latest = DB::table('manual_sections as s1')
            ->select('s1.*')
            ->join(DB::raw('(SELECT module_slug, MAX(version) AS max_version FROM manual_sections GROUP BY module_slug) s2'),
                function ($join) {
                    $join->on('s1.module_slug', '=', 's2.module_slug')
                         ->on('s1.version', '=', 's2.max_version');
                })
            ->orderBy('s1.title')
            ->get();

        $modulesMeta = DB::table('modules')->get()->keyBy(function ($m) {
            return preg_replace('/[^A-Za-z0-9]+/', '-', strtolower((string) $m->name));
        });

        $grouped = [];
        foreach ($latest as $section) {
            $meta = $modulesMeta[$section->module_slug] ?? null;
            $group = $meta->group ?? 'General';
            $grouped[$group][] = [
                'id'           => $section->id,
                'module_slug'  => $section->module_slug,
                'title'        => $section->title,
                'content'      => $section->content,
                'version'      => (int) $section->version,
                'generated_at' => $section->generated_at,
            ];
        }

        $payload = [];
        foreach ($grouped as $group => $items) {
            $payload[] = [
                'group'    => $group,
                'sections' => $items,
            ];
        }

        $lastUpdate = ManualSection::max('generated_at');

        return response()->json([
            'groups'      => $payload,
            'last_update' => $lastUpdate,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $section = ManualSection::where('module_slug', $slug)
            ->orderByDesc('version')
            ->first();

        if (!$section) {
            return response()->json(['message' => 'Sección no encontrada.'], 404);
        }

        return response()->json($section);
    }

    public function generate(Request $request, ManualGeneratorService $service): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->can('manual_generate')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $result = $service->generate();

        return response()->json([
            'message'   => 'Manual regenerado.',
            'generated' => $result['generated'],
            'errors'    => $result['errors'],
        ]);
    }
}
