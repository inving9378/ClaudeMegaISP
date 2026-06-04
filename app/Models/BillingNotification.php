<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class BillingNotification extends Model
{
    protected $fillable = [
        'client_id',
        'document_type',
        'invoice_id',
        'payment_id',
        'pdf_path',
        'email',
        'subject',
        'status',
        'window_hours',
        'notificar_despues_de',
        'enviado_at',
        'regenerated_at',
        'error_log',
        'send_by_email',
    ];

    protected $casts = [
        'notificar_despues_de' => 'datetime',
        'enviado_at'           => 'datetime',
        'regenerated_at'       => 'datetime',
        'send_by_email'        => 'boolean',
        'window_hours'         => 'integer',
    ];

    const STATUS_WAITING   = 'en_espera';
    const STATUS_SENT      = 'enviado';
    const STATUS_CANCELLED = 'cancelado';
    const STATUS_ERROR     = 'error';

    const TYPE_PREFACTURA = 'prefactura';
    const TYPE_TICKET     = 'ticket';
    const TYPE_RECIBO     = 'recibo';

    const MIN_WINDOW_HOURS = 12;

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function client()
    {
        return $this->belongsTo(\App\Modules\Core\Clientes\Models\Client::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePendingToSend(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_WAITING)
            ->where('send_by_email', true)
            ->where('notificar_despues_de', '<=', now());
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_WAITING);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function markSent(): void
    {
        $this->update(['status' => self::STATUS_SENT, 'enviado_at' => now()]);
    }

    public function markError(string $message): void
    {
        $this->update(['status' => self::STATUS_ERROR, 'error_log' => $message]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Reinicia la ventana de retención (se usa cuando el documento se regenera).
     * Siempre respeta el piso de MIN_WINDOW_HOURS.
     */
    public function resetWindow(?string $newPdfPath = null): void
    {
        $hours = max($this->window_hours, self::MIN_WINDOW_HOURS);

        $this->update([
            'status'               => self::STATUS_WAITING,
            'notificar_despues_de' => Carbon::now()->addHours($hours),
            'regenerated_at'       => now(),
            'enviado_at'           => null,
            'error_log'            => null,
            'pdf_path'             => $newPdfPath ?? $this->pdf_path,
        ]);
    }

    /**
     * Calcula y aplica la ventana inicial, respetando el piso de 12 h.
     */
    public static function makeWindow(int $configuredHours): int
    {
        return max($configuredHours, self::MIN_WINDOW_HOURS);
    }
}
