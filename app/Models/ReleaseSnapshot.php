<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'release_id',
        'tabla',
        'criticidad',
        'max_id_al_snapshot',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function release()
    {
        return $this->belongsTo(Release::class);
    }
}
