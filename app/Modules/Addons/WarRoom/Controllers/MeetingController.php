<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WarRoom\Jobs\SendMeetingMinutesJob;
use App\Modules\Addons\WarRoom\Models\Meeting;
use App\Modules\Addons\WarRoom\Models\MeetingAttendee;
use App\Modules\Addons\WarRoom\Models\MeetingSection;
use App\Modules\Addons\WarRoom\Models\SectionTemplate;
use App\Modules\Addons\WarRoom\Services\ModeratorAi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function active(): JsonResponse
    {
        $meeting = Meeting::active()->with(['sections', 'attendees.user', 'moderator'])->latest()->first();
        return response()->json($meeting);
    }

    public function start(Request $request): JsonResponse
    {
        $this->authorize('warroom.meeting.create');

        $request->validate([
            'name'         => 'nullable|string|max:120',
            'meeting_type' => 'nullable|in:ordinaria,acta',
            'scheduled_at' => 'nullable|date',
            'attendee_ids' => 'nullable|array',
            'sections'     => 'nullable|array',
            'settings'     => 'nullable|array',
        ]);

        $templates = SectionTemplate::active()->get();

        $sections    = $request->sections ?? [];
        $totalMins   = collect($sections)->sum('time_minutes') ?: $templates->sum('time_minutes');

        $meeting = Meeting::create([
            'name'                     => $request->name ?? 'Junta operativa',
            'meeting_type'             => $request->meeting_type ?? 'ordinaria',
            'scheduled_at'             => $request->scheduled_at,
            'started_at'               => now(),
            'status'                   => 'in_progress',
            'moderator_user_id'        => auth()->id(),
            'duration_planned_minutes' => $totalMins,
            'settings'                 => array_merge([
                'ai_suggestions'          => true,
                'sound_alerts'            => false,
                'send_minutes_whatsapp'   => true,
            ], $request->settings ?? []),
        ]);

        // Crear secciones desde templates o desde el request
        $sectionsData = $request->sections ?? $templates->map(fn($t) => [
            'key'           => $t->key,
            'time_minutes'  => $t->time_minutes,
            'presenter_id'  => null,
        ])->toArray();

        foreach ($sectionsData as $i => $sec) {
            $key  = $sec['key']          ?? $templates[$i]?->key ?? 'seccion_' . ($i + 1);
            $mins = $sec['time_minutes'] ?? ($templates->firstWhere('key', $key)?->time_minutes ?? 5);

            MeetingSection::create([
                'meeting_id'          => $meeting->id,
                'section_key'         => $key,
                'order_position'      => $i + 1,
                'time_planned_seconds'=> $mins * 60,
                'presenter_user_id'   => $sec['presenter_id'] ?? null,
                'status'              => 'pending',
            ]);
        }

        // Activar primera sección
        $firstSection = $meeting->sections()->first();
        if ($firstSection) {
            $firstSection->update(['status' => 'in_progress', 'started_at' => now()]);
            $meeting->update([
                'current_section_key'        => $firstSection->section_key,
                'current_section_started_at' => now(),
            ]);
        }

        // Registrar asistentes
        foreach (($request->attendee_ids ?? []) as $userId) {
            MeetingAttendee::firstOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $userId],
                ['present' => true, 'confirmed' => true]
            );
        }

        return response()->json($meeting->load(['sections', 'attendees.user']), 201);
    }

    public function nextSection(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('warroom.meeting.moderate');

        if (! $meeting->isActive()) {
            return response()->json(['error' => 'La junta no está activa'], 422);
        }

        $current = $meeting->sections()->where('section_key', $meeting->current_section_key)->first();

        if ($current) {
            $elapsed = (int) $current->started_at?->diffInSeconds(now()) ?? 0;
            $current->update([
                'status'           => 'completed',
                'completed_at'     => now(),
                'time_actual_seconds' => $elapsed,
            ]);
        }

        $next = $meeting->sections()
            ->where('status', 'pending')
            ->orderBy('order_position')
            ->first();

        if (! $next) {
            return $this->end($request, $meeting);
        }

        $next->update(['status' => 'in_progress', 'started_at' => now()]);

        $meeting->update([
            'current_section_key'        => $next->section_key,
            'current_section_started_at' => now(),
        ]);

        return response()->json($meeting->fresh(['sections', 'attendees.user']));
    }

    public function pause(Meeting $meeting): JsonResponse
    {
        $this->authorize('warroom.meeting.moderate');

        $meeting->update(['status' => $meeting->status === 'paused' ? 'in_progress' : 'paused']);

        return response()->json($meeting->fresh());
    }

    public function previousSection(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('warroom.meeting.moderate');

        if (! $meeting->isActive()) {
            return response()->json(['error' => 'La junta no está activa'], 422);
        }

        $current = $meeting->sections()->where('section_key', $meeting->current_section_key)->first();
        if ($current) {
            $current->update(['status' => 'pending', 'started_at' => null, 'completed_at' => null, 'time_actual_seconds' => 0]);
        }

        $prev = $meeting->sections()
            ->where('order_position', '<', $current?->order_position ?? 99)
            ->orderByDesc('order_position')
            ->first();

        if ($prev) {
            $prev->update(['status' => 'in_progress', 'started_at' => now()]);
            $meeting->update([
                'current_section_key'        => $prev->section_key,
                'current_section_started_at' => now(),
            ]);
        }

        return response()->json($meeting->fresh(['sections', 'attendees.user']));
    }

    public function resume(Meeting $meeting): JsonResponse
    {
        $this->authorize('warroom.meeting.moderate');

        if ($meeting->status === 'paused') {
            $meeting->update(['status' => 'in_progress']);
        }

        return response()->json($meeting->fresh());
    }

    public function getSuggestion(Meeting $meeting, ModeratorAi $ai): JsonResponse
    {
        return response()->json([
            'suggestion'   => $ai->getSuggestion($meeting),
            'generated_at' => now(),
        ]);
    }

    public function end(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('warroom.meeting.moderate');

        $current = $meeting->sections()->where('section_key', $meeting->current_section_key)->first();
        if ($current && $current->status === 'in_progress') {
            $elapsed = (int) ($current->started_at?->diffInSeconds(now()) ?? 0);
            $current->update([
                'status'              => 'completed',
                'completed_at'        => now(),
                'time_actual_seconds' => $elapsed,
            ]);
        }

        $meeting->sections()->where('status', 'pending')->update(['status' => 'skipped']);

        $meeting->update([
            'status'                  => 'ended',
            'ended_at'                => now(),
            'duration_actual_seconds' => (int) ($meeting->started_at?->diffInSeconds(now()) ?? 0),
            'current_section_key'     => null,
        ]);

        if ($meeting->settings['send_minutes_whatsapp'] ?? true) {
            dispatch(new SendMeetingMinutesJob($meeting->id));
        }

        $fresh = $meeting->fresh(['sections', 'actionItems.assignee', 'attendees.user']);

        return response()->json([
            'meeting' => $fresh,
            'summary' => $this->buildSummary($fresh),
        ]);
    }

    private function buildSummary(Meeting $meeting): array
    {
        $planned = $meeting->duration_planned_minutes ?: 1;
        $actual  = round(($meeting->duration_actual_seconds ?? 0) / 60, 1);

        return [
            'duration_planned_minutes' => $planned,
            'duration_actual_minutes'  => $actual,
            'duration_delta_pct'       => round(($actual / $planned - 1) * 100, 1),
            'sections_completed'       => $meeting->sections->where('status', 'completed')->count(),
            'sections_skipped'         => $meeting->sections->where('status', 'skipped')->count(),
            'action_items_count'       => $meeting->actionItems->count(),
            'action_items_by_priority' => $meeting->actionItems
                ->groupBy('priority')
                ->map->count(),
            'attendance_rate'          => $meeting->attendees->count()
                ? round($meeting->attendees->where('present', true)->count() / $meeting->attendees->count() * 100)
                : 100,
        ];
    }
}
