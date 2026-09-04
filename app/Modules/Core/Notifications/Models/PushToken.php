<?php

namespace App\Modules\Core\Notifications\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushToken extends BaseModel
{
    protected $table = 'push_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_id',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
