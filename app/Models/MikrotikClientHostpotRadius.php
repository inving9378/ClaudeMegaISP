<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MikrotikClientHostpotRadius extends BaseModel
{
    use HasFactory;
    protected $table = 'mikrotik_client_hostpot_radius';
    protected $fillable = [
        'client_id',
        'mikrotik_id',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
