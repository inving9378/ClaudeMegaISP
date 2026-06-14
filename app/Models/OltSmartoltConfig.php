<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Crypt;

class OltSmartoltConfig extends BaseModel
{
    protected $table = 'olt_smartolt_config';

    protected $fillable = [
        'api_domain', 'api_token', 'ttl', 'hourly_budget', 'activa',
    ];

    protected $hidden = ['api_token'];

    protected $casts = [
        'activa'        => 'boolean',
        'ttl'           => 'integer',
        'hourly_budget' => 'integer',
    ];

    // ── Cifrado del token ──────────────────────────────────────────────────

    public function getApiTokenAttribute(): string
    {
        try {
            return Crypt::decryptString($this->attributes['api_token'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    public function setApiTokenAttribute(string $value): void
    {
        $this->attributes['api_token'] = $value !== '' ? Crypt::encryptString($value) : null;
    }

    // ── Acceso singleton ───────────────────────────────────────────────────

    /**
     * Devuelve la única fila de configuración (o una instancia vacía sin guardar).
     * Usar ->activa para saber si las credenciales ya están configuradas.
     */
    public static function current(): static
    {
        return static::firstOrNew(['id' => 1]);
    }

    /**
     * ¿Hay credenciales cargadas y marcadas como activas?
     * Los consumers pueden decidir si caen al .env cuando esto es false.
     */
    public static function isConfigured(): bool
    {
        $c = static::current();
        return $c->exists && $c->activa && $c->api_domain && $c->getApiTokenAttribute() !== '';
    }
}
