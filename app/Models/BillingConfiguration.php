<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingConfiguration extends BaseModel
{
    use HasFactory;
    protected $guarded = [];


    public function client(){
        return $this->belongsTo('App\Models\Client');
    }

}
