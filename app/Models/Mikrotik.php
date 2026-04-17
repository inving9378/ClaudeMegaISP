<?php

namespace App\Models;

use App\Notifications\StandardNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

class Mikrotik extends BaseModel
{
    use HasFactory;
    protected $guarded = [];

    public function setRouterIdAttribute($value){
        $this->attributes['router_id'] = intval($value);
        return $this;
    }

    public function router(){
        return $this->belongsTo('App\Models\Router');
    }

    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)){
            return $query->where(function ($query) use ($search, $columns){
                foreach (collect($columns)->filter(function ($value){
                    return $value != 'action';
                })->toArray() as $value){
                    $query->orWhere($value,'like','%'.$search.'%');
                }
            });
        }
    }

    public function isActive()
    {
        return $this->active;
    }
}
