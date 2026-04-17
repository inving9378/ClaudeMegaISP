<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class Pole extends BaseModel
{
    use HasFactory;
    use HasSpatial;

    protected $fillable = [
        "description",
        "height",
        "type",
        "tension",
        "map_proyect_id",
        "created_by",
        "updated_by"
    ];

    /**
     * Get the Pole's position.
     */
    public function position()
    {
        return $this->morphOne(Position::class, 'positionable');
    }

    public function mapProyect():BelongsTo
    {
        return $this->belongsTo(MapProyect::class, 'map_proyect_id', 'id');
    }
}
