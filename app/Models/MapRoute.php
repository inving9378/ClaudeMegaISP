<?php

namespace App\Models;

use App\Repositories\PositionRepository;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapRoute extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "name",
        "description",
        "map_proyect_id",
        "fibers_amount",
        "created_by",
        "updated_by"
    ];

    protected $appends = ['label', 'key'];

    protected $with = ['layer'];

    public static function boot()
    {
        parent::boot();
        static::created(function ($obj) {
            $obj->layer()->create([
                ...request()->input('layer')
            ]);
            $obj->loadMissing('layer');
        });
    }

    public function mapProyect(): BelongsTo
    {
        return $this->belongsTo(mapProyect::class);
    }

    public function layer()
    {
        return $this->morphOne(MapLayer::class, 'layer');
    }

    public function mapLinks(): HasMany
    {
        return $this->hasMany(MapLink::class);
    }

    public function length(): float
    {
        $repository = new PositionRepository();

        $distance = 0.00;

        foreach ($this->mapLinks as $mapLink) {
            $inputPosition = $mapLink->inputPosition();
            $outputPosition = $mapLink->outputPosition();
            $distance += $repository->distanceBetweenTwoPoints(
                $inputPosition->id,
                $outputPosition->id
            );
        }

        return $distance;
    }

    public function getLabelAttribute()
    {
        return $this->name;
    }

    public function getKeyAttribute()
    {
        return sprintf('layer-%s', $this->layer->id);
    }
}
