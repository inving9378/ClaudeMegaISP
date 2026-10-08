<?php

namespace App\Models\Asistente;

use Illuminate\Database\Eloquent\Model;

/** Cambio que JARVIS preparó y espera la confirmación del usuario. */
class Accion extends Model
{
    public const PENDIENTE = 'pendiente';
    public const EJECUTADA = 'ejecutada';
    public const CANCELADA = 'cancelada';
    public const EXPIRADA  = 'expirada';
    public const FALLIDA   = 'fallida';

    protected $table = 'asistente_acciones';

    protected $guarded = [];

    protected $casts = [
        'argumentos'  => 'array',
        'resultado'   => 'array',
        'expira_at'   => 'datetime',
        'resuelta_at' => 'datetime',
    ];

    public function vigente(): bool
    {
        return $this->estado === self::PENDIENTE && ($this->expira_at === null || $this->expira_at->isFuture());
    }
}
