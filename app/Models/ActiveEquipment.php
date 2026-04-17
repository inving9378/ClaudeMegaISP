<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ActiveEquipment extends BaseModel
{
    use HasFactory;

    protected $table = "active_equipments";

    protected $fillable = [
        "rack_id",
        "type_id",
        "name",
        "description",
        "serial_number",
        "map_proyect_id",
        "created_by",
        "updated_by"
    ];


    public function type(): BelongsTo
    {
        return $this->belongsTo(ActiveEquipmentType::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function infoTable(): Table
    {
        return Table::where('model_class', $this::class)->first();
    }

    public function mapProyect(): BelongsTo
    {
        return $this->belongsTo(mapProyect::class);
    }

    public function ports(): MorphMany
    {
        return $this->morphMany(Port::class, 'portable');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'active_equipment_id', 'id');
    }
}
