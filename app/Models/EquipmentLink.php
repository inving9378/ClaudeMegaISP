<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentLink extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "input_id",
        "output_id",
        "map_link_id",
        "fiber_id",
        "created_by",
        "updated_by"
    ];

    public function inputObject(): BelongsTo
    {
        return $this->belongsTo(Port::class, "input_id", "id");
    }

    public function outputObject(): BelongsTo
    {
        return $this->belongsTo(Port::class, "output_id", "id");
    }

    public function fiber() : BelongsTo
    {
        return $this->belongsTo(Fiber::class);
    }

    function mapLink() : BelongsTo
    {
        return $this->belongsTo(MapLink::class);
    }
}
