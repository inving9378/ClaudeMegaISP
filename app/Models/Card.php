<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Card extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "name",
        "type",
        "active_equipment_id",
        "map_proyect_id",
        "created_by",
        "updated_by"
    ];

    public function mapProyect(): BelongsTo
    {
        return $this->belongsTo(MapProyect::class);
    }

    public function activeEquipment(): BelongsTo
    {
        return $this->belongsTo(ActiveEquipment::class);
    }

    public function infoTable(): Table
    {
        return Table::where('model_class', $this::class)->first();
    }

    public function ports(): MorphMany
    {
        return $this->morphMany(Port::class, 'portable');
    }

    public function transceivers(): HasMany
    {
        return $this->hasMany(Transceiver::class, 'card_id', 'id');
    }
}
