<?php

namespace App\Models\Referrals;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Traits\BelongsToClientTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCommission extends Model
{
    use HasFactory;
    use BelongsToClientTenant;

    /** Aislamiento por cliente fail-closed. INERTE: solo actúa si una query llama ->forClient(). */
    protected string $tenantColumn = 'beneficiary_id';
    protected bool $allowNullTenant = false;

    protected static function newFactory()
    {
        return \Database\Factories\ReferralCommissionFactory::new();
    }

    protected $table = 'referral_commissions';

    protected $fillable = [
        'beneficiary_id',
        'referral_id',
        'invoice_id',
        'level',
        'commission_pct',
        'base_amount',
        'commission_amount',
        'period_month',
        'period_year',
        'status',
        'apply_after_at',
        'applied_at',
        'applied_invoice_id',
    ];

    protected $dates = [
        'apply_after_at',
        'applied_at',
    ];

    protected $casts = [
        'commission_pct'    => 'decimal:2',
        'base_amount'       => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'beneficiary_id');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'invoice_id');
    }

    public function appliedInvoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'applied_invoice_id');
    }
}
