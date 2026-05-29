<?php

namespace App\Modules\Addons\CobranzaBlaster\Models;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Crypt;

class VoipConfiguracion extends BaseModel
{
    protected $table = 'voip_configuracion';

    protected $fillable = [
        'nombre', 'sip_host', 'sip_port', 'sip_username', 'sip_secret',
        'sip_fromuser', 'sip_fromdomain', 'estado', 'ultimo_registro_at',
        'callerid_nombre', 'callerid_numero', 'activa',
    ];

    protected $casts = [
        'activa'   => 'boolean',
        'sip_port' => 'integer',
    ];

    protected $hidden = ['sip_secret'];

    public function getSipSecretAttribute(): string
    {
        try {
            return Crypt::decryptString($this->attributes['sip_secret'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    public function setSipSecretAttribute(string $value): void
    {
        $this->attributes['sip_secret'] = Crypt::encryptString($value);
    }

    public function scopeActiva($query): ?self
    {
        return $query->where('activa', true)->first();
    }
}
