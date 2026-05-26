<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Models\Campaign;
use App\Modules\Addons\Marketing\Repositories\CampaignRepository;
use App\Modules\Addons\Marketing\Services\PublicationSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignRepository          $repository,
        private readonly PublicationSchedulerService $scheduler
    ) {}

    public function index(): View
    {
        return view('addon-marketing::index');
    }

    public function show(int $id): View
    {
        $campaign = Campaign::withCount(['contents', 'leads', 'schedules'])->findOrFail($id);

        return view('addon-marketing::campaigns.show', compact('campaign'));
    }

    public function table(Request $request): JsonResponse
    {
        $campaigns = $this->repository->paginate([
            'status'  => $request->status,
            'search'  => $request->search,
            'channel' => $request->channel,
        ]);

        return response()->json($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'target_zone'    => 'nullable|string|max:255',
            'target_plan_id' => 'nullable|integer',
            'channel'        => 'required|array|min:1',
            'channel.*'      => 'in:whatsapp,facebook,instagram',
            'daily_limit'    => 'integer|min:1|max:500',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
        ]);

        $validated['status'] = 'draft';

        $campaign = Campaign::create($validated);

        return response()->json($campaign, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        if (!$campaign->isEditable()) {
            return response()->json(['error' => 'Solo se pueden editar campañas en borrador o pendientes de aprobación.'], 422);
        }

        $validated = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'target_zone'    => 'nullable|string|max:255',
            'target_plan_id' => 'nullable|integer',
            'channel'        => 'sometimes|array|min:1',
            'channel.*'      => 'in:whatsapp,facebook,instagram',
            'daily_limit'    => 'integer|min:1|max:500',
            'start_date'     => 'sometimes|date',
            'end_date'       => 'sometimes|date',
        ]);

        $campaign->update($validated);

        return response()->json($campaign);
    }

    public function destroy(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status === 'active') {
            return response()->json(['error' => 'No se puede eliminar una campaña activa. Paúsela primero.'], 422);
        }

        $campaign->delete();

        return response()->json(['message' => 'Campaña eliminada']);
    }

    public function approve(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status !== 'pending_approval') {
            return response()->json(['error' => 'La campaña no está pendiente de aprobación.'], 422);
        }

        $campaign->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json($campaign);
    }

    public function pause(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status !== 'active') {
            return response()->json(['error' => 'Solo se pueden pausar campañas activas.'], 422);
        }

        $campaign->update(['status' => 'paused']);

        return response()->json($campaign);
    }

    public function activate(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        if (!in_array($campaign->status, ['approved', 'paused'])) {
            return response()->json(['error' => 'La campaña debe estar aprobada o pausada para activarse.'], 422);
        }

        $campaign->update(['status' => 'active']);
        $scheduledCount = $this->scheduler->scheduleForCampaign($campaign->fresh());

        return response()->json([
            'campaign'  => $campaign,
            'scheduled' => $scheduledCount,
            'message'   => "Campaña activada. {$scheduledCount} envíos programados.",
        ]);
    }
}
