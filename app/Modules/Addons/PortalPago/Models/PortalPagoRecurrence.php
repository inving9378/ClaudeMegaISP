<?php

namespace App\Modules\Addons\PortalPago\Models;

use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recurrencia asistida: liga SPEI mensual segun dia_corte (NO auto-debito).
 */
class PortalPagoRecurrence extends Model
{
    protected $table = 'portal_pago_recurrences';

    protected $fillable = [
        'client_id',
        'account_id',
        'dia_corte',
        'monto',
        'activa',
        'ultimo_link_enviado_at',
    ];

    protected $casts = [
        'client_id'              => 'integer',
        'account_id'             => 'integer',
        'dia_corte'              => 'integer',
        'monto'                  => 'decimal:2',
        'activa'                 => 'boolean',
        'ultimo_link_enviado_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(PortalPagoAccount::class, 'account_id');
    }

    /**
     * Cliente al que se le envia la liga recurrente. FK logica.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }
}
