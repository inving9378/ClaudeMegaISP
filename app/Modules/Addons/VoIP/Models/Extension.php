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
        // Plan de numeración (#9990718 §7): rango del que hereda, perfil de
        // sobrescritura, departamento de origen y marca de sembrada por el sistema.
        'voip_rango_numeracion_id', 'voip_perfil_extension_id',
        'departamento', 'sembrada_por_sistema',
    ];

    protected $casts = [
        'activo'               => 'boolean',
        'provisionado_at'      => 'datetime',
        'sembrada_por_sistema' => 'boolean',
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

    public function rango()
    {
        return $this->belongsTo(RangoNumeracion::class, 'voip_rango_numeracion_id');
    }

    /**
     * Perfil de SOBRESCRITURA, no el efectivo. Null es el caso corriente: la
     * extensión hereda el de su rango. Para el perfil que de verdad aplica,
     * usar ResolverPerfilEfectivo — no esta relación.
     */
    public function perfilSobrescrito()
    {
        return $this->belongsTo(PerfilExtension::class, 'voip_perfil_extension_id');
    }
}
