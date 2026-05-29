<?php

namespace App\Models\Referrals;

use Illuminate\Database\Eloquent\Model;

class ReferralNotificationLog extends Model
{
    protected $table = 'referral_notification_logs';

    protected $fillable = [
        'client_id',
        'event_type',
        'body_sent',
        'sent_at',
        'success',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'success' => 'boolean',
    ];
}
