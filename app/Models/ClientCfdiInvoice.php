<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CFDI 4.0 emitido para un Payment (item #117, Fase 1 — base interna de
 * almacenamiento). Sin PAC real conectado todavía: tabla poblada solo cuando
 * exista un adaptador de TimbradoServiceInterface distinto de NullTimbradoService.
 */
class ClientCfdiInvoice extends BaseModel
{
    use SoftDeletes;

    protected $table = 'client_cfdi_invoices';

    protected $fillable = [
        'client_id',
        'payment_id',
        'client_fiscal_data_id',
        'estado',
        'proveedor_pac',
        'uuid',
        'serie',
        'folio',
        'rfc_emisor',
        'rfc_receptor',
        'razon_social_receptor',
        'uso_cfdi',
        'regimen_fiscal_receptor',
        'moneda',
        'subtotal',
        'iva',
        'total',
        'xml_path',
        'pdf_path',
        'qr_path',
        'cadena_original',
        'sello_cfdi',
        'sello_sat',
        'no_certificado_cfdi',
        'no_certificado_sat',
        'fecha_timbrado',
        'motivo_cancelacion',
        'cancelada_at',
        'respuesta_pac',
        'error_mensaje',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'respuesta_pac' => 'array',
        'fecha_timbrado' => 'datetime',
        'cancelada_at' => 'datetime',
    ];

    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_TIMBRADA = 'timbrada';
    const ESTADO_CANCELADA = 'cancelada';
    const ESTADO_ERROR = 'error';

    public function client()
    {
        return $this->belongsTo(\App\Modules\Core\Clientes\Models\Client::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function fiscalData()
    {
        return $this->belongsTo(ClientFiscalData::class, 'client_fiscal_data_id');
    }
}
