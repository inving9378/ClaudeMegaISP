<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rack extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'name',
        'number',
        'map_proyect_id',
        'description',
        "created_by",
        "updated_by"
    ];

    /*
    |----------------------------------------------------------------------------
    |  Relations
    |----------------------------------------------------------------------------
    */
    public function site():BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id', 'id');
    }

    public function activeEquipments(): HasMany
    {
        return $this->hasMany(ActiveEquipment::class, 'rack_id', 'id');
    }

    public function passiveEquipments(): HasMany
    {
        return $this->hasMany(PassiveEquipment::class, 'rack_id', 'id');
    }

/*
    |----------------------------------------------------------------------------
    |  scopes
    |----------------------------------------------------------------------------
    */
    public function scopeName($querry, $text)
    {
        if(!empty($text)){
            return $querry->where('racks.name', 'LIKE', "%$text%");
        }
    }

}
