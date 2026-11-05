<?php

namespace App\Console\Commands\Active;

use App\Models\PingStatistic;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * #990 — Detecta dedicados con pérdida de paquetes > umbral sostenida.
 * Regla (decisión del ejecutor, reversible): con muestreo de 5 min, «5 minutos sostenidos» = las
 * últimas N muestras (default 2) dentro de la ventana, todas por encima del umbral.
 * Canal: solo log (canal diario); el canal definitivo (bandeja/WhatsApp/correo) lo define Irving.
 * Apagado por defecto: ping_statistics hoy puede medir al router y no al equipo (#999).
 */
class AlertaPerdidaPaquetesDedicados extends Command
{
    protected $signature = 'clientes:alerta-perdida-paquetes {--umbral=20} {--muestras=2} {--ventana=15} {--force}';
    protected $description = 'Avisa (log) cuando un dedicado tiene pérdida de paquetes sostenida sobre el umbral';

    public function handle(): int
    {
        if (! $this->option('force') && ! config('monitoreo.alerta_perdida_paquetes')) {
            $this->info('Apagado (ALERTA_PERDIDA_PAQUETES_ENABLED=false).');
            return self::SUCCESS;
        }

        $umbral = (int) $this->option('umbral');
        $muestras = max(1, (int) $this->option('muestras'));
        $desde = now()->subMinutes((int) $this->option('ventana'));
        $alertas = 0;

        foreach (Client::isDedicated()->pluck('id') as $clientId) {
            $ult = PingStatistic::where('client_id', $clientId)
                ->where('recorded_at', '>=', $desde)
                ->orderByDesc('recorded_at')->limit($muestras)->get();

            if ($ult->count() < $muestras || $ult->contains(fn ($p) => $p->packet_loss <= $umbral)) {
                continue;
            }
            // Una sola alerta por episodio (se re-arma a los 60 min).
            if (! Cache::add("alerta_perdida_paquetes:{$clientId}", 1, now()->addMinutes(60))) {
                continue;
            }
            $alertas++;
            Log::warning("Pérdida de paquetes sostenida en dedicado #{$clientId}", [
                'umbral' => $umbral, 'perdida' => $ult->pluck('packet_loss')->all(), 'ip' => $ult->first()->ip_address,
            ]);
            $this->warn("Cliente {$clientId}: pérdida " . $ult->pluck('packet_loss')->implode('%, ') . '%');
        }

        $this->info("Alertas emitidas: {$alertas}");
        return self::SUCCESS;
    }
}
