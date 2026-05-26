<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Promotion extends BaseModel
{
    use HasFactory;

    protected $fillable = ['promotionable_type', 'promotionable_id', 'code'];

    public function promotionable()
    {
        return $this->morphTo();
    }
}
