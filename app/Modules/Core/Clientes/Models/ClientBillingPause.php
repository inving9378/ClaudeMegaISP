<?php

namespace App\Modules\Core\Clientes\Models;

use App\Models\BaseModel;
use App\Models\Invoice;
use App\Models\User;

class ClientBillingPause extends BaseModel
{
    protected $table = 'client_billing_pauses';

    public const TIPO_SIN_CUOTA = 'sin_cuota';
    public const TIPO_CON_CUOTA = 'con_cuota';

    public const ESTADO_ESPERANDO_PAGO = 'esperando_pago';
    public const ESTADO_PROGRAMADA = 'programada';
    public const ESTADO_EN_CURSO = 'en_curso';
    public const ESTADO_CONCLUIDA = 'concluida';
    public const ESTADO_CANCELADA = 'cancelada';
    public const ESTADO_REANUDADA_ANTICIPADA = 'reanudada_anticipada';

    // Estados que cuentan como "pausa consumida" para los topes de la ventana móvil de 12 meses.
    public const ESTADOS_CONSUMEN_TOPE = [
        self::ESTADO_EN_CURSO,
        self::ESTADO_CONCLUIDA,
        self::ESTADO_REANUDADA_ANTICIPADA,
    ];

    // Estados que bloquean solicitar una pausa nueva (ya hay una viva).
    public const ESTADOS_ACTIVOS = [
        self::ESTADO_ESPERANDO_PAGO,
        self::ESTADO_PROGRAMADA,
        self::ESTADO_EN_CURSO,
    ];

    protected $fillable = [
        'client_id', 'tipo', 'meses', 'fecha_inicio', 'fecha_fin', 'fecha_fin_real',
        'estado', 'cuota_mensual', 'monto_cuota', 'balance_al_crear', 'factura_cuota_id',
        'motivo', 'canal', 'evidencia_path',
        'created_by', 'updated_by', 'cancelled_by',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'fecha_fin_real' => 'datetime',
        'meses' => 'integer',
        'cuota_mensual' => 'decimal:2',
        'monto_cuota' => 'decimal:2',
        'balance_al_crear' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($obj) {
            $obj->created_by = auth()->user() ? auth()->user()->id : ($obj->created_by ?? null);
            $obj->updated_by = auth()->user() ? auth()->user()->id : ($obj->updated_by ?? null);
        });

        static::updating(function ($obj) {
            $obj->updated_by = auth()->user() ? auth()->user()->id : $obj->updated_by;
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function facturaCuota()
    {
        return $this->belongsTo(Invoice::class, 'factura_cuota_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceladoPor()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
