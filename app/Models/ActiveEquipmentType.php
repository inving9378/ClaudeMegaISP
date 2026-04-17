<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveEquipmentType extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "type",
        "brand_id",
        "model",
        "cards",
        "ethernet_ports",
        "sfp_ports",
        "sfp_plus_ports",
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
