<?php

namespace App\Services;

use App\Http\Repository\TaskRepository;
use Carbon\Carbon;

class UnreadNotificationService
{
    protected $user;
    public function __construct()
    {
        $this->user = auth()->user();
    }

    public function unreadTask($id)
    {
        $notifications = $this->user->unreadNotifications->map(function ($notification) {
            $data = $notification->data[0] ?? [];
            return [
                'id' => $notification->id,
                'model' => $data,
                'task_id' => $data['task_id'] ?? null,
            ];
        });

        $notificationToMark = $notifications->firstWhere('task_id', $id);

        if ($notificationToMark) {
            $this->user->unreadNotifications
                ->where('id', $notificationToMark['id'])
                ->first()
                ->markAsRead();
        }
    }
}
