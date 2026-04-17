<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function fileable()
    {
        return $this->morphTo();
    }

    public function scopeLasted($query)
    {
        return $query->orderBy('created_at','desc');
    }
}
