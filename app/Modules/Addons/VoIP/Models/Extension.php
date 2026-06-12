<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use App\Models\User;

class Extension extends Model
{
    protected $table = 'voip_extensiones';

    protected $fillable = [
        'numero', 'nombre', 'secret', 'user_id',
        'tipo_dispositivo', 'contexto', 'codecs', 'transporte',
        'callerid', 'activo', 'provisionado_at',
    ];

    protected $casts = [
        'activo'          => 'boolean',
        'provisionado_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    public function setSecretAttribute(?string $value): void
    {
        $this->attributes['secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getSecretPlainAttribute(): ?string
    {
        if (empty($this->attributes['secret'])) {
            return null;
        }
        try {
            return Crypt::decryptString($this->attributes['secret']);
        } catch (\Throwable) {
            return null;
        }
    }

    public function endpointId(): string
    {
        return $this->numero;
    }

    public function estaProvisionada(): bool
    {
        return $this->provisionado_at !== null;
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
