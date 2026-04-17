<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function scopeClient($query)
    {
        return $query->where('receiptable_type', 'App\Models\Client');
    }
}
