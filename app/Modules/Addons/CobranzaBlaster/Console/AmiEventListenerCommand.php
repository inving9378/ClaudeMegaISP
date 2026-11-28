<?php

namespace App\Modules\Addons\CobranzaBlaster\Console;

use App\Modules\Addons\CobranzaBlaster\Jobs\ProcessCallResultJob;
use App\Modules\Addons\CobranzaBlaster\Services\AmiConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Daemon persistente que escucha el stream de eventos del AMI de Asterisk y los
 * traduce a los eventos abstractos que entiende ProcessCallResultJob.
 *
 * Es el reemplazo del "webhook AMI": Asterisk no hace HTTP POST por sí solo, así
 * que mantenemos un socket abierto, leemos los eventos y despachamos el job.
 * Correlación 100% por Uniqueid, que nosotros fijamos vía ChannelId al originar
 * (ver AmiConnectionService::originate).
 *
 * Lo deja corriendo «Instalar dependencias» de VoIP: servicio systemd megaisp-campanas-ami
 * (paso 55, 25-nov), que lo levanta cada hora para tomar el código de cada versión.
 */
class AmiEventListenerCommand extends Command
{
    protected $signature = 'cobranza:ami-listener
        {--max-seconds=0 : Detenerse tras N segundos (0 = infinito; útil para debug)}';

    protected $description = 'Escucha eventos del AMI de Asterisk y actualiza el estado de las llamadas de cobranza';

    public function handle(AmiConnectionService $ami): int
    {
        $inicio     = time();
        $maxSeconds = (int) $this->option('max-seconds');

        $this->info('[cobranza:ami-listener] iniciando…');

        // Atender SIGTERM/SIGINT para apagado limpio (supervisor envía SIGTERM).
        $debeParar = false;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () use (&$debeParar) { $debeParar = true; });
            pcntl_signal(SIGINT, function () use (&$debeParar) { $debeParar = true; });
        }

        $seAcabo = fn () => $maxSeconds > 0 && (time() - $inicio) >= $maxSeconds;

        // Tras una actualización (el deploy hace queue:restart) sale solo y systemd lo levanta con
        // el código nuevo (27-nov). Antes seguía con el código viejo hasta un reinicio a mano.
        $marca = $this->marcaReinicio();
        $ultimaRevision = time();

        while (!$debeParar && !$seAcabo()) {
            if (!$ami->isConnected() && !$ami->connect()) {
                Log::error('[cobranza:ami-listener] no se pudo conectar al AMI; reintento en 5s');
                $this->error('AMI no disponible, reintentando en 5s…');
                sleep(5);
                continue;
            }

            $this->info('[cobranza:ami-listener] conectado al AMI, escuchando eventos…');

            while (!$debeParar && $ami->isConnected()) {
                $evento = $ami->readEvent(2);

                if ($evento !== null) {
                    $this->procesarEvento($evento);
                }

                if ($seAcabo()) {
                    $debeParar = true;
                }

                if (time() - $ultimaRevision >= 10) {
                    $ultimaRevision = time();
                    if ($this->marcaReinicio() !== $marca) {
                        Log::info('[cobranza:ami-listener] llegó una versión nueva; se reinicia con el código nuevo.');
                        $debeParar = true;
                    }
                }
            }

            if (!$debeParar) {
                Log::warning('[cobranza:ami-listener] conexión AMI perdida; reconectando…');
                $ami->disconnect();
                sleep(2);
            }
        }

        $ami->disconnect();
        $this->info('[cobranza:ami-listener] detenido.');

        return self::SUCCESS;
    }

    /**
     * Traduce un evento AMI crudo al evento abstracto del job y lo despacha.
     */
    /** Lo que el deploy cambia con queue:restart (el mismo aviso que reinicia los workers). */
    private function marcaReinicio(): ?string
    {
        try {
            $valor = Cache::get('illuminate:queue:restart');
        } catch (\Throwable) {
            return null;
        }

        return $valor === null ? null : (string) $valor;
    }

    private function procesarEvento(array $evento): void
    {
        $uniqueid = $evento['Uniqueid'] ?? null;

        // Solo nos importan los canales que nosotros originamos (ChannelId 'cob-*').
        if (!$uniqueid || !str_starts_with($uniqueid, 'cob-')) {
            return;
        }

        $abstracto = self::mapearEvento($evento);

        if ($abstracto === null) {
            return;
        }

        ProcessCallResultJob::dispatch($abstracto, $uniqueid, $evento);
    }

    /**
     * Evento AMI → evento abstracto de ProcessCallResultJob, o null si no interesa.
     *
     * El desenlace de una llamada que no se contestó lo da OriginateResponse, que Asterisk
     * manda siempre al terminar un Originate asíncrono, con su Reason (25-nov). Antes se
     * leía también la causa del Hangup y las dos cosas se pisaban: ocupado y rechazada
     * terminaban como «fallida» sin reintento, y «sin contestar» dependía de cuál llegaba
     * primero. El Hangup ahora solo queda en la bitácora de la llamada.
     *
     * Reason (Asterisk): 0 no se pudo crear el canal, 1 colgaron antes de contestar,
     * 3 timbró y nadie contestó, 4 contestó, 5 ocupado, 8 congestión.
     */
    public static function mapearEvento(array $evento): ?string
    {
        return match ($evento['Event'] ?? null) {
            // Canal contestado: ChannelStateDesc = 'Up'.
            'Newstate' => ($evento['ChannelStateDesc'] ?? '') === 'Up' ? 'ANSWER' : null,

            // El cliente marcó un dígito; "1" = quiere hablar con asesor.
            'DTMFEnd' => ($evento['Digit'] ?? '') === '1' ? 'DTMF' : null,

            // Success = contestó (Reason 4). Es la segunda señal de «contestada», además del
            // Newstate Up: si una se pierde (el listener reiniciándose), basta la otra.
            'OriginateResponse' => ($evento['Response'] ?? '') === 'Success'
                ? 'ANSWER'
                : match ((int) ($evento['Reason'] ?? 0)) {
                    5       => 'BUSY',
                    1, 3    => 'NOANSWER',
                    default => 'FAILED',
                },

            'Hangup' => 'HANGUP',

            default => null,
        };
    }
}
