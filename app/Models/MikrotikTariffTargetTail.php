<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MikrotikTariffTargetTail extends BaseModel
{
    use HasFactory;
    protected $guarded = [];

    public function client_internet_service(){
        return $this->belongsTo('App\Models\ClientInternetService');
    }

    public function mikrotik_tariff_main_tail(){
        return $this->belongsTo('App\Models\MikrotikTariffMainTail');
    }

    public function client_custom_service(){
        return $this->belongsTo('App\Models\ClientCustomService');
    }


}
