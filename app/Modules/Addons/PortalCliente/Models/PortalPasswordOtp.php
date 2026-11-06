<?php

namespace App\Modules\Addons\PortalCliente\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Item #473 — un código solicitado para recuperar la contraseña del Portal Cliente.
 * Solo guarda hashes (otp_hash, reset_token_hash), nunca los valores en claro.
 */
class PortalPasswordOtp extends Model
{
    public const MAX_ATTEMPTS = 5;

    public $timestamps = false;

    protected $table = 'portal_password_otps';

    protected $fillable = [
        'client_id',
        'otp_hash',
        'canal',
        'destino_enmascarado',
        'expires_at',
        'attempts',
        'verified_at',
        'reset_token_hash',
        'reset_token_expires_at',
        'reset_token_used_at',
        'ip',
        'user_agent',
    ];

    protected $hidden = ['otp_hash', 'reset_token_hash'];

    protected $casts = [
        'attempts'               => 'integer',
        'expires_at'             => 'datetime',
        'verified_at'            => 'datetime',
        'reset_token_expires_at' => 'datetime',
        'reset_token_used_at'    => 'datetime',
        'created_at'             => 'datetime',
    ];

    /**
     * Gasta un intento ANTES de comparar el código: es un solo UPDATE condicionado, así peticiones
     * simultáneas no pueden rebasar el límite. False = el código ya no admite intentos.
     */
    public function reservarIntento(): bool
    {
        return static::whereKey($this->getKey())
            ->whereNull('verified_at')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where('expires_at', '>', now())
            ->increment('attempts') === 1;
    }

    public function resetTokenValido(string $token): bool
    {
        if (! $this->verified_at || $this->reset_token_used_at || ! $this->reset_token_hash) {
            return false;
        }
        if (! $this->reset_token_expires_at || now()->greaterThan($this->reset_token_expires_at)) {
            return false;
        }

        return Hash::check($token, $this->reset_token_hash);
    }
}
