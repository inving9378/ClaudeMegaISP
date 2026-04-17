<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CrmLeadInformation extends BaseModel
{
    use HasFactory;
    protected $guarded = [];

    public function commissions_details()
    {
        return $this->hasMany(CommissionDetail::class);
    }

    public function payments_details()
    {
        return $this->hasMany(PaymentDetail::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'owner_id');
    }
}
