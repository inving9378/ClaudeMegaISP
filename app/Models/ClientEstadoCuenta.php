<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Estado de cuenta generado para un cliente (FR-02 #536): «final» al pasar a Cancelado; «mensual»
 * en la Ronda 2 (#540). Documento informativo sin valor fiscal. Generarlo no lo envía.
 */
class ClientEstadoCuenta extends Model
{
    protected $table = 'client_estados_cuenta';

    public const TIPO_FINAL = 'final';
    public const TIPO_MENSUAL = 'mensual';

    protected $fillable = [
        'client_id', 'tipo', 'clave', 'estado_cliente', 'saldo', 'adeudo', 'facturas_pendientes',
        'pdf_path', 'pdf_sha256', 'origen', 'generado_por', 'error',
    ];

    protected $casts = [
        'saldo' => 'float',
        'adeudo' => 'float',
        'facturas_pendientes' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(\App\Modules\Core\Clientes\Models\Client::class);
    }
}
