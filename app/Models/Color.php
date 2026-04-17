<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Color extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "name",
        "code",
        "created_by",
        "updated_by"
    ];
}
