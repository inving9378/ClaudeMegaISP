<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointAccessory extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'point_id',
        'name',
        'lenght',
        'initial_meter',
        'final_meter',
        'map_proyect_id',
        'created_by',
        'updated_by'
    ];

    public function point() : BelongsTo {
        return $this->belongsTo(Point::class);
    }

    public function mapProyect(): BelongsTo{
        return $this->belongsTo(MapProyect::class);
    }
}
