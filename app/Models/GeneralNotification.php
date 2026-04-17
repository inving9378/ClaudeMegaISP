<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralNotification extends Model
{
    use HasFactory;

    protected $table = 'general_notifications';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
