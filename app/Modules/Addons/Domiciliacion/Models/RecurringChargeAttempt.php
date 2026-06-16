<?php

namespace App\Modules\Addons\Domiciliacion\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringChargeAttempt extends Model
{
    // Tabla inmutable: solo created_at manual, sin updated_at
    public $timestamps = false;

    protected $table = 'recurring_charge_attempts';

    protected $fillable = [
        'client_id',
        'invoice_id',
        'order_id',
        'openpay_charge_id',
        'amount',
        'status',
        'attempt_no',
        'error_code',
        'error_message',
        'notified_at',
        'created_at',
    ];

    protected $casts = [
        'created_at'  => 'datetime',
        'notified_at' => 'datetime',
    ];
}
