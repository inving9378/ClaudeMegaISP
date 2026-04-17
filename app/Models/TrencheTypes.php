<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrencheTypes extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'model',
        'width',
        'lenght',
        'depth',
        'brand_id',
        'created_by',
        'updated_by'
    ];

    /*
    |----------------------------------------------------------------------------
    |  Relations
    |----------------------------------------------------------------------------
    */

    public function brand():BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }
}
