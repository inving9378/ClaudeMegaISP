<?php

namespace App\Modules\Addons\VoIP\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\VoIP\Models\IaBotConfig;
use App\Modules\Addons\VoIP\Models\IaBotConversation;
use App\Modules\Addons\VoIP\Models\IaBotKnowledgeBase;
use App\Modules\Addons\VoIP\Models\IaBotLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IaBotController extends Controller
{
    // ── Vista principal ───────────────────────────────────────────────────────

    public function index()
    {
        $this->authorize('voip.ia-bot.view');
        return view('addon-voip::ia-bot.index');
    }

    // ── Config ────────────────────────────────────────────────────────────────

    public function getConfig(): JsonResponse
    {
        $this->authorize('voip.ia-bot.view');
        return response()->json(IaBotConfig::current());
    }

    public function saveConfig(Request $request): JsonResponse
    {
        $this->authorize('voip.ia-bot.config');

        $data = $request->validate([
            'enabled'              => 'boolean',
            'voice'                => 'string|in:nova,alloy,echo,fable,onyx,shimmer',
            'language'             => 'string|max:20',
            'temperature'          => 'numeric|min:0|max:1',
            'max_turns'            => 'integer|min:3|max:30',
            'max_duration_seconds' => 'integer|min:60|max:900',
            'timeout_seconds'      => 'integer|min:5|max:30',
            'grupo_timbrado_name'  => 'nullable|string|max:100',
            'greeting_customer'    => 'string|max:500',
            'greeting_lead'        => 'string|max:500',
            'system_prompt'        => 'string',
        ]);

        $config = IaBotConfig::current();
        $config->update($data);

        return response()->json(['success' => true, 'data' => $config->fresh()]);
    }

    // ── Conversaciones ────────────────────────────────────────────────────────

    public function conversaciones(Request $request): JsonResponse
    {
        $this->authorize('voip.ia-bot.view');

        $q = IaBotConversation::latest()
            ->when($request->type, fn ($q, $t) => $q->where('conversation_type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->phone, fn ($q, $p) => $q->where('phone_number', 'like', "%{$p}%"));

        $data = $q->paginate(20);

        return response()->json($data);
    }

    // ── Leads ─────────────────────────────────────────────────────────────────

    public function leads(Request $request): JsonResponse
    {
        $this->authorize('voip.ia-bot.leads');

        $q = IaBotLead::with('conversation:id,started_at,phone_number')
            ->latest()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s));

        return response()->json($q->paginate(20));
    }

    public function updateLead(Request $request, IaBotLead $lead): JsonResponse
    {
        $this->authorize('voip.ia-bot.leads');

        $data = $request->validate([
            'status'             => 'in:new,contacted,interested,converted,rejected',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'notes'              => 'nullable|string',
        ]);

        $lead->update($data);

        return response()->json(['success' => true, 'data' => $lead->fresh()]);
    }

    // ── Base de conocimiento ──────────────────────────────────────────────────

    public function kbIndex(): JsonResponse
    {
        $this->authorize('voip.ia-bot.view');
        return response()->json(['data' => IaBotKnowledgeBase::orderBy('category')->orderBy('problem')->get()]);
    }

    public function kbStore(Request $request): JsonResponse
    {
        $this->authorize('voip.ia-bot.kb');

        $data = $request->validate([
            'category'          => 'required|string|max:100',
            'problem'           => 'required|string|max:255',
            'keywords'          => 'nullable|string|max:500',
            'solution_steps'    => 'required|array|min:1',
            'escalate_if_fails' => 'boolean',
        ]);

        $kb = IaBotKnowledgeBase::create($data);

        return response()->json(['success' => true, 'data' => $kb], 201);
    }

    public function kbUpdate(Request $request, IaBotKnowledgeBase $kb): JsonResponse
    {
        $this->authorize('voip.ia-bot.kb');

        $data = $request->validate([
            'category'          => 'string|max:100',
            'problem'           => 'string|max:255',
            'keywords'          => 'nullable|string|max:500',
            'solution_steps'    => 'array|min:1',
            'escalate_if_fails' => 'boolean',
        ]);

        $kb->update($data);

        return response()->json(['success' => true, 'data' => $kb->fresh()]);
    }

    public function kbDestroy(IaBotKnowledgeBase $kb): JsonResponse
    {
        $this->authorize('voip.ia-bot.kb');
        $kb->delete();
        return response()->json(['success' => true]);
    }
}
