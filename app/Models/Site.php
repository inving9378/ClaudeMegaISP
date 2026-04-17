<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class Site extends BaseModel
{
    use HasFactory;
    use HasSpatial;

    protected $fillable = [
        "name",
        "map_proyect_id",
        "created_by",
        "updated_by"
    ];

    /*
    |----------------------------------------------------------------------------
    |  relations
    |----------------------------------------------------------------------------
    */
    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class, 'site_id', 'id');
    }

    /**
    * @return BelongsTo
    */
    public function upatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    public function infoTable(): Table
    {
        return Table::where('model_class', $this::class)->first();
    }

    /**
    * @return BelongsTo
    */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function position()
    {
        return $this->morphOne(Position::class, 'positionable');
    }

    /*
    |----------------------------------------------------------------------------
    |  scopes
    |----------------------------------------------------------------------------
    */
    public function scopeName($querry, $text)
    {
        if(!empty($text)){
            return $querry->where('sites.name', 'LIKE', "%$text%");
        }
    }
}
