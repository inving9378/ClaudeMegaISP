<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de llamadas. Solo-lectura desde PHP — las filas las escribe
 * Asterisk directo vía cdr_adaptive_odbc (ver la migración).
 */
class Llamada extends Model
{
    protected $connection = 'asterisk_rt';

    protected $table = 'voip_llamadas';

    public $timestamps = false;

    protected $casts = [
        'start'  => 'datetime',
        'answer' => 'datetime',
        'end'    => 'datetime',
    ];

    public function grabacionPath(): ?string
    {
        if (! $this->grabacion) {
            return null;
        }

        return rtrim(config('voip.grabaciones.dir'), '/') . '/' . $this->grabacion;
    }

    public function scopeContestadas($query)
    {
        return $query->where('disposition', 'ANSWERED');
    }
}
