<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassiveEquipmentType extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "type",
        "brand_id",
        "model",
        "ports",
        "trays",
        "created_by",
        "updated_by"
    ];

    /*
    |----------------------------------------------------------------------------
    |  Relations
    |----------------------------------------------------------------------------
    */

    public function brand():BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }
}
