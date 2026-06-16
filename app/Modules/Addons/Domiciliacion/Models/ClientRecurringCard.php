<?php

namespace App\Modules\Addons\Domiciliacion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientRecurringCard extends Model
{
    use SoftDeletes;

    protected $table = 'client_recurring_cards';

    protected $fillable = [
        'client_id',
        'openpay_customer_id',
        'openpay_card_id',
        'card_brand',
        'card_last4',
        'card_exp',
        'cardholder',
        'status',
        'consent_at',
        'consent_channel',
        'created_by',
    ];

    protected $casts = [
        'consent_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}
