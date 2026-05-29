<?php

namespace App\Modules\Addons\CobranzaBlaster\Models;

use Illuminate\Database\Eloquent\Model;

class CobranzaLlamadaEvento extends Model
{
    protected $table = 'cobranza_llamada_eventos';

    public $timestamps = false;

    protected $fillable = [
        'llamada_id', 'evento', 'payload', 'ocurrido_at',
    ];

    protected $casts = [
        'payload'     => 'array',
        'ocurrido_at' => 'datetime',
    ];

    public function llamada()
    {
        return $this->belongsTo(CobranzaLlamada::class, 'llamada_id');
    }
}
