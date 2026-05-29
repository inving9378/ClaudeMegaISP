<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Resources\Marketing\ConversationResource;
use App\Http\Resources\Marketing\MessageResource;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\Message;
use App\Modules\Addons\Marketing\Jobs\SendOutboundMessageJob;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MarketingConversationController extends Controller
{
    // GET /api/marketing/conversations
    public function index(Request $request)
    {
        $query = Conversation::with(['lead', 'channel', 'assignedUser'])
            ->orderByDesc('last_message_at');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($channelId = $request->get('channel_id')) {
            $query->where('channel_id', $channelId);
        }
        if ($request->get('assigned_user_id') !== null) {
            $query->where('assigned_user_id', $request->get('assigned_user_id'));
        }
        if ($request->boolean('unread_only')) {
            $query->where('unread_count', '>', 0);
        }
        if ($request->get('ai_handled') !== null) {
            $query->where('ai_handled', $request->boolean('ai_handled'));
        }
        if ($search = $request->get('search')) {
            $query->whereHas('lead', fn ($q) => $q->where('full_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('whatsapp', 'like', "%{$search}%"));
        }

        $perPage = min($request->get('per_page', 30), 100);
        $result  = $query->paginate($perPage);

        return ConversationResource::collection($result);
    }

    // GET /api/marketing/conversations/{id}
    public function show(int $id)
    {
        $conv = Conversation::with(['lead', 'channel', 'assignedUser'])->findOrFail($id);
        return new ConversationResource($conv);
    }

    // GET /api/marketing/conversations/{id}/messages
    public function messages(Request $request, int $id)
    {
        $conv  = Conversation::findOrFail($id);
        $limit = min($request->get('limit', 50), 100);

        $query = Message::where('conversation_id', $conv->id)
            ->orderByDesc('sent_at');

        if ($beforeId = $request->get('before_id')) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->limit($limit)->get()->reverse()->values();

        return MessageResource::collection($messages);
    }

    // POST /api/marketing/conversations/{id}/send-message
    public function sendMessage(Request $request, int $id)
    {
        $request->validate(['text' => 'required|string|max:4096']);
        $conv = Conversation::findOrFail($id);

        SendOutboundMessageJob::dispatch(
            $conv->id,
            $request->text,
            'human_user',
            auth()->id()
        )->onQueue('default');

        return response()->json(['queued' => true], 202);
    }

    // POST /api/marketing/conversations/{id}/toggle-ai
    public function toggleAi(int $id)
    {
        $conv = Conversation::findOrFail($id);
        $conv->update(['ai_handled' => !$conv->ai_handled]);
        return response()->json(['ai_handled' => $conv->fresh()->ai_handled]);
    }

    // POST /api/marketing/conversations/{id}/assign
    public function assign(Request $request, int $id)
    {
        $request->validate(['user_id' => 'nullable|integer']);
        $conv = Conversation::findOrFail($id);
        $conv->update([
            'assigned_user_id' => $request->get('user_id'),
            'ai_handled'       => false,
            'status'           => 'human_review',
        ]);
        return response()->json(['success' => true]);
    }

    // POST /api/marketing/conversations/{id}/close
    public function close(int $id)
    {
        $conv = Conversation::findOrFail($id);
        $conv->update(['status' => 'closed']);
        return response()->json(['success' => true]);
    }

    // POST /api/marketing/conversations/{id}/mark-as-read
    public function markAsRead(int $id)
    {
        $conv = Conversation::findOrFail($id);
        $conv->update(['unread_count' => 0]);
        return response()->json(['success' => true]);
    }
}
