<?php

namespace App\Services;

use App\Http\Repository\UserRepository;
use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class LogService
{

    public function getAuthUser()
    {
        if (Auth::check()) {
            return auth()->user()->name;
        }
        return "Sistema";
    }


    public function log($model, $message)
    {
        $authUser = $this->getAuthUser();
        if (get_class($model) === Client::class) {
            activity()->tap(function (Activity $activity) use ($model) {
                $activity->client_id = $model->id;
            })->log($message . ' por ' . $authUser);
        } else {
            activity()->log($message . ' por ' . $authUser);
        }
    }


    public function getLogs($model){
        $repo = new UserRepository();
        $logsActivities = $model->activities()->orderBy('id', 'desc')->get();
        $userIds = [];
        $logs = [];

        foreach ($logsActivities as $log) {
            $property = json_decode($log->properties, true);

            if($log->event === 'created' && isset($property['attributes']['created_by'])) {
                $userIds[] = $property['attributes']['created_by'];
            }
            else if($log->event === 'updated' && isset($property['attributes']['updated_by'])) {
                $userIds[] = $property['attributes']['updated_by'];
            }
        }
        $userIds = array_unique(array_filter($userIds));

        $users = [];

        if(!empty($userIds)) {
            $users = $repo->getUsersNameByIdInArray($userIds);
        }

        foreach ($logsActivities as $log) {
            $userName = 'Sistema';
            $property = json_decode($log->properties, true);

            if ($log->event === 'created' && isset($property['attributes']['created_by'])) {
                $userId = (int) $property['attributes']['created_by'];
                $userName = $users[$userId] ?? 'Sistema';
            } elseif ($log->event === 'updated' && isset($property['attributes']['updated_by'])) {
                $userId = (int) $property['attributes']['updated_by'];
                $userName = $users[$userId] ?? 'Sistema';
            }

            $logs[] = [
                "id" => $log->id,
                "log_name" => $log->log_name,
                "description" => $log->description,
                "subject_type" => $log->subject_type,
                "event" => $log->event,
                "subject_id" => $log->subject_id,
                "causer_type" => $log->causer_type,
                "causer_id" => $log->causer_id,
                "properties" => $log->properties,
                "batch_uuid" => $log->batch_uuid,
                "client_id" => $log->client_id,
                "created_at" => $log->created_at,
                "updated_at" => $log->updated_at,
                "user_name"     => $userName,
            ];
        }
        return $logs;
    }


    public function getUserName($log)
    {
        $event = $log->event;
        $userRepository = new UserRepository();
        $name = 'Sistema';
        if ($event === 'created') {
            $property = json_decode($log->properties, true);
            $userId = $property['attributes']['created_by'];
            $user = $userRepository->getUserById($userId);
            if ($user) {
                $name = $user->name;
            }
        }
        if ($event === 'updated') {
            $property = json_decode($log->properties, true);
            $userId = $property['attributes']['updated_by'];
            $user = $userRepository->getUserById($userId);
            if ($user) {
                $name = $user->name;
            }
        }
        return $name;
    }
}
