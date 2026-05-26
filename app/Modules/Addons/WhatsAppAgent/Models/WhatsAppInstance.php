<?php

namespace App\Modules\Addons\WhatsAppAgent\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class WhatsAppInstance extends BaseModel
{
    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'name',
        'slug',
        'instance_id',
        'api_url',
        'api_key',
        'webhook_secret',
        'default_instance',
        'active',
        'phone_number',
        'status',
        'qr_code',
        'qr_expires_at',
    ];

    protected $casts = [
        'default_instance' => 'boolean',
        'active'           => 'boolean',
        'qr_expires_at'    => 'datetime',
    ];

    protected $hidden = [
        'api_key',
        'webhook_secret',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->webhook_secret)) {
                $model->webhook_secret = bin2hex(random_bytes(32));
            }
        });
    }

    public function getDecryptedApiKeyAttribute(): string
    {
        try {
            return Crypt::decryptString($this->api_key);
        } catch (\Throwable $e) {
            return (string) $this->api_key;
        }
    }

    public function getIsConnectedAttribute(): bool
    {
        return $this->status === 'connected';
    }

    public function getQrIsValidAttribute(): bool
    {
        return $this->qr_expires_at && now()->lt($this->qr_expires_at);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('default_instance', true);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class, 'instance_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'instance_id');
    }
}
