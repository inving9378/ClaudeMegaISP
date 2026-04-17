<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tray extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "number",
        "box_id",
        "created_by",
        "updated_by"
    ];

    public function box() : BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function infoTable(): Table
    {
        return Table::where('model_class', $this::class)->first();
    }
}
