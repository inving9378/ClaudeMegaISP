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
        $repo = new TaskRepository();
        $user = $this->userAutenticated();
        $notifications = [];
        $taskIds = [];
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
                if(isset($data['task_id'])){
                    $taskIds[] = $data['task_id'];
                }
            }
        }
        $this->data['notifications'] = $notifications;

        if(!empty($taskIds)){
            $tasksStartingToday = $repo->getStartingTodayTasks($taskIds);
        }

        $filtered = [];
        foreach ($notifications as $notification) {
            if (isset($notification['model']['task_id'])) {
                $taskId = (int) $notification['model']['task_id'];
                if (in_array($taskId, $tasksStartingToday)) {
                    $filtered[] = $notification;
                }
            } else {
                $filtered[] = $notification;
            }
        }

        $notifications = $filtered;
        $this->data['notifications'] = $notifications;

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
