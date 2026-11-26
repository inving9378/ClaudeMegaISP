<?php

namespace App\Modules\Addons\CobranzaBlaster\Jobs;

use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamadaEvento;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Lo que dice Asterisk de una llamada de campaña (vía cobranza:ami-listener) → su estado.
 *
 * 25-nov (pedido de Irving): una llamada que no se contesta ya NO programa la suspensión
 * del servicio del cliente. Antes, en el último intento, «sin contestar», «ocupado» o
 * «falló» mandaban suspender a las 24 h aunque el cliente hubiera pagado y aunque la
 * campaña fuera un aviso. El corte por adeudo sigue siendo el de siempre (facturación).
 */
class ProcessCallResultJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Estado final cuando ya no quedan intentos, según por qué no se completó. */
    private const FINAL = ['NOANSWER' => 'no_contesto', 'BUSY' => 'ocupado', 'FAILED' => 'fallida'];

    public function __construct(
        public string $evento,
        public string $uniqueid,
        public array  $payload
    ) {
        $this->onQueue('cobranza');
    }

    public function handle(): void
    {
        $llamada = CobranzaLlamada::where('ami_uniqueid', $this->uniqueid)->first();

        if (!$llamada) {
            return;
        }

        $campana = $llamada->campana;
        $cambio  = self::siguienteEstado(
            $this->evento,
            (string) $llamada->estado,
            (int) $llamada->intentos,
            (int) ($campana?->max_intentos ?? 1)
        );

        if ($cambio !== null) {
            $llamada->update([
                'estado'             => $cambio['estado'],
                'proximo_intento_at' => $cambio['reintentar']
                    ? now()->addMinutes((int) ($campana?->minutos_entre_intentos ?? 180))
                    : null,
            ]);
        }

        if (!in_array($this->evento, ['ANSWER', 'BUSY', 'NOANSWER', 'FAILED', 'HANGUP', 'DTMF'], true)) {
            Log::info("ProcessCallResultJob: evento desconocido [{$this->evento}]", ['uniqueid' => $this->uniqueid]);
        }

        CobranzaLlamadaEvento::create([
            'llamada_id'  => $llamada->id,
            'evento'      => $this->evento,
            'payload'     => $this->payload,
            'ocurrido_at' => now(),
        ]);
    }

    /**
     * El estado que sigue, o null si este evento no cambia nada. Pura: probada en tests/Unit.
     *
     * - Contestada gana: nada que llegue después la vuelve fallida.
     * - Sin contestar, ocupado o falló: se reintenta mientras queden intentos; si no, queda
     *   como no_contesto / ocupado / fallida.
     * - Eso solo cuenta mientras la llamada está «marcando»: un evento repetido o tardío
     *   no reabre una que ya terminó ni la que ya volvió a la fila.
     * - El Hangup y el «1» solo quedan en la bitácora de la llamada.
     *
     * @return array{estado: string, reintentar: bool}|null
     */
    public static function siguienteEstado(string $evento, string $estadoActual, int $intentos, int $maxIntentos): ?array
    {
        if ($evento === 'ANSWER') {
            return in_array($estadoActual, ['contestada', 'pagada', 'excluida'], true)
                ? null
                : ['estado' => 'contestada', 'reintentar' => false];
        }

        if (!isset(self::FINAL[$evento]) || $estadoActual !== 'marcando') {
            return null;
        }

        return $intentos < $maxIntentos
            ? ['estado' => 'pendiente', 'reintentar' => true]
            : ['estado' => self::FINAL[$evento], 'reintentar' => false];
    }
}
