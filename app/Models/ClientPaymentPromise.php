<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientPaymentPromise extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'first_court_date',
        'first_amount',
        'first_amount_is_pay',
        'second_court_date',
        'second_amount',
        'second_amount_is_pay',
        'third_court_date',
        'third_amount',
        'third_amount_is_pay'
    ];
}
