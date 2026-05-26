<?php

namespace App\Modules\Addons\Payments\Models;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora de webhooks entrantes — NO extiende BaseModel a propósito
 * (no necesita created_by/updated_by; los webhooks vienen de un servicio
 * externo sin usuario autenticado).
 *
 * Status:
 *   pending          — recién recibido, aún procesando
 *   processed        — payment aplicado correctamente
 *   failed           — error al aplicar; ver error_message
 *   duplicate        — external_id ya procesado previamente (idempotencia)
 *   signature_invalid — firma HMAC no coincide; posible intento de fraude
 */
class PaymentWebhookLog extends Model
{
    protected $table = 'payment_webhooks_log';

    protected $fillable = [
        'provider',
        'event_type',
        'external_id',
        'payload',
        'status',
        'payment_id',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
