<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Troncal extends Model
{
    protected $table = 'voip_troncales';

    protected $fillable = [
        'nombre', 'proveedor', 'tipo', 'direccion', 'proposito',
        'host', 'puerto', 'usuario', 'secret',
        'contexto', 'did', 'grupo_entrante_id', 'codecs', 'transporte',
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

    // ── Relaciones ───────────────────────────────────────────────────────────

    public function grupoEntrante(): BelongsTo
    {
        return $this->belongsTo(GrupoTimbrado::class, 'grupo_entrante_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function endpointId(): string
    {
        return "trunk_{$this->id}";
    }

    /**
     * Contexto del plan de marcado al que Asterisk manda las llamadas que entran por
     * esta troncal. Con grupo de entrada, el que genera el plan (inbound-trunk-N →
     * el grupo); sin grupo, el configurado. Es el único lugar que lo decide: antes el
     * alta, la edición y «Provisionar» ponían `contexto` (from-trunk, que no existe en
     * el plan, o vacío al crear) encima del que había dejado el plan, y las llamadas
     * de entrada se perdían aunque la troncal estuviera registrada.
     */
    public function contextoEntrante(): string
    {
        if ($this->grupo_entrante_id) {
            return "inbound-trunk-{$this->id}";
        }

        return trim((string) $this->contexto) !== '' ? (string) $this->contexto : 'from-trunk';
    }

    public function estiProvisionada(): bool
    {
        return $this->provisionado_at !== null;
    }
}
