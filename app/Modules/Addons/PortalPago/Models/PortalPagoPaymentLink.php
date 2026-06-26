<?php

namespace App\Modules\Addons\PortalPago\Models;

use App\Models\ClientInvoice;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Liga de pago por token: expone UNA factura (client_invoices) al cliente.
 */
class PortalPagoPaymentLink extends Model
{
    protected $table = 'portal_pago_payment_links';

    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_REPORTADO  = 'reportado';
    public const ESTADO_VALIDADO   = 'validado';
    public const ESTADO_CONCILIADO = 'conciliado';
    public const ESTADO_EXPIRADO   = 'expirado';
    public const ESTADO_RECHAZADO  = 'rechazado';

    protected $fillable = [
        'token',
        'document_id',
        'client_id',
        'account_id',
        'monto_esperado',
        'referencia_unica',
        'estado',
        'expira_at',
    ];

    protected $casts = [
        'document_id'    => 'integer',
        'client_id'      => 'integer',
        'account_id'     => 'integer',
        'monto_esperado' => 'decimal:2',
        'expira_at'      => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(PortalPagoAccount::class, 'account_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PortalPagoPaymentReport::class, 'payment_link_id');
    }

    /**
     * Factura legacy (client_invoices) que cubre esta liga. FK logica.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'document_id');
    }

    /**
     * Cliente dueño de la factura. FK logica.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function isExpired(): bool
    {
        return $this->expira_at !== null && $this->expira_at->isPast();
    }
}
