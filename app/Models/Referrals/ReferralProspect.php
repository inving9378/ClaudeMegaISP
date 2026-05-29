<?php

namespace App\Models\Referrals;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralProspect extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\ReferralProspectFactory::new();
    }

    protected $table = 'referral_prospects';

    protected $fillable = [
        'embajador_id',
        'name',
        'phone',
        'email',
        'address',
        'source',
        'status',
        'notes',
        'converted_client_id',
        'converted_at',
        'last_contact_at',
    ];

    protected $casts = [
        'converted_at'    => 'datetime',
        'last_contact_at' => 'datetime',
    ];

    public function embajador(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'embajador_id');
    }

    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(ProspectFollowup::class, 'prospect_id');
    }

    public function referral(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Referral::class, 'prospect_id');
    }
}
