<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'iva',
        'account_balance',
        'monthly_bonus',
        'monthly_bonus_sales_number',
        'status',
        'seller_id',
        'start_date',
        'end_date',
        'period',
        'number_sales',
        'number_prospects',
        'zone',
    ];


    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function created_by()
    {
        return $this->belongsTo(User::class);
    }

    public function payment_sellers()
    {
        return $this->hasMany(PaymentSeller::class);
    }

    public function commissions_details()
    {
        return $this->hasMany(CommissionDetail::class, 'commission_id');
    }
}
