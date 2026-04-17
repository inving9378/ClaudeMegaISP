<?php

namespace App\Http\Traits;

use App\Http\Repository\TaskRepository;
use App\Services\FormatDateService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

trait UserTrait
{
    use NotificationTrait;

    public function userAutenticated()
    {
        return Auth::user();
    }

    public function userNotification()
    {
        $user = $this->userAutenticated();
        $notifications = [];
        foreach ($user->unreadNotifications as $notification) {
            $data = $this->getNotificationAttributes($notification);
            if ($data) {
                $notifications[] = [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'model' => $data,
                    'reported_by' => $data['reporter'] ?? '',
                    'topic' => $data['topic'] ?? '',
                    'created_at' => Carbon::parse($data['created_at'])->diffForHumans()
                ];
            }
        }
        $this->data['notifications'] = $notifications;

        //si la notificacion es de tareas que solo muestre las que la fecha de inicio es hoy
        $unsetsNotifications = [];
        foreach ($notifications as $notification) {
            if (isset($notification['model']['task_id'])) {
                $taskId = $notification['model']['task_id'];
                $taskRepository = new TaskRepository();
                $task = $taskRepository->getModelById($taskId);
                if ($task) {
                    $taskStartTime = $task->start_time;
                    $isTaskToday = $this->getIsTaskToDay($taskStartTime);
                    if (!$isTaskToday) {
                        $unsetsNotifications[] = $notification;
                    }
                }
            }
        }

        foreach ($unsetsNotifications as $notification) {
            unset($notifications[array_search($notification, $notifications)]);
        }

        return $notifications;
    }


    public function getIsTaskToDay($taskDateStart)
    {
        $formatDateService = new FormatDateService($taskDateStart);
        $date = $formatDateService->formatDate();
        try {
            return Carbon::now()->startOfDay() == Carbon::parse($date)->startOfDay();
        } catch (Exception $e) {
            return false;
        }
    }
}
