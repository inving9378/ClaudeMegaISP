<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoleAccessory extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "pole_id",
        "name",
        "amount",
        "observations",
        "map_proyect_id",
        "created_by",
        "updated_by"
    ];

    public function pole() : BelongsTo {
        return $this->belongsTo(Pole::class);
    }

    public function mapProyect(): BelongsTo{
        return $this->belongsTo(MapProyect::class);
    }
}
