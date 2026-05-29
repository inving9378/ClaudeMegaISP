<?php

namespace App\Modules\Addons\CobranzaBlaster\Jobs;

use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamadaEvento;
use App\Modules\Addons\CobranzaBlaster\Services\AmiConnectionService;
use App\Modules\Addons\CobranzaBlaster\Services\CobranzaTtsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BlastCampanaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 1;

    public function __construct(public int $campanaId)
    {
        $this->onQueue('cobranza');
    }

    public function handle(AmiConnectionService $ami, CobranzaTtsService $tts): void
    {
        $campana = CobranzaCampana::find($this->campanaId);

        if (!$campana || $campana->estado !== 'activa') {
            return;
        }

        $ahora = now()->format('H:i:s');
        if ($ahora < $campana->hora_inicio || $ahora > $campana->hora_fin) {
            return;
        }

        $llamadas = CobranzaLlamada::where('campana_id', $this->campanaId)
            ->where('estado', 'pendiente')
            ->where(fn ($q) => $q->whereNull('proximo_intento_at')
                ->orWhere('proximo_intento_at', '<=', now()))
            ->with('client.client_main_information')
            ->limit(50)
            ->get();

        if ($llamadas->isEmpty()) {
            return;
        }

        if (!$ami->connect()) {
            Log::error("BlastCampanaJob: no se pudo conectar al AMI para campaña #{$this->campanaId}");
            return;
        }

        foreach ($llamadas as $llamada) {
            $cmi = $llamada->client?->client_main_information;

            if (!$cmi) {
                $llamada->update(['estado' => 'excluida']);
                continue;
            }

            $audioPath = $tts->generateAudio(
                $llamada->id,
                $cmi->name,
                (float) $llamada->monto_vencido,
                $campana->fecha_fin?->format('d/m/Y') ?? 'la brevedad posible',
                (string) $llamada->client_id
            );

            $result = $ami->originate($llamada->telefono, $audioPath, $llamada->id, $cmi->name);

            $llamada->update([
                'estado'             => $result['success'] ? 'marcando' : 'fallida',
                'ultimo_intento_at'  => now(),
                'intentos'           => $llamada->intentos + 1,
                'ami_channel'        => $result['channel'] ?? null,
                'ami_uniqueid'       => $result['uniqueid'] ?? null,
                'proximo_intento_at' => now()->addMinutes($campana->minutos_entre_intentos),
            ]);

            CobranzaLlamadaEvento::create([
                'llamada_id'  => $llamada->id,
                'evento'      => 'Originate',
                'payload'     => $result,
                'ocurrido_at' => now(),
            ]);
        }

        $ami->disconnect();
    }
}
