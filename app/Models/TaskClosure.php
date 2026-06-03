<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de cierre de una orden de trabajo (Task) hecho desde la app
 * del técnico: geolocalización del cierre, firma y foto. Una fila por tarea.
 */
class TaskClosure extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'latitude'     => 'float',
        'longitude'    => 'float',
        'gps_accuracy' => 'float',
        'closed_at'    => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
