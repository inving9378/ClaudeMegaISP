<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Credential extends BaseModel
{
    use HasFactory;

    protected $table = 'credential_images';

    protected $fillable = [
        'name',
        'path',
        'type',
    ];
}
