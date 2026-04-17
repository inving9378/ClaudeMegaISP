<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionLog extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'model',
        'action',
        'user_id',
        'json',
    ];
}
