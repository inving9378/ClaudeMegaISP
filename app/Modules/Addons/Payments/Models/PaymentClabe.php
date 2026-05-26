<?php

namespace App\Modules\Addons\Payments\Models;

use App\Models\BaseModel;
use App\Models\Client;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CLABE virtual asignada a un cliente. La genera el proveedor (OpenPay)
 * vía API; cuando un pago llega a esa CLABE, el banco notifica a OpenPay
 * y OpenPay nos llama el webhook con el transaction_id y el monto.
 */
class PaymentClabe extends BaseModel
{
    use SoftDeletes;

    protected $table = 'payment_clabes';

    protected $fillable = [
        'client_id',
        'clabe',
        'openpay_id',
        'payment_provider_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function provider()
    {
        return $this->belongsTo(PaymentProvider::class, 'payment_provider_id');
    }
}
