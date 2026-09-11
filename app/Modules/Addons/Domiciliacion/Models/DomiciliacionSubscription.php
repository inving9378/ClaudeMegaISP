<?php

namespace App\Modules\Addons\Domiciliacion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DomiciliacionSubscription extends Model
{
    use SoftDeletes;

    protected $table = 'domiciliacion_subscriptions';

    protected $fillable = [
        'client_id', 'client_recurring_card_id', 'openpay_customer_id', 'openpay_card_id',
        'domiciliacion_plan_id', 'openpay_subscription_id', 'amount', 'status', 'charge_date', 'created_by',
    ];

    protected $casts = ['amount' => 'decimal:2', 'charge_date' => 'date'];

    // active/trial/past_due cuentan como vigentes; unpaid/cancelled no.
    public function scopeVigente($q)
    {
        return $q->whereIn('status', ['active', 'trial', 'past_due']);
    }

    public function scopeForClient($q, int $clientId)
    {
        return $q->where('client_id', $clientId);
    }
}
