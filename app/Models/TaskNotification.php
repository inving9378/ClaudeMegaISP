<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class TaskNotification extends Model
{
    protected $table = 'task_notifications';
    protected $appends = ['created_by'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_id');
    }

    public function getCreatedByAttribute()
    {
        return $this->user()->first()->name;
    }
}