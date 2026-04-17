<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MikrotikTariffMainTail extends BaseModel
{
    use HasFactory;
    protected $guarded = [];

    public function mikrotik_tariff_target_tail()
    {
        return $this->hasMany(MikrotikTariffTargetTail::class);
    }

    public function internet(){
        return $this->belongsTo(Internet::class,'id','tariff_id')
            ->where('model','App\Models\Internet');
    }
}
