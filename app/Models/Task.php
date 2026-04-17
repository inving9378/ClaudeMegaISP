<?php

namespace App\Models;

use App\Http\Repository\TeamRepository;
use App\Http\Traits\Models\Task\ScopeTask;
use App\Notifications\StandardNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

class Task extends Model
{
    use HasFactory;
    use ScopeTask;

    public static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->created_by = auth()->user() ? auth()->user()->id : 0;
            $obj->updated_by = auth()->user() ? auth()->user()->id : 0;
        });
        static::updating(function ($obj) {
            $obj->updated_by = auth()->user() ? auth()->user()->id : 0;
            if ($obj->isDirty('status')) {
                $originalStatus = $obj->getOriginal('status');
                $newStatus = $obj->status;

                if ($originalStatus === 'Done' && $newStatus !== 'Done') {
                    $obj->finish_at = null;
                } elseif ($newStatus === 'Done') {
                    $obj->finish_at = Carbon::now();
                    if ($obj->finish_at_first_time == null) {
                        $obj->finish_at_first_time = Carbon::now();
                    }
                }
            }
        });
    }

    protected $guarded = [];
    protected $appends = ['project_title', 'workflow_name', 'status_name', 'color_task_assigned', 'user_name_assigned', 'user_or_team_name', 'created_by_name', 'updated_by_name', 'last_note', 'status_cls'];

    /* TODO Si se cambia aqui cambiar en TaskCrud.vue y TaskEdit.vue */
    const TASK_COLOR = [
        'priority' => [
            'Alta' => 'red',
            'Media' => 'yellow',
            'Baja' => 'blue',
        ],
        'status' => [
            'ToDo' => 'blue',
            'InProgress' => 'yellow',
            'Done' => 'green',
            'Archivado' => 'gray',
            'PostponedByClient' => 'red',
        ]
    ];


    const MULTIPLE_RELATIONS = [
        'assigned_to' => 'users',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function file()
    {
        return $this->morphOne(File::class, 'fileable');
    }
    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id');
    }

    public function client_main_information()
    {
        return $this->belongsTo(ClientMainInformation::class, 'client_main_information_id');
    }

    public function notes()
    {
        return $this->hasMany(ObservationTask::class, 'task_id');
    }

    public function latestNote()
    {
        return $this->hasOne(ObservationTask::class, 'task_id')->latestOfMany();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activities()
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function getProjectTitleAttribute()
    {
        return $this->project->title ?? '';
    }

    public function getWorkflowNameAttribute()
    {
        $workFlowId = $this->project->workflow ?? null;
        $workFlowName = '';
        if ($workFlowId) {
            $workFlowName = WorkFlow::find($workFlowId)->first()->name ?? '';
        }
        return $workFlowName;
    }

    public function getStatusNameAttribute()
    {
        $status = null;
        $statuses = [
            'ToDo' => 'Por Hacer',
            'InProgress' => 'En Progreso',
            "Done" => 'Terminado',
            "Archived" => "Archivado"
        ];

        if (isset($statuses[$this->status])) {
            $status = $statuses[$this->status];
        }

        return $status;
    }

    public function getColorTaskAssignedAttribute()
    {
        $users = $this->users;
        $arrayUsers = [];
        $arrayTeamColor = [];

        if ($users->count()) {
            $arrayUsers = $users->pluck('color');

            if ($arrayUsers->count() > 1) {
                foreach ($users as $user) {
                    // Iteramos sobre los equipos de cada usuario
                    foreach ($user->teams as $team) {
                        $teamName = $team->name;
                        $arrayTeamColor[] = $teamName;
                    }
                }
            } else {
                return $arrayUsers->first();
            }
        }

        if (count($arrayTeamColor) > 0) {
            if ($this->ifDistinctTeam($arrayTeamColor)) {
                return "arcoiris";
            } else {
                $teamRepository = new TeamRepository();
                $nameTeam = $arrayTeamColor[0];
                $color = $teamRepository->getColorByName($nameTeam);
                if ($color != null) {
                    return $color;
                }
            }
        }

        // Devolver el color del primer equipo, o un color por defecto
        return $this->user->color ?? "rgb(211, 211, 211)";
    }

    public function getCreatedByNameAttribute()
    {
        return $this->createdBy->name ?? '';
    }

    public function getUpdatedByNameAttribute()
    {
        return $this->updatedBy->name ?? '';
    }

    public function getUserOrTeamNameAttribute()
    {
        $users = $this->users;
        $arrayUsers = [];
        $arrayTeamName = [];

        if ($users->count()) {
            $arrayUsers = $users->pluck('name');

            if ($arrayUsers->count() > 1) {
                foreach ($users as $user) {
                    // Iteramos sobre los equipos de cada usuario
                    foreach ($user->teams as $team) {
                        $teamName = $team->name;
                        $arrayTeamName[] = $teamName;
                    }
                }
            } else {
                return substr($arrayUsers[0], 0, -2);
            }
        }

        if (count($arrayTeamName) > 0) {
            if ($this->ifDistinctTeam($arrayTeamName)) {
                $arrayTeamName = array_unique($arrayTeamName);
                $initials = '';

                foreach ($arrayTeamName as $name) {
                    $initials .= strtoupper(substr($name, 0, 1)) . ', ';
                }

                return substr($initials, 0, -2);
            } else {
                $nameTeam = $arrayTeamName[0];
                return strtoupper(substr($nameTeam, 0, 1));
            }
        }
        $name = $this->user ? $this->user->name : '';
        return substr($name, 0, -2);
    }

    public function ifDistinctTeam($array)
    {
        if (empty($array) || count($array) === 1) {
            return false;
        }
        $firstValue = $array[0];
        foreach ($array as $value) {
            if ($value !== $firstValue) {
                return true;
            }
        }
        return false;
    }

    public function getUserNameAssignedAttribute()
    {
        $users = $this->users;
        $userNamesAssigned = $users->pluck('name')->toArray();

        // Si hay más de 5 usuarios, tomar los primeros 5 y agregar "..."
        if (count($userNamesAssigned) > 5) {
            $userNamesAssigned = array_slice($userNamesAssigned, 0, 5);
            $string = implode(', ', $userNamesAssigned) . ', ...';
        } else {
            // Si hay 5 o menos, solo unir los nombres con comas
            $string = implode(', ', $userNamesAssigned);
        }

        return $string;
    }

    public function getLastNoteAttribute()
    {
        return $this->latestNote->observation ?? '';
    }

    public function getStatusClsAttribute()
    {
        $cls = null;
        $status = $this->status;
        switch ($status) {
            case 'InProgress':
                $cls = 'task-status-progress';
                break;
            case 'ToDo':
                $cls = 'task-status-todo';
                break;
            case 'Archivado':
                $cls = 'task-status-archived';
                break;
            case 'PostponedByClient':
                $cls = 'task-status-future';
                break;
            case 'Done':
                $cls = 'task-status-done';
                break;
            default:
                $cls = '';
                break;
        }
        return $cls;
    }

    public function sendNotifications($priority = null)
    {
        $notification = new TaskNotification();
        $notification->priority = $priority;
        $notification->title = 'Nueva tarea asignada';
        $notification->task_id = $this->id;
        $notification->base_url = '/scheduling/task/read-notification/';
        $notification->created_id = auth()->user()->id;
        $notification->save();
        Notification::send($this->users, new StandardNotification($notification));
    }
}
