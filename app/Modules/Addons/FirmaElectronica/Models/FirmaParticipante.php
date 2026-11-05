<?php

namespace App\Modules\Addons\FirmaElectronica\Models;

use Illuminate\Database\Eloquent\Model;

class FirmaParticipante extends Model
{
    protected $table = 'firma_participantes';
    protected $guarded = ['id'];
    protected $hidden = ['token_hash', 'otp_hash'];
    protected $casts = ['expira_at' => 'datetime', 'usado_at' => 'datetime', 'otp_expira_at' => 'datetime', 'firmado_at' => 'datetime'];

    public function solicitud()
    {
        return $this->belongsTo(FirmaSolicitud::class, 'solicitud_id');
    }
}
