<?php

namespace App\Modules\Addons\Payments\Models;

use App\Models\BaseModel;
use App\Models\Payment;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Comprobante adjunto a un pago. Múltiples por payment (XML CFDI + PDF
 * imprimible + payload del webhook + captura, según el caso).
 */
class PaymentReceipt extends BaseModel
{
    use SoftDeletes;

    protected $table = 'payment_receipts';

    protected $fillable = [
        'payment_id',
        'type',
        'file_path',
        'original_name',
        'size',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size'     => 'integer',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
