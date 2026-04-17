<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Transceiver extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "description",
        "type",
        "card_id",
        "map_proyect_id",
        "created_by",
        "updated_by"
    ];

    public static $ls = "LS simple";
    public static $lsDual = "LS dual";
    public static $sc = "SC";

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function mapProyect(): BelongsTo
    {
        return $this->belongsTo(mapProyect::class);
    }

    public function infoTable(): Table
    {
        return Table::where('model_class', $this::class)->first();
    }

    public function ports(): MorphMany
    {
        return $this->morphMany(Port::class, 'portable');
    }
}
