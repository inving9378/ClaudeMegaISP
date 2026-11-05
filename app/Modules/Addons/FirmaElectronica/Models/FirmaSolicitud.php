<?php

namespace App\Modules\Addons\FirmaElectronica\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FirmaSolicitud extends Model
{
    public const PENDIENTE_ENVIO = 'pendiente_envio';
    public const ENVIADA = 'enviada';
    public const ENTREGADA = 'entregada';
    public const ABIERTA = 'abierta';
    public const PENDIENTE_FIRMA = 'pendiente_firma';
    public const PARCIAL = 'parcialmente_firmada';
    public const FIRMADA = 'firmada';
    public const RECHAZADA = 'rechazada';
    public const EXPIRADA = 'expirada';
    public const CANCELADA = 'cancelada';
    public const ERROR = 'error';

    /** Estados terminales: ningún evento los modifica. */
    public const TERMINALES = [self::FIRMADA, self::RECHAZADA, self::EXPIRADA, self::CANCELADA];

    /** Progreso de los estados "de avance": un evento tardío nunca retrocede. */
    public const RANGO = [
        self::PENDIENTE_ENVIO => 0, self::ENVIADA => 1, self::ENTREGADA => 2,
        self::ABIERTA => 3, self::PENDIENTE_FIRMA => 4, self::PARCIAL => 5,
    ];

    protected $table = 'firma_solicitudes';
    protected $guarded = ['id'];
    protected $casts = ['expira_at' => 'datetime', 'orden_secuencial' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->uuid = $m->uuid ?: (string) Str::uuid());
    }

    public function participantes()
    {
        return $this->hasMany(FirmaParticipante::class, 'solicitud_id')->orderBy('orden');
    }

    public function esTerminal(): bool
    {
        return in_array($this->estado, self::TERMINALES, true);
    }
}
