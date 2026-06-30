<?php

namespace App\Modules\Addons\Payments\Models;

use App\Models\BaseModel;
use App\Models\Client;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Instrumento de cobro multi-motor por cliente (reference | clabe | card).
 * Convive con payment_clabes (exclusiva de OpenPay): ver migración para el porqué.
 */
class PaymentInstrument extends BaseModel
{
    use SoftDeletes;

    protected $table = 'payment_instruments';

    protected $fillable = [
        'client_id',
        'payment_provider_id',
        'instrument_type',
        'external_ref',
        'status',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
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
