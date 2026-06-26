<?php

namespace App\Modules\Addons\PortalPago\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reporte de pago: clave de rastreo SPEI + resultado de validacion CEP.
 */
class PortalPagoPaymentReport extends Model
{
    protected $table = 'portal_pago_payment_reports';

    public const ESTADO_PENDIENTE   = 'pendiente_validacion';
    public const ESTADO_VALIDADO    = 'validado';
    public const ESTADO_DISCREPANCIA = 'discrepancia';
    public const ESTADO_RECHAZADO   = 'rechazado';

    protected $fillable = [
        'payment_link_id',
        'clave_rastreo',
        'banco_emisor',
        'fecha_operacion',
        'monto_reportado',
        'comprobante_path',
        'cep_validado',
        'cep_resultado',
        'cep_xml_path',
        'estado',
        'revisado_por',
        'revisado_at',
    ];

    protected $casts = [
        'payment_link_id' => 'integer',
        'fecha_operacion' => 'date',
        'monto_reportado' => 'decimal:2',
        'cep_validado'    => 'boolean',
        'cep_resultado'   => 'array',
        'revisado_por'    => 'integer',
        'revisado_at'     => 'datetime',
    ];

    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PortalPagoPaymentLink::class, 'payment_link_id');
    }

    /**
     * Admin que reviso/aprobo manualmente el reporte. FK logica.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
