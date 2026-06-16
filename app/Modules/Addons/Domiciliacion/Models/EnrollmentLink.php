<?php

namespace App\Modules\Addons\Domiciliacion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EnrollmentLink extends Model
{
    protected $table = 'enrollment_links';

    protected $fillable = [
        'client_id',
        'token',
        'channel',
        'expires_at',
        'used_at',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
