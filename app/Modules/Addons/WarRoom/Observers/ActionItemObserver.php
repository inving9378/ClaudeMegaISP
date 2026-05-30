<?php

namespace App\Modules\Addons\WarRoom\Observers;

use App\Models\Task;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use App\Modules\Addons\WarRoom\Models\ActionItem;
use Illuminate\Support\Facades\Log;

class ActionItemObserver
{
    public function created(ActionItem $item): void
    {
        $this->linkToTask($item);
        $this->notifyAssignee($item);
    }

    private function linkToTask(ActionItem $item): void
    {
        try {
            $task = Task::create([
                'title'       => 'War Room: ' . mb_substr($item->description, 0, 50),
                'description' => $item->description,
                'assigned_to' => $item->assignee_user_id,
                'priority'    => $this->mapPriority($item->priority),
                'start_date'  => $item->deadline?->format('Y-m-d'),
                'status'      => 'To Do',
                'source'      => 'warroom',
            ]);

            $item->updateQuietly(['linked_task_id' => $task->id]);
        } catch (\Throwable $e) {
            Log::warning('WarRoom: no se pudo crear Task vinculada', ['error' => $e->getMessage(), 'action_item_id' => $item->id]);
        }
    }

    private function notifyAssignee(ActionItem $item): void
    {
        try {
            $user = $item->assignee;
            if (! $user?->cellphone) {
                return;
            }

            $jid  = EvolutionApiService::phoneToJid($user->cellphone);
            $date = $item->meeting?->started_at?->format('d/m') ?? now()->format('d/m');
            $text = "📋 *Nueva tarea de la junta del {$date}*\n\n"
                  . "{$item->description}\n\n"
                  . "Prioridad: {$item->priority}\n"
                  . "Deadline: " . ($item->deadline?->format('d/m/Y') ?? 'sin definir');

            app(EvolutionApiService::class)->sendText($jid, $text);
        } catch (\Throwable $e) {
            Log::warning('WarRoom: no se pudo enviar WhatsApp', ['error' => $e->getMessage(), 'action_item_id' => $item->id]);
        }
    }

    private function mapPriority(string $priority): string
    {
        return match ($priority) {
            'critico'     => 'Alta',
            'alto'        => 'Alta',
            'medio'       => 'Media',
            'oportunidad' => 'Media',
            'estrategico' => 'Baja',
            default       => 'Media',
        };
    }
}
