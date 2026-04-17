<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeBilling extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    const TYPE_OF_BILLING_PREPAID_CUSTOM = 3;
    const TYPE_OF_BILLING_PREPAID_DAILY = 2;
    const TYPE_OF_BILLING_PREPAID_RECURRENT = 1;

    public function internet()
    {
        return $this->morphedByMany(Internet::class, 'plan_billing','plan_type_billings');
    }

    public function voz()
    {
        return $this->morphedByMany(Voise::class, 'plan_billing','plan_type_billings');
    }

    public function custom()
    {
        return $this->morphedByMany(Custom::class, 'plan_billing','plan_type_billings');
    }

    public function crm()
    {
        return $this->morphedByMany(Custom::class, 'plan_billing','plan_type_billings');
    }

    public function client_main_information()
    {
        return $this->belongsTo('App\Models\ClientMainInformation');
    }
}
