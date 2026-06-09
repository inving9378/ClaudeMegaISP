<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Troncal extends Model
{
    protected $table = 'voip_troncales';

    protected $fillable = [
        'nombre', 'proveedor', 'tipo', 'direccion',
        'host', 'puerto', 'usuario', 'secret',
        'contexto', 'did', 'codecs', 'transporte',
        'activo', 'provisionado_at',
    ];

    protected $casts = [
        'activo'          => 'boolean',
        'provisionado_at' => 'datetime',
        'puerto'          => 'integer',
    ];

    protected $hidden = ['secret'];

    // ── Mutators de cifrado ──────────────────────────────────────────────────

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

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function endpointId(): string
    {
        return "trunk_{$this->id}";
    }

    public function estiProvisionada(): bool
    {
        return $this->provisionado_at !== null;
    }
}
