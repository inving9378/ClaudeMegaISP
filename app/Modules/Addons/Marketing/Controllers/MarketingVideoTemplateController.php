<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\VideoTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingVideoTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view-video-templates')->only(['index', 'show', 'variables']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = VideoTemplate::where('company_id', 1)->where('active', true);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('aspect_ratio')) {
            $query->whereJsonContains('aspect_ratios', $request->aspect_ratio);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $templates = $query->select([
            'id', 'slug', 'name', 'description', 'category',
            'aspect_ratios', 'duration_seconds', 'preview_thumbnail',
            'requires_voiceover', 'estimated_render_seconds',
            'default_variables', 'engine_version',
        ])->orderBy('category')->orderBy('slug')->get();

        return response()->json([
            'data'  => $templates,
            'total' => $templates->count(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $template = VideoTemplate::where('company_id', 1)->findOrFail($id);

        return response()->json(['data' => $template]);
    }

    public function variables(int $id): JsonResponse
    {
        $template  = VideoTemplate::where('company_id', 1)->findOrFail($id);
        $variables = $template->schema['variables'] ?? [];

        return response()->json([
            'template_id'       => $template->id,
            'template_name'     => $template->name,
            'variables'         => $variables,
            'default_variables' => $template->default_variables ?? [],
        ]);
    }
}
