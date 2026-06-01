<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

class RoadmapItemMemory extends Model
{
    protected $table = 'roadmap_item_memory';

    protected $fillable = [
        'roadmap_item_id',
        'file_path',
        'file_size_bytes',
        'content_hash',
        'session_count',
        'summary_last_session',
        'last_appended_at',
    ];

    protected $casts = [
        'last_appended_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(RoadmapItem::class, 'roadmap_item_id');
    }
}
