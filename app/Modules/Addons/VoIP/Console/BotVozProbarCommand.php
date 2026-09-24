<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Modules\Addons\VoIP\Models\IaBotConversation;
use App\Modules\Addons\VoIP\Services\Voz\ConversacionBotService;
use App\Modules\Addons\VoIP\Services\Voz\VoiceSttService;
use App\Modules\Addons\VoIP\Services\Voz\VoiceTtsService;
use Illuminate\Console\Command;

/**
 * MegaVoz Fase 6 — prueba el motor de "María" COMPLETO (STT → LLM → TTS) sin
 * tocar Asterisk ni AudioSocket. Se corre esto primero: aísla "¿el cerebro
 * responde bien?" de "¿el audio viaja bien por el socket?" — si algo suena
 * mal aquí, el problema NO es de framing/protocolo.
 *
 * La fila de IaBotConversation que crea queda marcada is_test en el propio
 * call_id (prefijo "test-") para poder limpiarla o identificarla luego.
 */
class BotVozProbarCommand extends Command
{
    protected $signature = 'voip:bot-voz-probar
                            {--wav= : Ruta a un .wav con la pregunta del cliente}
                            {--texto= : O, más simple, el texto directo (sin pasar por STT)}';

    protected $description = 'Prueba el motor de María (STT→LLM→TTS) con un WAV o texto de prueba, sin tocar Asterisk';

    public function handle(
        VoiceSttService $stt,
        ConversacionBotService $cerebro,
        VoiceTtsService $tts
    ): int {
        $wav   = $this->option('wav');
        $texto = $this->option('texto');

        if (! $wav && ! $texto) {
            $this->error('Pasa --wav=ruta.wav o --texto="lo que dice el cliente"');
            return self::FAILURE;
        }

        if ($wav) {
            if (! is_file($wav)) {
                $this->error("No existe: {$wav}");
                return self::FAILURE;
            }
            $this->line("[1/3] Transcribiendo {$wav}…");
            $r = $stt->transcribir($wav);
            if (! ($r['success'] ?? false)) {
                $this->error('STT falló: ' . ($r['error'] ?? '?'));
                return self::FAILURE;
            }
            $texto = $r['texto'];
            $this->info("Transcrito (\${$r['costo_usd']}): \"{$texto}\"");
        } else {
            $this->line('[1/3] STT omitido — usando --texto directo.');
        }

        if (trim((string) $texto) === '') {
            $this->warn('Transcripción vacía — no hay nada que responder.');
            return self::SUCCESS;
        }

        $conversacion = IaBotConversation::create([
            'call_id'            => 'test-' . uniqid(),
            'conversation_type'  => 'customer_support',
            'started_at'         => now(),
            'status'             => 'completed',
        ]);

        $this->line('[2/3] Pensando la respuesta…');
        $resultado = $cerebro->turno($conversacion, $texto);

        $this->info("Respuesta: \"{$resultado['texto_hablado']}\"");
        $this->line('  transferir=' . ($resultado['transferir'] ? 'SI' : 'no') . "  costo_llm=\${$resultado['costo_usd']}");

        $this->line('[3/3] Sintetizando voz…');
        $audio = $tts->sintetizar($resultado['texto_hablado']);
        if (! ($audio['success'] ?? false)) {
            $this->error('TTS falló: ' . ($audio['error'] ?? '?'));
            return self::FAILURE;
        }

        $conversacion->ended_at = now();
        $conversacion->duration = 0;
        $conversacion->save();

        $this->info("Audio PCM crudo en: {$audio['pcm_path']}");
        $this->line('Para escucharlo: ffplay -f s16le -ar 8000 -ac 1 "' . $audio['pcm_path'] . '"');
        $this->line("IaBotConversation #{$conversacion->id} (call_id={$conversacion->call_id})");

        return self::SUCCESS;
    }
}
