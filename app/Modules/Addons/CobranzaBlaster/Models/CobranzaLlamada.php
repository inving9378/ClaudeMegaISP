<?php

namespace App\Modules\Addons\CobranzaBlaster\Models;

use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CobranzaLlamada extends Model
{
    protected $table = 'cobranza_llamadas';

    protected $fillable = [
        'campana_id', 'client_id', 'telefono', 'intentos', 'estado',
        'ultimo_intento_at', 'proximo_intento_at',
        'ami_channel', 'ami_uniqueid', 'monto_vencido',
    ];

    protected $casts = [
        'monto_vencido'       => 'decimal:2',
        'intentos'            => 'integer',
        'ultimo_intento_at'   => 'datetime',
        'proximo_intento_at'  => 'datetime',
    ];

    public function campana()
    {
        return $this->belongsTo(CobranzaCampana::class, 'campana_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function eventos()
    {
        return $this->hasMany(CobranzaLlamadaEvento::class, 'llamada_id');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente')
            ->where('proximo_intento_at', '<=', now());
    }

    public function scopeParaReintentar($query)
    {
        return $query->whereIn('estado', ['no_contesto', 'ocupado'])
            ->whereColumn('intentos', '<', DB::raw(
                '(SELECT max_intentos FROM cobranza_campanas WHERE id = cobranza_llamadas.campana_id)'
            ));
    }
}
