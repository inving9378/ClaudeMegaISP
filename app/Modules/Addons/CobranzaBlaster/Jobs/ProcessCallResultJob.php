<?php

namespace App\Modules\Addons\CobranzaBlaster\Jobs;

use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamadaEvento;
use App\Modules\Core\Clientes\Services\SuspendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCallResultJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $evento,
        public string $uniqueid,
        public array  $payload
    ) {
        $this->onQueue('cobranza');
    }

    public function handle(SuspendService $suspendService): void
    {
        $llamada = CobranzaLlamada::where('ami_uniqueid', $this->uniqueid)->first();

        if (!$llamada) {
            return;
        }

        $campana = $llamada->campana;

        match ($this->evento) {
            'ANSWER' => $llamada->update(['estado' => 'contestada']),

            'BUSY', 'NOANSWER' => $this->handleReintento($llamada, $campana),

            'FAILED' => $this->handleFallida($llamada, $campana, $suspendService),

            'HANGUP' => $this->handleHangup($llamada),

            'DTMF', 'TRANSFER' => null, // Asterisk maneja la transferencia

            default => Log::info("ProcessCallResultJob: evento desconocido [{$this->evento}]", [
                'uniqueid' => $this->uniqueid,
            ]),
        };

        CobranzaLlamadaEvento::create([
            'llamada_id'  => $llamada->id,
            'evento'      => $this->evento,
            'payload'     => $this->payload,
            'ocurrido_at' => now(),
        ]);
    }

    private function handleReintento(CobranzaLlamada $llamada, CobranzaCampana $campana): void
    {
        $puedeReintentar = $llamada->intentos < $campana->max_intentos;

        $llamada->update([
            'estado'             => $puedeReintentar ? 'pendiente' : 'fallida',
            'proximo_intento_at' => $puedeReintentar
                ? now()->addMinutes($campana->minutos_entre_intentos)
                : null,
        ]);
    }

    private function handleFallida(CobranzaLlamada $llamada, CobranzaCampana $campana, SuspendService $suspendService): void
    {
        $llamada->update(['estado' => 'fallida']);

        if ($llamada->intentos >= $campana->max_intentos && $llamada->client) {
            // Delegar suspensión al SuspendService existente con 24h de gracia
            dispatch(function () use ($llamada, $suspendService) {
                $suspendService->suspendServiceByClient($llamada->client);
            })->delay(now()->addHours(24))->onQueue('cobranza');

            Log::info("Cobranza: cliente #{$llamada->client_id} programado para suspensión en 24h tras {$llamada->intentos} intentos fallidos.");
        }
    }

    private function handleHangup(CobranzaLlamada $llamada): void
    {
        if ($llamada->estado === 'marcando') {
            $llamada->update(['estado' => 'no_contesto']);
        }
    }
}
