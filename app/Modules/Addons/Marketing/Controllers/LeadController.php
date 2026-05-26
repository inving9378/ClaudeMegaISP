<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Models\Lead;
use App\Modules\Addons\Marketing\Services\LeadQualifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(
        private readonly LeadQualifierService $qualifier
    ) {}

    public function table(Request $request): JsonResponse
    {
        $query = Lead::with(['campaign:id,title', 'assignedTo:id,name'])
            ->when($request->filled('campaign_id'), fn ($q) => $q->where('campaign_id', $request->campaign_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->channel))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('contact_name', 'like', "%{$request->search}%")
                    ->orWhere('contact_identifier', 'like', "%{$request->search}%");
            }))
            ->orderByDesc('created_at');

        return response()->json($query->paginate(20));
    }

    public function show(int $id): JsonResponse
    {
        $lead = Lead::with(['campaign:id,title', 'assignedTo:id,name'])->findOrFail($id);

        return response()->json($lead);
    }

    public function qualify(int $id): JsonResponse
    {
        $lead   = Lead::findOrFail($id);
        $result = $this->qualifier->qualify($lead);

        return response()->json([
            'message'        => 'Lead calificado correctamente.',
            'qualification'  => $result,
            'lead'           => $lead->fresh(),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);

        $request->validate([
            'status' => 'required|in:new,contacted,qualified,unqualified,scheduled,converted,lost',
        ]);

        $lead->update(['status' => $request->status]);

        return response()->json($lead);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $lead->update(['assigned_to' => $request->user_id]);
        $lead->load('assignedTo:id,name');

        return response()->json($lead);
    }
}
