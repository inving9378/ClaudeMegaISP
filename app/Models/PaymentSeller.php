<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSeller extends BaseModel
{
    use HasFactory;
    protected $table = 'payments_sellers';
    protected $fillable = [
        'payment_number',
        'payment_date',
        'amount',
        'method_of_payment',
        'comment',
        'status',
        'created_by',
        'seller_id',
        'commission_id',
    ];

    public function method_payment()
    {
        return $this->belongsTo(MethodOfPayment::class);
    }

    public function created_by()
    {
        return $this->belongsTo(User::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }

    public function payments_details()
    {
        return $this->hasMany(PaymentDetail::class);
    }
}

