<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modem extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'model',
        'brand',
        'serie',
        'created_by',
        'updated_by'
    ];
}
