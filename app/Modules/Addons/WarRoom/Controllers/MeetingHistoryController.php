<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WarRoom\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Meeting::ended()
            ->with('moderator:id,name')
            ->orderByDesc('ended_at');

        if ($request->filled('from')) {
            $query->whereDate('ended_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('ended_at', '<=', $request->to);
        }
        if ($request->filled('type')) {
            $query->where('meeting_type', $request->type);
        }

        $meetings = $query->get()->map(fn($m) => [
            'id'                       => $m->id,
            'name'                     => $m->name,
            'meeting_type'             => $m->meeting_type ?? 'ordinaria',
            'started_at'               => $m->started_at?->toIso8601String(),
            'ended_at'                 => $m->ended_at?->toIso8601String(),
            'duration_planned_minutes' => $m->duration_planned_minutes,
            'duration_actual_minutes'  => $m->duration_actual_seconds
                ? round($m->duration_actual_seconds / 60, 1) : null,
            'moderator'                => $m->moderator?->only('id', 'name'),
        ]);

        return response()->json($meetings);
    }

    public function show(Meeting $meeting): JsonResponse
    {
        if ($meeting->status !== 'ended') {
            return response()->json(['error' => 'Solo se puede ver el detalle de juntas finalizadas'], 422);
        }

        $meeting->load([
            'sections',
            'notes.author:id,name',
            'notes.section:id,section_key',
            'actionItems.assignee:id,name',
            'attendees.user:id,name',
            'moderator:id,name',
        ]);

        $planned = $meeting->duration_planned_minutes ?: 1;
        $actual  = round(($meeting->duration_actual_seconds ?? 0) / 60, 1);

        $summary = [
            'duration_planned_minutes' => $planned,
            'duration_actual_minutes'  => $actual,
            'duration_delta_pct'       => round(($actual / $planned - 1) * 100, 1),
            'sections_completed'       => $meeting->sections->where('status', 'completed')->count(),
            'sections_skipped'         => $meeting->sections->where('status', 'skipped')->count(),
            'action_items_count'       => $meeting->actionItems->count(),
            'attendance_rate'          => $meeting->attendees->count()
                ? round($meeting->attendees->where('present', true)->count() / $meeting->attendees->count() * 100)
                : 100,
        ];

        return response()->json([
            'meeting'      => $meeting->only([
                'id', 'name', 'meeting_type', 'started_at', 'ended_at',
                'duration_planned_minutes', 'duration_actual_seconds', 'status', 'settings',
            ]),
            'moderator'    => $meeting->moderator?->only('id', 'name'),
            'sections'     => $meeting->sections->map(fn($s) => [
                'id'                   => $s->id,
                'section_key'          => $s->section_key,
                'order_position'       => $s->order_position,
                'status'               => $s->status,
                'time_planned_seconds' => $s->time_planned_seconds,
                'time_actual_seconds'  => $s->time_actual_seconds,
                'started_at'           => $s->started_at?->toIso8601String(),
                'completed_at'         => $s->completed_at?->toIso8601String(),
            ]),
            'notes'        => $meeting->notes->map(fn($n) => [
                'id'          => $n->id,
                'kind'        => $n->kind,
                'body'        => $n->body,
                'section_key' => $n->section?->section_key,
                'author'      => $n->author?->only('id', 'name'),
                'created_at'  => $n->created_at?->toIso8601String(),
            ]),
            'action_items' => $meeting->actionItems->map(fn($a) => [
                'id'          => $a->id,
                'description' => $a->description,
                'priority'    => $a->priority,
                'status'      => $a->status,
                'deadline'    => $a->deadline,
                'section_key' => $a->section_key,
                'assignee'    => $a->assignee?->only('id', 'name'),
            ]),
            'attendees'    => $meeting->attendees->map(fn($att) => [
                'user'      => $att->user?->only('id', 'name'),
                'present'   => (bool) $att->present,
                'confirmed' => (bool) $att->confirmed,
            ]),
            'summary'      => $summary,
        ]);
    }
}
