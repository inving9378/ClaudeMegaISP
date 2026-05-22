<?php

namespace App\Modules\Addons\WhatsAppAgent\Models;

use App\Models\BaseModel;
use App\Models\Client;
use App\Models\User;
use App\Modules\Core\CRM\Models\Crm;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WhatsAppConversation extends BaseModel
{
    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'instance_id',
        'contact_number',
        'contact_name',
        'client_id',
        'crm_id',
        'seller_id',
        'status',
        'unread_count',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'unread_count'    => 'integer',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'instance_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function crm(): BelongsTo
    {
        return $this->belongsTo(Crm::class, 'crm_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id')
            ->orderBy('created_at');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(WhatsAppMessage::class, 'conversation_id')->latestOfMany();
    }

    public function scopeForSeller($query, int $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeWithUnread($query)
    {
        return $query->where('unread_count', '>', 0);
    }

    public function markRead(): void
    {
        $this->update(['unread_count' => 0]);
    }

    public function incrementUnread(): void
    {
        $this->increment('unread_count');
    }
}
