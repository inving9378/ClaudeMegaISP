<?php

namespace App\Modules\Addons\CobranzaBlaster\Jobs;

use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamadaEvento;
use App\Modules\Addons\CobranzaBlaster\Services\AmiConnectionService;
use App\Modules\Addons\CobranzaBlaster\Services\CobranzaTtsService;
use App\Modules\Addons\VoIP\Models\Troncal;
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

        // MegaVoz Fase 7 — límite de canales simultáneos por campaña. Antes
        // "50" era solo el tamaño del LOTE que esta corrida del job procesa,
        // nunca un tope de cuántas de esas 50 pueden estar sonando/hablando
        // AL MISMO TIEMPO. Con max_canales_simultaneos configurado, se resta
        // lo que ya está en curso (estado 'marcando') y solo se completa el
        // lote hasta ese tope — sin configurar, comportamiento de siempre.
        $lote = 50;
        if ($campana->max_canales_simultaneos) {
            $enCurso = CobranzaLlamada::where('campana_id', $this->campanaId)
                ->where('estado', 'marcando')
                ->count();
            $lote = max(0, $campana->max_canales_simultaneos - $enCurso);
            if ($lote === 0) {
                return;
            }
        }

        $llamadas = CobranzaLlamada::where('campana_id', $this->campanaId)
            ->where('estado', 'pendiente')
            ->where(fn ($q) => $q->whereNull('proximo_intento_at')
                ->orWhere('proximo_intento_at', '<=', now()))
            ->with('client.client_main_information')
            ->limit($lote)
            ->get();

        if ($llamadas->isEmpty()) {
            return;
        }

        if (!$ami->connect()) {
            Log::error("BlastCampanaJob: no se pudo conectar al AMI para campaña #{$this->campanaId}");
            return;
        }

        // MegaVoz Fase 7 — troncal propia por campaña (voip_troncales, elegida
        // al crearla). Sin una elegida, originate() cae al default global de
        // siempre — comportamiento sin cambio para las campañas de cobranza
        // ya existentes.
        $endpointId = null;
        if ($campana->troncal_id) {
            $troncal = Troncal::find($campana->troncal_id);
            $endpointId = $troncal?->endpointId();
        }

        foreach ($llamadas as $llamada) {
            $cmi = $llamada->client?->client_main_information;

            if (!$cmi) {
                $llamada->update(['estado' => 'excluida']);
                continue;
            }

            // MegaVoz Fase 7 — cobranza sigue con SU mensaje (nombre+monto+
            // fecha, plantilla ya probada); aviso/anuncio/corte hablan el
            // texto libre que el admin escribió al crear la campaña
            // (audio_mensaje), cacheado por contenido (generateAudioCached
            // ya cachea por hash del texto — un solo audio para toda la
            // campaña, no uno por llamada).
            $audioPath = $campana->tipo === 'cobranza'
                ? $tts->generateAudio(
                    $llamada->id,
                    $cmi->name,
                    (float) $llamada->monto_vencido,
                    $campana->fecha_fin?->format('d/m/Y') ?? 'la brevedad posible',
                    (string) $llamada->client_id
                )
                : $tts->generateAudioCached('campana_' . $campana->id, (string) $campana->audio_mensaje);

            $result = $ami->originate($llamada->telefono, $audioPath, $llamada->id, $cmi->name, $endpointId);

            $nuevosIntentos = $llamada->intentos + 1;
            $puedeReintentar = !$result['success'] && $nuevosIntentos < $campana->max_intentos;

            $llamada->update([
                'estado'             => $result['success'] ? 'marcando' : ($puedeReintentar ? 'pendiente' : 'fallida'),
                'ultimo_intento_at'  => now(),
                'intentos'           => $nuevosIntentos,
                'ami_channel'        => $result['channel'] ?? null,
                'ami_uniqueid'       => $result['uniqueid'] ?? null,
                'proximo_intento_at' => ($result['success'] || $puedeReintentar)
                    ? now()->addMinutes($campana->minutos_entre_intentos)
                    : null,
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
