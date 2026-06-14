<?php

namespace App\Modules\Addons\PortalCliente\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de autenticación para el portal cliente.
 * Tabla: client_main_information
 *
 * NOTA DE SEGURIDAD DELIBERADA: el campo portal_password usa bcrypt (Hash::make),
 * a diferencia del campo password legacy del admin que usa base64.
 * Esta decisión es intencional — el portal es superficie pública expuesta a internet.
 */
class PortalClient extends Model implements AuthenticatableContract
{
    use Authenticatable, SoftDeletes;

    protected $table = 'client_main_information';

    protected $fillable = [
        'portal_password',
        'portal_registered_at',
        'portal_last_login_at',
    ];

    protected $hidden = ['portal_password', 'password'];

    protected $casts = [
        'portal_registered_at' => 'datetime',
        'portal_last_login_at' => 'datetime',
    ];

    // ── Authenticatable overrides ─────────────────────────────────────────

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return $this->portal_password ?? '';
    }

    public function getAuthPasswordName(): string
    {
        return 'portal_password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Nombre completo del cliente.
     */
    public function getNombreCompletoAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->name,
            $this->father_last_name,
            $this->mother_last_name,
        ])));
    }

    /**
     * El Client padre (para acceder a clients.id = número de cliente).
     */
    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    /**
     * Plan de internet activo.
     */
    public function servicioInternet()
    {
        return $this->hasOne(\App\Models\ClientInternetService::class, 'client_id', 'client_id')
            ->where('estado', '!=', 'Eliminado')
            ->orderByDesc('id');
    }
}
