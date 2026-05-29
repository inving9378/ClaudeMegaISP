<?php

namespace App\Models\Referrals;

use Illuminate\Database\Eloquent\Model;

class ReferralNotificationTemplate extends Model
{
    protected $table = 'referral_notification_templates';

    protected $fillable = [
        'event_type',
        'label',
        'channel',
        'body_template',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public static function forEvent(string $eventType): ?self
    {
        return static::where('event_type', $eventType)->where('active', true)->first();
    }

    public function render(array $placeholders): string
    {
        $body = $this->body_template;
        foreach ($placeholders as $key => $value) {
            $body = str_replace('{{' . $key . '}}', (string) $value, $body);
        }
        return $body;
    }
}
