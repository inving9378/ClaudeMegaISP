<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WarRoom\Models\ActionItem;
use App\Modules\Addons\WarRoom\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ActionItem::with(['assignee', 'meeting']);

        if ($request->meeting_id) {
            $query->where('meeting_id', $request->meeting_id);
        }
        if ($request->section_key) {
            $query->forSection($request->section_key);
        }

        return response()->json($query->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('warroom.action_items.assign');

        $request->validate([
            'meeting_id'       => 'required|exists:warroom_meetings,id',
            'section_key'      => 'required|string',
            'description'      => 'required|string|max:500',
            'assignee_user_id' => 'nullable|exists:users,id',
            'priority'         => 'nullable|in:critico,alto,medio,oportunidad,estrategico',
            'priority_label'   => 'nullable|string|max:50',
            'deadline'         => 'nullable|date',
        ]);

        $item = ActionItem::create($request->only([
            'meeting_id', 'section_key', 'description',
            'assignee_user_id', 'priority', 'priority_label', 'deadline',
        ]));

        return response()->json($item->load('assignee'), 201);
    }

    public function update(Request $request, ActionItem $actionItem): JsonResponse
    {
        $this->authorize('warroom.action_items.assign');

        $request->validate([
            'description'      => 'sometimes|string|max:500',
            'assignee_user_id' => 'nullable|exists:users,id',
            'priority'         => 'nullable|in:critico,alto,medio,oportunidad,estrategico',
            'priority_label'   => 'nullable|string|max:50',
            'deadline'         => 'nullable|date',
            'status'           => 'nullable|in:pending,in_progress,completed,cancelled',
        ]);

        $actionItem->update($request->only([
            'description', 'assignee_user_id', 'priority',
            'priority_label', 'deadline', 'status',
        ]));

        return response()->json($actionItem->fresh('assignee'));
    }

    public function destroy(ActionItem $actionItem): JsonResponse
    {
        $this->authorize('warroom.meeting.delete');

        $actionItem->delete();

        return response()->json(null, 204);
    }
}
