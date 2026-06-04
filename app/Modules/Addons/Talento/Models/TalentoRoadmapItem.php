<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoRoadmapItem extends Model
{
    protected $table = 'talento_roadmap_items';

    protected $fillable = ['phase', 'title', 'scope', 'status', 'target_date', 'notes'];

    protected $casts = [
        'target_date' => 'date',
        'phase'       => 'integer',
    ];
}
