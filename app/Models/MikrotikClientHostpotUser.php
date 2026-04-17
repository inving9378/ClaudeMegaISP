<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MikrotikClientHostpotUser extends BaseModel
{
    use HasFactory;
    protected $fillable = ['client_id', 'mikrotik_id'];

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }
}
