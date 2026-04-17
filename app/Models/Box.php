<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class Box extends BaseModel
{
    use HasFactory;
    use HasSpatial;

    protected $fillable = [
        "nomenclature",
        "box_type_id",
        "map_proyect_id",
        "point_id",
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

    public function mapProyect(): BelongsTo
    {
        return $this->belongsTo(MapProyect::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BoxType::class, 'box_type_id', 'id');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(BoxInput::class, 'box_id', 'id');
    }

    public function infoTable(): Table
    {
        return Table::where('model_class', $this::class)->first();
    }

    public function trays(): HasMany
    {
        return $this->hasMany(Tray::class, 'box_id', 'id');
    }
}
