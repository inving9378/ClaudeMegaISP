<?php

namespace App\Modules\Addons\Payments\Models;

use App\Models\BaseModel;

/**
 * FASE 3 — Sesión (máquina de estados) de identificación del cliente dueño de
 * un comprobante recibido por WhatsApp.
 *
 * SOLO identifica + marca quién es y con qué certeza. NO aplica pago (F4).
 * Ver el contrato para F4 en las constantes CERTAINTY_*.
 */
class WhatsappIdentificationSession extends BaseModel
{
    protected $table = 'whatsapp_identification_sessions';

    // Estados de la máquina.
    public const STATE_DETECTING        = 'detecting';
    public const STATE_AWAITING_NAME    = 'awaiting_name';
    public const STATE_AWAITING_SERVICE = 'awaiting_service';
    public const STATE_RESOLVED         = 'resolved';
    public const STATE_ESCALATED        = 'escalated';

    // Cómo se identificó.
    public const METHOD_MEG                = 'meg';
    public const METHOD_NAME_SINGLE        = 'name_single';
    public const METHOD_NAME_DISAMBIGUATED = 'name_disambiguated';

    // Certeza (contrato para F4).
    public const CERTAINTY_EXACT    = 'exact';     // MEG → F4 puede auto-aplicar.
    public const CERTAINTY_PROPOSED = 'proposed';  // nombre → requiere confirmación humana.

    // Tope de reintentos antes de escalar (decisión de Irving: 2).
    public const MAX_ATTEMPTS = 2;

    protected $fillable = [
        'is_simulation',
        'extraction_id',
        'conversation_id',
        'message_id',
        'state',
        'method',
        'certainty',
        'resolved_client_id',
        'candidate_client_ids',
        'attempts',
        'expires_at',
        'reminder_sent_at',
        'escalated_to',
        'escalation_reason',
        'created_by',
    ];

    protected $casts = [
        'is_simulation'        => 'boolean',
        'candidate_client_ids' => 'array',
        'attempts'             => 'integer',
        'expires_at'           => 'datetime',
        'reminder_sent_at'     => 'datetime',
    ];

    public function extraction()
    {
        return $this->belongsTo(WhatsappPaymentExtraction::class, 'extraction_id');
    }

    public function isResolved(): bool
    {
        return $this->state === self::STATE_RESOLVED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
