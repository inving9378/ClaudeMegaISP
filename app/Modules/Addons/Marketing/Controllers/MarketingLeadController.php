<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketing\LeadResource;
use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadActivity;
use App\Modules\Addons\Marketing\Jobs\ScoreLeadJob;
use Illuminate\Http\Request;

class MarketingLeadController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view-leads')->only(['index', 'show', 'activities']);
        $this->middleware('permission:create-leads')->only('store');
        $this->middleware('permission:update-leads')->only('update');
        $this->middleware('permission:delete-leads')->only('destroy');
        $this->middleware('permission:assign-leads')->only('assign');
        $this->middleware('permission:score-leads')->only('triggerScoring');
    }

    public function index(Request $request)
    {
        $query = Lead::with(['source', 'assignedUser', 'leadForm'])
            ->withCount('activities')
            ->where('company_id', 1);

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('source_id')) {
            $query->where('source_id', $request->source_id);
        }
        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->assigned_user_id);
        }
        if ($request->filled('score_min')) {
            $query->where('score', '>=', $request->score_min);
        }
        if ($request->filled('score_max')) {
            $query->where('score', '<=', $request->score_max);
        }
        if ($request->filled('captured_from')) {
            $query->whereDate('captured_at', '>=', $request->captured_from);
        }
        if ($request->filled('captured_to')) {
            $query->whereDate('captured_at', '<=', $request->captured_to);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('full_name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('address', 'like', "%{$s}%");
            });
        }
        if ($request->filled('tags')) {
            foreach ((array) $request->tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        // Ordenamiento
        $sortable = ['captured_at', 'score', 'last_activity_at', 'full_name'];
        $sort  = in_array($request->sort, $sortable) ? $request->sort : 'captured_at';
        $order = $request->order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $order);

        $perPage = min((int) ($request->per_page ?? 25), 100);
        $leads   = $query->paginate($perPage);

        return LeadResource::collection($leads);
    }

    public function show(int $id)
    {
        $lead = Lead::with(['source', 'assignedUser', 'leadForm', 'pipelines.stages'])
            ->withCount('activities')
            ->where('company_id', 1)
            ->findOrFail($id);

        return new LeadResource($lead);
    }

    public function store(Request $request)
    {
        $request->validate([
            'source_id' => 'required|exists:marketing_lead_sources,id',
            'full_name' => 'required|string|max:150',
            'email'     => 'nullable|email',
            'phone'     => 'nullable|string|max:30',
        ]);

        $lead = Lead::create(array_merge($request->only([
            'source_id', 'full_name', 'email', 'phone', 'whatsapp',
            'address', 'neighborhood', 'city', 'state', 'postal_code',
            'notes', 'assigned_user_id', 'status',
        ]), [
            'company_id'  => 1,
            'status'      => $request->status ?? 'new',
            'captured_at' => now(),
        ]));

        ScoreLeadJob::dispatch($lead->id)->onQueue('default');

        return (new LeadResource($lead->load('source', 'assignedUser')))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $lead = Lead::where('company_id', 1)->findOrFail($id);

        $lead->update($request->only([
            'full_name', 'email', 'phone', 'whatsapp', 'address',
            'neighborhood', 'city', 'state', 'postal_code', 'notes',
            'status', 'lost_reason', 'plan_interested_id', 'tags',
        ]));

        return new LeadResource($lead->fresh(['source', 'assignedUser']));
    }

    public function destroy(int $id)
    {
        $lead = Lead::where('company_id', 1)->findOrFail($id);
        $lead->delete();
        return response()->json(['success' => true]);
    }

    public function assign(Request $request, int $id)
    {
        $request->validate(['user_id' => 'required|integer']);
        $lead = Lead::where('company_id', 1)->findOrFail($id);
        $lead->update(['assigned_user_id' => $request->user_id]);
        return response()->json(['success' => true]);
    }

    public function triggerScoring(int $id)
    {
        $lead = Lead::where('company_id', 1)->findOrFail($id);
        ScoreLeadJob::dispatch($lead->id)->onQueue('default');
        return response()->json(['success' => true, 'message' => 'Scoring encolado'], 202);
    }

    public function activities(int $id)
    {
        $lead = Lead::where('company_id', 1)->findOrFail($id);
        $activities = $lead->activities()
            ->with('user', 'channel')
            ->orderBy('happened_at', 'desc')
            ->paginate(30);

        return response()->json($activities);
    }
}
