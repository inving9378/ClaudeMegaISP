<?php

namespace App\Models;

use App\Http\Traits\Models\ActivityLog\scope\ScopeActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use ScopeActivityLog;
    use HasFactory;
    protected $table = 'activity_log';
    protected $guarded = [];
    protected $appends = ['user_name'];
    protected $with = ['user'];


    public function user()
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
    public function getUserNameAttribute()
    {
        return $this->user ? $this->user->getClientNameWithFathersNamesAttribute() : 'Sistema';
    }
}
