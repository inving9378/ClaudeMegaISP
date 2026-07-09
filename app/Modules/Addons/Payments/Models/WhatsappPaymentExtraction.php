<?php

namespace App\Modules\Addons\Payments\Models;

use App\Models\BaseModel;
use App\Models\Marketing\Message;

/**
 * FASE 2 — Resultado de la extracción por IA de un comprobante recibido por
 * WhatsApp (imagen o PDF), ligado al mensaje/conversación de Marketing.
 *
 * SOLO datos leídos + confianza. NO representa un pago aplicado ni una
 * identificación de cliente (eso es F3-F4). F3 consumirá estas filas.
 */
class WhatsappPaymentExtraction extends BaseModel
{
    protected $table = 'whatsapp_payment_extractions';

    protected $fillable = [
        'source',
        'source_message_id',
        'source_conversation_id',
        'message_id',
        'conversation_id',
        'document_type',
        'source_mime',
        'concepto',
        'fecha_pago',
        'ok',
        'fields',
        'unreadable',
        'error',
        'model',
        'raw',
        'extracted_by',
        'extracted_at',
        'discarded_at',
        'discard_reason',
    ];

    protected $casts = [
        'ok'           => 'boolean',
        'fields'       => 'array',
        'unreadable'   => 'array',
        'extracted_at' => 'datetime',
        'discarded_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
