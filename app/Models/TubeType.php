<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TubeType extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'type',
        'diameter',
        'created_by',
        'updated_by'
    ];
}
