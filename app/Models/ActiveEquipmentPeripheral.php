<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActiveEquipmentPeripheral extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'active_equipment_id',
        'model',
        'brand',
        'ports',
        'map_proyect_id',
        'created_by',
        'updated_by'
    ];
}
