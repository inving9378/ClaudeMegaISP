<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fiber extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'buffer_id',
        'number',
        'color_id',
        'created_by',
        'updated_by'
    ];

    public function buffer() : BelongsTo
    {
        return $this->belongsTo(Buffer::class);
    }

    function color() : BelongsTo
    {
        return $this->belongsTo(Color::class);
    }
}
