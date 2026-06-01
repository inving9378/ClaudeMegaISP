<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

class RoadmapItem extends Model
{
    protected $table = 'roadmap_items';

    protected $fillable = [
        'title', 'description', 'status', 'priority',
        'target_version', 'prompt', 'position',
        'started_at', 'completed_at',
        'subtasks', 'log',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'position'     => 'integer',
        'subtasks'     => 'array',
        'log'          => 'array',
    ];

    protected $attributes = [
        'subtasks' => '[]',
        'log'      => '[]',
    ];

    public static function currentInProgress(): ?self
    {
        return static::where('status', 'in_progress')->first();
    }

    public function scopeOrdered($query)
    {
        return $query->orderByRaw("FIELD(status,'in_progress','pending','done','cancelled')")
                     ->orderBy('position')
                     ->orderBy('id');
    }
}
