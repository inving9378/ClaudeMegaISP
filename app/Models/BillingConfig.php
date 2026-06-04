<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingConfig extends Model
{
    protected $table = 'billing_config';

    protected $fillable = [
        'notification_window_hours',
        'send_prefactura_email',
        'send_ticket_email',
        'envio_pausado',
    ];

    protected $casts = [
        'notification_window_hours' => 'integer',
        'send_prefactura_email'     => 'boolean',
        'send_ticket_email'         => 'boolean',
        'envio_pausado'             => 'boolean',
    ];

    /**
     * Devuelve la instancia única de configuración (crea si no existe).
     */
    public static function current(): self
    {
        return self::firstOrCreate([], [
            'notification_window_hours' => 12,
            'send_prefactura_email'     => true,
            'send_ticket_email'         => false,
        ]);
    }

    /**
     * Ventana validada: nunca menor a 12 h.
     */
    public function windowHours(): int
    {
        return max((int) $this->notification_window_hours, BillingNotification::MIN_WINDOW_HOURS);
    }
}
