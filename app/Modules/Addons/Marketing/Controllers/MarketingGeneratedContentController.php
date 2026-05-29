<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\VideoTemplate;
use App\Modules\Addons\Marketing\Jobs\RenderVideoJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketingGeneratedContentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view-video-content')->only(['index', 'show', 'progress']);
        $this->middleware('permission:generate-video-content')->only('render');
        $this->middleware('permission:delete-video-content')->only('destroy');
    }

    public function index(Request $request): JsonResponse
    {
        $query = GeneratedContent::where('company_id', 1)
            ->where('type', 'video');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('template_id')) {
            $query->where('template_id', $request->template_id);
        }

        $items = $query->orderByDesc('created_at')
            ->select([
                'id', 'type', 'status', 'template_id', 'template_type',
                'output_path', 'thumbnail_path', 'generation_cost_usd',
                'generation_metadata', 'render_progress', 'render_stage',
                'created_at', 'updated_at',
            ])
            ->paginate(20);

        return response()->json($items);
    }

    public function show(int $id): JsonResponse
    {
        $item = GeneratedContent::where('company_id', 1)->findOrFail($id);

        return response()->json(['data' => $item]);
    }

    public function progress(int $id): JsonResponse
    {
        $item = GeneratedContent::where('company_id', 1)
            ->select(['id', 'status', 'render_progress', 'render_stage', 'render_log', 'output_path', 'thumbnail_path'])
            ->findOrFail($id);

        return response()->json([
            'id'             => $item->id,
            'status'         => $item->status,
            'render_progress'=> $item->render_progress,
            'render_stage'   => $item->render_stage,
            'render_log'     => $item->render_log,
            'output_path'    => $item->output_path,
            'thumbnail_path' => $item->thumbnail_path,
        ]);
    }

    public function render(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'template_id'             => 'required|integer|exists:marketing_video_templates,id',
            'aspect_ratio'            => 'nullable|string|in:9:16,1:1,16:9',
            'variables'               => 'nullable|array',
            'options'                 => 'nullable|array',
            'overrides.logo_file'     => 'nullable|file|mimes:png,jpg,jpeg,svg,webp|max:5120',
            'overrides.primary_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'overrides.secondary_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }

        $template    = VideoTemplate::where('company_id', 1)->findOrFail($request->template_id);
        $aspectRatio = $request->aspect_ratio ?? ($template->aspect_ratios[0] ?? '9:16');

        // variables may come as JSON string when multipart
        $variables = is_string($request->variables)
            ? json_decode($request->variables, true) ?? []
            : ($request->variables ?? []);

        $options = is_string($request->options)
            ? json_decode($request->options, true) ?? []
            : ($request->options ?? []);

        $options['aspect_ratio'] = $aspectRatio;

        // Merge default_variables
        $defaults  = $template->default_variables ?? [];
        $variables = array_merge($defaults, $variables);

        // Handle logo file override
        if ($request->hasFile('overrides.logo_file')) {
            $logoFile    = $request->file('overrides.logo_file');
            $ext         = strtolower($logoFile->getClientOriginalExtension());
            $tempRelPath = 'marketing/temp/override_logo_' . Str::uuid() . '.' . $ext;
            Storage::disk('local')->putFileAs(
                'marketing/temp',
                $logoFile,
                basename($tempRelPath)
            );
            $variables['_override_logo'] = $tempRelPath;
        }

        // Handle color overrides
        if ($request->filled('overrides.primary_color')) {
            $variables['_override_primary_color'] = $request->input('overrides.primary_color');
        }
        if ($request->filled('overrides.secondary_color')) {
            $variables['_override_secondary_color'] = $request->input('overrides.secondary_color');
        }
        if (isset($request->overrides['primary_color'])) {
            $variables['_override_primary_color'] = $request->overrides['primary_color'];
        }
        if (isset($request->overrides['secondary_color'])) {
            $variables['_override_secondary_color'] = $request->overrides['secondary_color'];
        }

        $record = GeneratedContent::create([
            'company_id'          => 1,
            'type'                => 'video',
            'status'              => 'pending',
            'template_id'         => $template->id,
            'template_type'       => VideoTemplate::class,
            'render_progress'     => 0,
            'render_stage'        => 'queued',
            'render_log'          => [],
            'generation_engine'   => 'mvm_ffmpeg',
            'generation_cost_usd' => 0,
            'generation_metadata' => [
                'template_slug' => $template->slug,
                'aspect_ratio'  => $aspectRatio,
                'variables'     => $variables,
            ],
        ]);

        RenderVideoJob::dispatch($record->id, $template->id, $variables, $options, 1)
            ->onQueue('video-render');

        return response()->json([
            'message'            => 'Render enqueued',
            'generated_content_id' => $record->id,
            'status'             => 'pending',
        ], 202);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = GeneratedContent::where('company_id', 1)->findOrFail($id);

        // Clean up files
        if ($item->output_path) {
            @unlink(storage_path('app/' . $item->output_path));
        }
        if ($item->thumbnail_path) {
            @unlink(storage_path('app/' . $item->thumbnail_path));
        }

        $item->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function download(int $id): mixed
    {
        $item = GeneratedContent::where('company_id', 1)->findOrFail($id);

        if (empty($item->output_path)) {
            return response()->json(['error' => 'No output file'], 404);
        }

        $absPath = storage_path('app/' . $item->output_path);

        if (!file_exists($absPath)) {
            return response()->json(['error' => 'File not found on disk'], 404);
        }

        return response()->download($absPath, basename($absPath));
    }
}
