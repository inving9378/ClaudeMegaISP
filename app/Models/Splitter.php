<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Splitter extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'box_id',
        'number',
        'outputs',
        'created_by',
        'updated_by'
    ];

    public function infoTable(): Table
    {
        return Table::where('model_class', $this::class)->first();
    }

    public function ports(): MorphMany
    {
        return $this->morphMany(Port::class, 'portable');
    }

    public function box() : BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    function input()
    {
        return Port::where("portable_type", Splitter::class)
                    ->where("type", Port::$splitterIn)
                    ->where("portable_id", $this->id)
                    ->first();
    }
}
