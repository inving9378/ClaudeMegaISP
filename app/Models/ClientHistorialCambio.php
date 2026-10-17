<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** FR-04 (#538): un cambio de estado o de contrato del cliente. Solo se agrega, nunca se edita. */
class ClientHistorialCambio extends Model
{
    protected $table = 'client_historial_cambios';

    public const UPDATED_AT = null;

    public const ORIGEN_USUARIO = 'usuario';
    public const ORIGEN_SISTEMA = 'sistema';
    public const ORIGEN_PAGO = 'pago';

    protected $fillable = ['client_id', 'campo', 'valor_anterior', 'valor_nuevo', 'origen', 'user_id', 'motivo'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
