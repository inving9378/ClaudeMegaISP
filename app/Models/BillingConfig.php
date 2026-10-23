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
        'envio_pausado_cambiado_por',
        'envio_pausado_cambiado_at',
        'envio_lote_por_hora',
        'envio_lote_hasta',
        'reactivacion_nivel',
        'reactivacion_limite_corrida',
        'reactivacion_limite_diario',
        'reactivacion_cambiada_por',
        'reactivacion_cambiada_at',
        'proforma_anticipada',
        'proforma_anticipada_cambiada_por',
        'proforma_anticipada_cambiada_at',
        'envio_prefactura_dias_cambiado_por',
        'envio_prefactura_dias_cambiado_at',
    ];

    protected $casts = [
        'notification_window_hours' => 'integer',
        'send_prefactura_email'     => 'boolean',
        'send_ticket_email'         => 'boolean',
        'envio_pausado'             => 'boolean',
        'envio_pausado_cambiado_at' => 'datetime',
        'envio_lote_por_hora'       => 'integer',
        'envio_lote_hasta'          => 'datetime',
        'reactivacion_limite_corrida' => 'integer',
        'reactivacion_limite_diario'  => 'integer',
        'reactivacion_cambiada_at'  => 'datetime',
        'proforma_anticipada'       => 'boolean',
        'proforma_anticipada_cambiada_at' => 'datetime',
        'envio_prefactura_dias_cambiado_at' => 'datetime',
    ];

    /** Columna vigilada → [cuándo, quién] (#581 suma la proforma anticipada y sus días). */
    private const CAMBIOS = [
        'envio_pausado' => ['envio_pausado_cambiado_at', 'envio_pausado_cambiado_por'],
        'proforma_anticipada' => ['proforma_anticipada_cambiada_at', 'proforma_anticipada_cambiada_por'],
        'envio_prefactura_dias' => ['envio_prefactura_dias_cambiado_at', 'envio_prefactura_dias_cambiado_por'],
    ];

    /**
     * Quién y cuándo cambió la pausa general, la proforma anticipada o sus días, aunque el cambio
     * no venga de la pantalla «Envíos automáticos» (que los fija ella misma). Desde consola queda
     * sin usuario.
     */
    protected static function booted(): void
    {
        static::saving(function (self $config) {
            foreach (self::CAMBIOS as $columna => [$at, $por]) {
                if ($config->isDirty($columna) && ! $config->isDirty($at)
                    && \Illuminate\Support\Facades\Schema::hasColumn($config->getTable(), $at)) {
                    $config->{$at} = now();
                    $config->{$por} = auth()->id();
                }
            }
        });
    }

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
