<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Point extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "map_proyect_id",
        "is_pole",
        "created_by",
        "updated_by"
    ];

    /**
     * Get the Point's position.
     */
    public function position():MorphOne
    {
        return $this->morphOne(Position::class, 'positionable');
    }

    public function infoTable(): Table
    {
        return Table::where('model_class', $this::class)->first();
    }

    public function mapProyect():BelongsTo
    {
        return $this->belongsTo(MapProyect::class, 'map_proyect_id', 'id');
    }

    public function ports(): MorphMany
    {
        return $this->morphMany(Port::class, 'portable');
    }
}
