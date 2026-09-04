<?php

namespace App\Modules\Addons\MapaRed\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Espejo de App\Models\MapProyect, apuntando a mapared_proyects (MR-06a-1, item #9990333).
 */
class MapaRedProyect extends BaseModel
{
    use HasFactory;

    protected $table = 'mapared_proyects';

    protected $fillable = [
        'name',
        'parent_id',
        'classification',
        'level',
        'created_by',
        'updated_by',
    ];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function layers(): HasMany
    {
        return $this->hasMany(MapaRedLayer::class, 'project_id', 'id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(MapaRedProyect::class, 'parent_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MapaRedProyect::class);
    }
}
