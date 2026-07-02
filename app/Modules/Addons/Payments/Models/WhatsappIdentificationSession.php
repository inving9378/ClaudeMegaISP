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

    // Estados de la máquina (flujo escalonado: llave más fuerte → más débil).
    public const STATE_DETECTING         = 'detecting';
    public const STATE_AWAITING_CLIENT_ID = 'awaiting_client_id';
    public const STATE_AWAITING_NAME      = 'awaiting_name';
    public const STATE_AWAITING_STREET    = 'awaiting_street';
    public const STATE_RESOLVED           = 'resolved';
    public const STATE_ESCALATED          = 'escalated';

    // Cómo se identificó.
    public const METHOD_MEG                = 'meg';
    public const METHOD_CLIENT_ID          = 'client_id';
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
        'resolved_multiple_services',
        'candidate_client_ids',
        'attempts',
        'expires_at',
        'reminder_sent_at',
        'reminders_sent',
        'escalated_to',
        'escalation_reason',
        'created_by',
    ];

    protected $casts = [
        'is_simulation'              => 'boolean',
        'resolved_multiple_services' => 'boolean',
        'candidate_client_ids'       => 'array',
        'attempts'             => 'integer',
        'reminders_sent'       => 'integer',
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
