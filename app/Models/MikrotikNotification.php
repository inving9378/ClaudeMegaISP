<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MikrotikNotification extends Model
{
    protected $table = 'mikrotik_notifications';

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
