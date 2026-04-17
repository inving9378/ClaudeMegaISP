<?php

namespace App\Http\Controllers\Utils;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Crm;
use App\Models\LogActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

const RELATION = [
    'Crm' => 'getLogActivitiesCrm',
    'Client' => 'getLogActivitiesClient',
];

class LogActivityController extends Controller
{
    public function getLogActivities(Request $request, $id)
    {
        if (isset(RELATION[$request->module])) {
            $function = RELATION[$request->module];
            return $this->$function($id);
        }
        return null;
    }

    public function getLogActivitiesCrm($id)
    {
        $crm = Crm::where('id', $id)->with('log_activities')->first();
        if ($crm) return $crm->log_activities;
        return [];
    }

    public function getLogActivitiesClient($id)
    {
        $logs = ActivityLog::where('client_id', $id)
            ->orWhere(function ($query) use ($id) {
                $query->where('subject_type', 'App\\Models\\Client')
                    ->where('subject_id', $id);
            })
            ->orderBy('id', 'desc')->get();
        $client = Client::find($id);
        $log_activities_data = [];
        foreach ($logs as $log_activity) {
            $text = !is_null($log_activity->description) ? $log_activity->description : $client->client_name . ' - ' . $log_activity->event;

            $log_activity_data = [
                'id' => $log_activity->id,
                'comment' => $log_activity->description,
                'created_at' => $log_activity->created_at->format('Y-m-d H:i:s'),
                'data' => $log_activity->properties,
                'date' => Carbon::parse($log_activity->created_at)->diffForHumans(),
                'text' => $text,
                'type' => $log_activity->subject_type,
                'updated_at' => $log_activity->updated_at->format('Y-m-d H:i:s'),
                'user' => $client->client_name,
            ];

            $log_activities_data[] = $log_activity_data;
        }
        return $log_activities_data;
    }

    public function getAllActivities()
    {
        $log_activities = LogActivity::orderBy('created_at', 'desc')->get();
        $log_activities_data = [];

        foreach ($log_activities as $log_activity) {
            $user = User::find($log_activity->user_id);
            if ($user) {
                $action_text = '';
                switch ($log_activity->type) {
                    case 'create_crm':
                        $action_text = ' creo el crm.';
                        break;
                    case 'updated_crm':
                        $action_text = ' actualizo el crm.';
                        break;
                }

                $log_activity_data = [
                    'id' => $log_activity->id,
                    'comment' => $log_activity->comment,
                    'created_at' => $log_activity->created_at->format('Y-m-d H:i:s'),
                    'data' => $log_activity->data,
                    'date' => Carbon::parse($log_activity->created_at)->diffForHumans(),
                    'text' => $user->name . $action_text,
                    'type' => $log_activity->logable_type,
                    'updated_at' => $log_activity->updated_at->format('Y-m-d H:i:s'),
                    'user' => $user->name,
                ];

                $log_activities_data[] = $log_activity_data;
            }
        }

        return response()->json($log_activities_data);
    }

    public function getActivitiesByUser($id)
    {
        $log_activities = LogActivity::where('user_id', $id)->orderBy('created_at', 'desc')->get();
        $log_activities_data = [];

        foreach ($log_activities as $log_activity) {
            $user = User::find($log_activity->user_id);
            $action_text = '';
            switch ($log_activity->type) {
                case 'create_crm':
                    $action_text = ' creo el crm.';
                    break;
                case 'updated_crm':
                    $action_text = ' actualizo el crm.';
                    break;
            }

            $log_activity_data = [
                'id' => $log_activity->id,
                'comment' => $log_activity->comment,
                'created_at' => $log_activity->created_at->format('Y-m-d H:i:s'),
                'data' => $log_activity->data,
                'date' => Carbon::parse($log_activity->created_at)->diffForHumans(),
                'text' => $user->name . $action_text,
                'type' => $log_activity->logable_type,
                'updated_at' => $log_activity->updated_at->format('Y-m-d H:i:s'),
                'user' => $user->name,
            ];

            $log_activities_data[] = $log_activity_data;
        }

        return response()->json($log_activities_data);
    }
}