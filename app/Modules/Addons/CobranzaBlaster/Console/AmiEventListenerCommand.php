<?php

namespace App\Modules\Addons\CobranzaBlaster\Console;

use App\Modules\Addons\CobranzaBlaster\Jobs\ProcessCallResultJob;
use App\Modules\Addons\CobranzaBlaster\Services\AmiConnectionService;
use Illuminate\Console\Command;
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
 * Correr bajo supervisor:  php artisan cobranza:ami-listener
 */
class AmiEventListenerCommand extends Command
{
    protected $signature = 'cobranza:ami-listener
        {--max-seconds=0 : Detenerse tras N segundos (0 = infinito; útil para debug)}';

    protected $description = 'Escucha eventos del AMI de Asterisk y actualiza el estado de las llamadas de cobranza';

    /** Eventos AMI crudos que nos interesan. El resto se ignora. */
    private const EVENTOS_RELEVANTES = [
        'Newstate', 'Hangup', 'DTMFEnd', 'OriginateResponse',
    ];

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

        while (!$debeParar) {
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

                if ($maxSeconds > 0 && (time() - $inicio) >= $maxSeconds) {
                    $debeParar = true;
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
    private function procesarEvento(array $evento): void
    {
        $tipo = $evento['Event'] ?? null;

        if (!$tipo || !in_array($tipo, self::EVENTOS_RELEVANTES, true)) {
            return;
        }

        $uniqueid = $evento['Uniqueid'] ?? null;

        // Solo nos importan los canales que nosotros originamos (ChannelId 'cob-*').
        if (!$uniqueid || !str_starts_with($uniqueid, 'cob-')) {
            return;
        }

        $abstracto = $this->mapearEvento($tipo, $evento);

        if ($abstracto === null) {
            return;
        }

        ProcessCallResultJob::dispatch($abstracto, $uniqueid, $evento);
    }

    /**
     * Mapea (evento AMI, payload) → evento abstracto de ProcessCallResultJob,
     * o null si no debe generar transición de estado.
     */
    private function mapearEvento(string $tipo, array $evento): ?string
    {
        return match ($tipo) {
            // Canal contestado: ChannelStateDesc = 'Up'.
            'Newstate' => ($evento['ChannelStateDesc'] ?? '') === 'Up' ? 'ANSWER' : null,

            // El cliente marcó un dígito; "1" = quiere hablar con asesor.
            'DTMFEnd' => ($evento['Digit'] ?? '') === '1' ? 'DTMF' : null,

            // Originate asíncrono que falló de entrada (sin canal).
            'OriginateResponse' => ($evento['Response'] ?? '') === 'Failure' ? 'FAILED' : null,

            // Fin de llamada: el código de causa Q.850 define el desenlace.
            'Hangup' => $this->mapearHangup((int) ($evento['Cause'] ?? 0)),

            default => null,
        };
    }

    /**
     * Códigos de causa Q.850 (campo Cause del evento Hangup).
     *  16 = Normal Clearing  17 = User Busy  18/19 = No answer  21 = Rejected
     *  Resto = fallo de red/troncal.
     */
    private function mapearHangup(int $cause): string
    {
        return match ($cause) {
            17       => 'BUSY',
            18, 19   => 'NOANSWER',
            16       => 'HANGUP',
            default  => 'FAILED',
        };
    }
}
