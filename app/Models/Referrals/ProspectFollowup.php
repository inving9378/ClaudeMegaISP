<?php

namespace App\Models\Referrals;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectFollowup extends Model
{
    protected $table = 'prospect_followups';

    public $timestamps = false;

    protected $fillable = [
        'prospect_id',
        'embajador_id',
        'action',
        'notes',
        'next_action_date',
        'created_at',
    ];

    protected $casts = [
        'next_action_date' => 'date',
        'created_at'       => 'datetime',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(ReferralProspect::class, 'prospect_id');
    }

    public function embajador(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'embajador_id');
    }
}
