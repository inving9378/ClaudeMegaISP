<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tube extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'tube_type_id',
        'created_by',
        'updated_by'
    ];
}
