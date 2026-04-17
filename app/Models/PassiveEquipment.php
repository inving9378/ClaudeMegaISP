<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PassiveEquipment extends BaseModel
{
    use HasFactory;

    protected $table = "passive_equipments";

    protected $fillable = [
        "rack_id",
        "type_id",
        "name",
        "description",
        "map_proyect_id",
        "created_by",
        "updated_by"
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(PassiveEquipmentType::class);
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
}
