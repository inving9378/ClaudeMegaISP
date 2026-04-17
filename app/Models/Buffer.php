<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Buffer extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'color_id',
        'created_by',
        'updated_by'
    ];

    function color() : BelongsTo
    {
        return $this->belongsTo(Color::class);
    }
}
