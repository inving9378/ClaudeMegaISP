<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientLog extends BaseModel
{
    use HasFactory;
    protected $table = 'client_logs';
    protected $guarded = [];
}
