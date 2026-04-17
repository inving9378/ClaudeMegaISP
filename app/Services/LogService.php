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


    public function getLogs($model)
    {
        $logsActivities = $model->activities()->orderBy('id', 'desc')->get();
        $logs = [];
        foreach ($logsActivities as $log) {
            $array = [
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
                "user_name" => $this->getUserName($log),
            ];
            array_push($logs, $array);
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
