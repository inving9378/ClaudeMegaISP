<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class FleetClientAlertContact extends BaseModel
{
    use SoftDeletes;

    protected $table = 'fleet_client_alert_contacts';

    protected $fillable = [
        'client_id', 'nombre', 'email', 'telefono', 'es_principal', 'channels',
        'tipos_alerta', 'silencio_desde', 'silencio_hasta', 'active',
    ];

    protected $casts = [
        'channels'     => 'array',
        'tipos_alerta' => 'array',
        'es_principal' => 'boolean',
        'active'       => 'boolean',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public function scopeForClient(Builder $q, int $clientId): Builder
    {
        return $q->where('client_id', $clientId);
    }

    /** Máximo de contactos por cliente (config/flotas.php → alertas_cliente.max_contactos). */
    public static function maxPorCliente(): int
    {
        return (int) config('flotas.alertas_cliente.max_contactos', 5);
    }

    public function recibeTipo(string $tipo): bool
    {
        return empty($this->tipos_alerta) || in_array($tipo, $this->tipos_alerta, true);
    }

    /** ¿$hora (HH:MM[:SS]) cae en su horario silencioso? Soporta cruce de medianoche. */
    public function enSilencio(string $hora): bool
    {
        if (! $this->silencio_desde || ! $this->silencio_hasta) {
            return false;
        }
        $h = substr($hora, 0, 5);
        $d = substr($this->silencio_desde, 0, 5);
        $a = substr($this->silencio_hasta, 0, 5);

        return $d <= $a ? ($h >= $d && $h < $a) : ($h >= $d || $h < $a);
    }
}
