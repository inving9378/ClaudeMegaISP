<?php

namespace App\Modules\Addons\Payments\Models;

use App\Models\BaseModel;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ticket de conciliación: pago/transferencia dudosa por resolver.
 * Mientras exista status=open, el listado de clientes muestra banner persistente.
 */
class ReconciliationTicket extends BaseModel
{
    use SoftDeletes;

    protected $table = 'reconciliation_tickets';

    protected $fillable = [
        'client_id',
        'payment_id',
        'reason',
        'detail',
        'amount',
        'status',
        'resolved_by',
        'resolved_at',
        'created_by',
    ];

    protected $casts = [
        'amount'      => 'double',
        'resolved_at' => 'datetime',
    ];

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
