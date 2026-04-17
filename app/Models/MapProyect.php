<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MapProyect extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "name",
        "parent_id",
        "classification",
        'level',
        "created_by",
        "updated_by"
    ];

    protected $appends = ['text_node', 'key', 'icon', 'type', 'config', 'parent_key'];

    protected static function boot()
    {
        parent::boot();
        static::deleted(function () {
            (new MapDevicePortConnection())->removeOrphansConnections();
        });
    }

    /*
    |----------------------------------------------------------------------------
    |  scopes
    |----------------------------------------------------------------------------
    */
    public function scopeName($querry, $text)
    {
        if (!empty($text)) {
            return $querry->where('map_proyects.name', 'LIKE', "%$text%");
        }
    }

    /*
    |----------------------------------------------------------------------------
    |  relations
    |----------------------------------------------------------------------------
    */

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function layers(): HasMany
    {
        return $this->hasMany(MapLayer::class, 'project_id', 'id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(MapProyect::class, 'parent_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MapProyect::class);
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class, 'map_proyect_id', 'id');
    }

    public function mapRoutes(): HasMany
    {
        return $this->hasMany(MapRoute::class, 'map_proyect_id', 'id');
    }

    public function poles(): HasMany
    {
        return $this->hasMany(Pole::class, 'map_proyect_id', 'id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(Point::class, 'map_proyect_id', 'id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'map_proyect_id', 'id');
    }

    public function trenches(): HasMany
    {
        return $this->hasMany(Trench::class, 'map_proyect_id', 'id');
    }

    public function getTextNodeAttribute()
    {
        return $this->name;
    }

    public function getKeyAttribute()
    {
        return sprintf('project-%s', $this->id);
    }

    public function getParentKeyAttribute()
    {
        return $this->parent->key ?? null;
    }

    public function getIconAttribute()
    {
        return 'mdi-folder-outline';
    }

    public function getTypeAttribute()
    {
        return 'project';
    }

    public function getConfigAttribute()
    {
        return [
            'type' => 'project',
            'route' => 'projects',
            'tooltips' => 'proyecto'
        ];
    }

    public function changeRecursiveClassification($classification)
    {
        foreach ($this->projects as $l) {
            $l->changeRecursiveClassification($classification);
        }
        foreach ($this->layers as $l) {
            $l->classification = $classification;
            $l->save();
        }
    }
}
