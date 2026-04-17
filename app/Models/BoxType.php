<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoxType extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "model",
        "type",
        "brand_id",
        "inputs",
        "trays",
        "mergers_by_tray",
        "ports",
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
