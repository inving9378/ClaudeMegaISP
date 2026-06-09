<?php

namespace App\Modules\Addons\WarRoom\Jobs;

use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use App\Modules\Addons\WarRoom\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMeetingMinutesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(public readonly int $meetingId) {}

    public function handle(EvolutionApiService $evolution): void
    {
        $meeting = Meeting::with(['attendees.user', 'actionItems.assignee', 'sections'])
            ->find($this->meetingId);

        if (! $meeting) {
            return;
        }

        foreach ($meeting->attendees->where('present', true) as $attendee) {
            $user = $attendee->user;
            if (! $user?->phone) {
                continue;
            }

            try {
                $myTasks = $meeting->actionItems
                    ->where('assignee_user_id', $user->id)
                    ->values();

                $message = $this->buildMessage($meeting, $user, $myTasks);
                $jid     = EvolutionApiService::phoneToJid($user->phone);
                $evolution->sendText($jid, $message);
            } catch (\Throwable $e) {
                Log::warning("WarRoom minutas: fallo envío a user {$user->id}", ['err' => $e->getMessage()]);
            }
        }
    }

    private function buildMessage(Meeting $meeting, $user, $tasks): string
    {
        $date     = $meeting->started_at?->format('d/m/Y') ?? now()->format('d/m/Y');
        $duration = round(($meeting->duration_actual_seconds ?? 0) / 60);
        $total    = $meeting->actionItems->count();

        $msg  = "📋 *Minutas — Junta del {$date}*\n\n";
        $msg .= "Duración: {$duration} min\n";
        $msg .= "Tareas generadas: {$total}\n\n";

        if ($tasks->count() > 0) {
            $msg .= "*Tus tareas asignadas:*\n\n";
            foreach ($tasks as $i => $task) {
                $msg .= ($i + 1) . ". {$task->description}\n";
                $msg .= "   Prioridad: {$task->priority}";
                if ($task->deadline) {
                    $msg .= " · Deadline: {$task->deadline->format('d/m')}";
                }
                $msg .= "\n\n";
            }
        } else {
            $msg .= "Sin tareas asignadas para ti.\n\n";
        }

        $msg .= "Ver War Room: " . url('/warroom');

        return $msg;
    }
}
