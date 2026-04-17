<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientPaymentMetadata extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'client_payment_metadata';

    protected $guarded = [];
    protected $casts = [
        'previous_balance' => 'decimal:2',
        'previous_active_status' => 'boolean',
        'previous_promise_status' => 'boolean',
        'previous_fecha_pago' => 'date',
        'previous_fecha_corte' => 'date',
        'additional_data' => 'array'
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
