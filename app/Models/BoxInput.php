<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoxInput extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'number',
        'box_id',
        'created_by',
        'updated_by'
    ];

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class, 'box_id', 'id');
    }

    public function mapLink()
    {
        return MapLink::where(function($query){
            $query->where("input_id", $this->id)
                ->where("input_type", $this::class);
        })
        ->orWhere(function($query){
            $query->where("output_id", $this->id)
                ->where("output_type", $this::class);
        })
        ->first();
    }

    public function infoTable(): Table
    {
        return Table::where('model_class', $this::class)->first();
    }
}
