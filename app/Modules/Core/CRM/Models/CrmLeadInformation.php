<?php

namespace App\Modules\Core\CRM\Models;

use App\Models\BaseModel;
use App\Models\CommissionDetail;
use App\Models\PaymentDetail;
use App\Models\Seller;
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
