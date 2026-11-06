<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Código OTP (segundo factor) para login del padre. Se emite ANTES de tener
 * sesión (no hay auth user) → modelo plano, sin BaseModel/auto-stamp.
 */
class ParentalOtpCode extends Model
{
    protected $table = 'parental_otp_codes';

    protected $fillable = [
        'client_main_information_id', 'destino', 'canal', 'proposito',
        'code_hash', 'intentos', 'max_intentos', 'expira_at',
        'bloqueado_hasta', 'verificado_at',
    ];

    protected $casts = [
        'expira_at'       => 'datetime',
        'bloqueado_hasta' => 'datetime',
        'verificado_at'   => 'datetime',
    ];
}
