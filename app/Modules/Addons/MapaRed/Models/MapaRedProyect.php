<?php

namespace App\Modules\Addons\MapaRed\Models;

use App\Models\BaseModel;
use App\Modules\Addons\MapaRed\Repositories\MapaRedProyectRepository;
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

    /**
     * MR-22 Fase 4b (item #9991049): invalida el cache corto de getNodes() en cualquier
     * alta/edición/baja de proyecto, para que la mitigación de performance no sirva datos
     * obsoletos tras una escritura.
     */
    protected static function boot()
    {
        parent::boot();
        static::saved(fn () => MapaRedProyectRepository::invalidateNodesCache());
        static::deleted(fn () => MapaRedProyectRepository::invalidateNodesCache());
    }

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
