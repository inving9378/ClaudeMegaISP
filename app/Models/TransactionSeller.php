<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionSeller extends BaseModel
{
    use HasFactory;

    protected $table = 'transactions_sellers';

    protected $fillable = [
        'transaction_date',
        'method_of_payment',
        'seller_id',
        'previous_balance',
        'new_balance',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function methodOfPayment()
    {
        return $this->belongsTo(MethodOfPayment::class);
    }
}
