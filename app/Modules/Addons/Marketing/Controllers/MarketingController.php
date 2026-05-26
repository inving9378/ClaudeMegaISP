<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Models\Campaign;
use App\Modules\Addons\Marketing\Models\Lead;
use App\Modules\Addons\Marketing\Models\MarketingTemplate;
use App\Modules\Addons\Marketing\Repositories\CampaignRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingController extends Controller
{
    public function __construct(
        private readonly CampaignRepository $repository
    ) {}

    public function index(): View
    {
        return view('addon-marketing::index');
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'campaigns' => $this->repository->stats(),
            'leads'     => [
                'total'       => Lead::count(),
                'new'         => Lead::where('status', 'new')->count(),
                'qualified'   => Lead::where('status', 'qualified')->count(),
                'converted'   => Lead::where('status', 'converted')->count(),
            ],
            'templates' => MarketingTemplate::active()->count(),
        ]);
    }

    public function templates(): JsonResponse
    {
        $templates = MarketingTemplate::active()->orderBy('name')->get();

        return response()->json($templates);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'channel'       => 'required|string|in:whatsapp,facebook,instagram',
            'template_type' => 'required|string|in:campaign,welcome,followup,closing',
            'system_prompt' => 'nullable|string',
            'base_copy'     => 'required|string',
            'variables'     => 'nullable|array',
            'is_active'     => 'boolean',
        ]);

        $template = MarketingTemplate::create($validated);

        return response()->json($template, 201);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $template  = MarketingTemplate::findOrFail($id);
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'description'   => 'nullable|string',
            'channel'       => 'sometimes|string|in:whatsapp,facebook,instagram',
            'template_type' => 'sometimes|string|in:campaign,welcome,followup,closing',
            'system_prompt' => 'nullable|string',
            'base_copy'     => 'sometimes|string',
            'variables'     => 'nullable|array',
            'is_active'     => 'boolean',
        ]);

        $template->update($validated);

        return response()->json($template);
    }

    public function destroyTemplate(int $id): JsonResponse
    {
        MarketingTemplate::findOrFail($id)->delete();

        return response()->json(['message' => 'Plantilla eliminada']);
    }
}
