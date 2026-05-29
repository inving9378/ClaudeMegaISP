<?php

namespace App\Models\Referrals;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralShareLog extends Model
{
    protected $table = 'referral_share_logs';

    public $timestamps = false;

    protected $fillable = [
        'embajador_id',
        'channel',
        'contacts_count',
        'message_template',
        'shared_at',
        'created_at',
    ];

    protected $casts = [
        'shared_at'  => 'datetime',
        'created_at' => 'datetime',
    ];

    public function embajador(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'embajador_id');
    }
}
