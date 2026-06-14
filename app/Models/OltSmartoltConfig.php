<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Crypt;

class OltSmartoltConfig extends BaseModel
{
    protected $table = 'olt_smartolt_config';

    protected $fillable = [
        'api_domain', 'api_token', 'ttl', 'hourly_budget', 'activa',
        'alertas_olt_activas', 'alertas_rol_destino',
        'last_sync_ok_at', 'last_sync_error_at', 'connected_since',
        'last_error_message', 'last_olt_count',
    ];

    protected $hidden = ['api_token'];

    protected $casts = [
        'activa'               => 'boolean',
        'alertas_olt_activas'  => 'boolean',
        'ttl'                  => 'integer',
        'hourly_budget'        => 'integer',
        'last_olt_count'     => 'integer',
        'last_sync_ok_at'    => 'datetime',
        'last_sync_error_at' => 'datetime',
        'connected_since'    => 'datetime',
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

    // ── Heartbeat de conexión ─────────────────────────────────────────────

    /**
     * Estampa una sincronización exitosa.
     * connected_since se fija solo en la primera conexión sana tras una caída
     * (cuando era NULL); si ya tenía valor, no se toca (sigue el reloj corriendo).
     */
    public function markSyncOk(int $oltCount): void
    {
        $now = now();
        $this->last_sync_ok_at  = $now;
        $this->last_olt_count   = $oltCount;
        $this->last_error_message = null;
        if ($this->connected_since === null) {
            $this->connected_since = $now;
        }
        $this->save();
    }

    /**
     * Estampa un fallo de sincronización.
     * Resetea connected_since a NULL para que la próxima conexión sana
     * reinicie el reloj de "conectada desde".
     */
    public function markSyncError(string $msg): void
    {
        $this->last_sync_error_at = now();
        $this->last_error_message = mb_substr($msg, 0, 500);
        $this->connected_since    = null;
        $this->save();
    }
}
