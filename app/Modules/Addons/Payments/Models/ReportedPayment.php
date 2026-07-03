<?php

namespace App\Modules\Addons\Payments\Models;

use App\Models\BaseModel;
use App\Models\MethodOfPayment;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Addons\PortalPago\Models\PortalPagoAccount;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Registro del pago reportado capturado por mostrador. Liga el pago aplicado
 * (payments) con su comprobante y el estado de conciliación de Tere.
 * El abono de saldo vive en payments/observers — este registro es metadata.
 */
class ReportedPayment extends BaseModel
{
    use SoftDeletes;

    protected $table = 'reported_payments';

    protected $fillable = [
        'payment_id',
        'client_id',
        'receiver_account_id',
        'method_of_payment_id',
        'amount',
        'fecha_pago',
        'clave_rastreo',
        'dedup_fingerprint',
        'titular',
        'banco_origen',
        'referencia_oxxo',
        'tecnico_id',
        'numero_autorizacion',
        'ultimos4_tarjeta',
        'comprobante_path',
        'conciliation_status',
        'verified_by',
        'verified_at',
        'conciliation_note',
        'identified_by_user_id',
        'confirmed_by_user_id',
        'identification_session_id',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'fecha_pago'  => 'date',
        'verified_at' => 'datetime',
    ];

    public const ESTADO_PENDIENTE  = 'pendiente_verificar';
    public const ESTADO_VERIFICADO = 'verificado';
    public const ESTADO_RECHAZADO  = 'rechazado';

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function receiverAccount()
    {
        return $this->belongsTo(PortalPagoAccount::class, 'receiver_account_id');
    }

    public function method()
    {
        return $this->belongsTo(MethodOfPayment::class, 'method_of_payment_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function tecnico()
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }
}
