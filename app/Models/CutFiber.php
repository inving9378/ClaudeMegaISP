<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class CutFiber extends BaseModel
{
    use HasFactory;
    use HasSpatial;

    protected $fillable = [
        "description",
        "date",
        "type",
        "power",
        "meter",
        "passive_equipment_id",
        "map_proyect_id",
        "created_by",
        "updated_by"
    ];

    /*
    |----------------------------------------------------------------------------
    |  relations
    |----------------------------------------------------------------------------
    */

    public function passiveEquipment():BelongsTo
    {
        return $this->belongsTo(PassiveEquipment::class, 'passive_equipment_id', 'id');
    }

    public function position()
    {
        return $this->morphOne(Position::class, 'positionable');
    }
}
