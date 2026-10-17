<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Evidencia de un PDF de cobro enviado (FAC-05, item #510): bytes exactos en billing.pdf_disk,
 * sha256 y estado según la evidencia disponible. Ver EvidenciaDocumentoService.
 */
class BillingDocumentoEvidencia extends Model
{
    protected $table = 'billing_documento_evidencias';

    public const ESTADO_GENERADO = 'generado';
    public const ESTADO_ENCOLADO = 'encolado';
    public const ESTADO_ACEPTADO = 'aceptado';
    public const ESTADO_FALLIDO = 'fallido';

    public const ORIGEN_AUTOMATICO = 'envio_automatico';
    public const ORIGEN_MANUAL = 'enviar_manual';

    protected $fillable = [
        'origen', 'billing_notification_id', 'client_id', 'invoice_id', 'payment_id', 'documento', 'canal',
        'destinatario', 'asunto', 'archivo', 'nombre_adjunto', 'sha256', 'bytes', 'estado', 'interceptado',
        'message_id', 'error', 'generado_at', 'encolado_at', 'aceptado_at',
    ];

    protected $casts = [
        'interceptado' => 'boolean',
        'bytes'        => 'integer',
        'generado_at'  => 'datetime',
        'encolado_at'  => 'datetime',
        'aceptado_at'  => 'datetime',
    ];
}
