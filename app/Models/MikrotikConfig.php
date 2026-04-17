<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MikrotikConfig extends Model
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

}
