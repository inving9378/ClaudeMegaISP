<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CFDI (XML+PDF) ya timbrado fuera del sistema, adjuntado a una factura de
 * client_invoices para que el Portal Cliente lo muestre/descargue.
 * No genera ni timbra facturas — ver App\Services\Finance\Timbrado.
 */
class ClientInvoiceCfdi extends Model
{
    use SoftDeletes;

    protected $table = 'client_invoice_cfdi';

    protected $fillable = [
        'client_invoice_id',
        'uuid_fiscal',
        'serie',
        'folio',
        'rfc_receptor',
        'xml_path',
        'pdf_path',
        'timbrado_at',
        'cancelado_at',
        'motivo_cancelacion',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'timbrado_at'  => 'datetime',
        'cancelado_at' => 'datetime',
    ];

    public function clientInvoice()
    {
        return $this->belongsTo(\App\Modules\Core\Clientes\Models\ClientInvoice::class, 'client_invoice_id');
    }
}
