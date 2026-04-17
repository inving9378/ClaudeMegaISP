<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MatanYadaev\EloquentSpatial\Objects\Point;

class Position extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "point",
        "positionable_id",
        "positionable_type",
        "created_by",
        "updated_by",
    ];

    protected $casts = [
        "point" => Point::class
    ];

    /**
     * Get the parent imageable model (user or post).
     */
    public function positionable()
    {
        return $this->morphTo();
    }
}
