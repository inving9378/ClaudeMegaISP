<?php

namespace App\Modules\Addons\Flotas\Models;

use Illuminate\Database\Eloquent\Model;

/** Contador mensual de WhatsApp por cliente (tope en config/flotas.php). */
class FleetClientWhatsappUsage extends Model
{
    protected $table = 'fleet_client_whatsapp_usage';

    protected $guarded = [];

    protected $casts = ['aviso_tope_at' => 'datetime'];

    public static function tope(): int
    {
        return (int) config('flotas.alertas_cliente.whatsapp_tope_mensual', 100);
    }

    public static function delMes(int $clientId, ?string $periodo = null): self
    {
        return static::firstOrCreate(
            ['client_id' => $clientId, 'periodo' => $periodo ?? now()->format('Y-m')],
            ['enviados' => 0]
        );
    }

    public function topeAlcanzado(): bool
    {
        return $this->enviados >= static::tope();
    }
}
