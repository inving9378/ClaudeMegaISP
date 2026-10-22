<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Item #571, Parte B: una corrección de facturación (propuesta, aplicada sola o resuelta por una
 * persona). Es la bitácora en la base; ver CorreccionesFacturacion.
 */
class FacturacionCorreccion extends Model
{
    protected $table = 'facturacion_correcciones';

    public const REACOMODO = 'reacomodo';
    public const MONTO_PAGO = 'monto_pago';
    public const REVISION = 'revision';

    public const AUTOMATICA = 'automatica';
    public const DECIDIDA = 'decidida';
    public const PROPUESTA = 'propuesta';

    public const PENDIENTE = 'pendiente';
    public const APLICADA = 'aplicada';
    public const DESCARTADA = 'descartada';
    public const OBSOLETA = 'obsoleta';

    protected $guarded = ['id'];

    protected $casts = [
        'parametros' => 'array',
        'antes' => 'array',
        'despues' => 'array',
        'deuda_antes' => 'float',
        'deuda_despues' => 'float',
        'resuelta_at' => 'datetime',
    ];

    public function resolvio()
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }
}
