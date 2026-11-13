<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Addons\VoIP\Models\IaBotConfig;
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
                            {--texto= : O, más simple, el texto directo (sin pasar por STT)}
                            {--revisar : Solo revisa que María esté lista (claves de IA, servicio, configuración), sin llamar a OpenAI}';

    protected $description = 'Prueba el motor de María (STT→LLM→TTS) con un WAV o texto de prueba, sin tocar Asterisk';

    public function handle(
        VoiceSttService $stt,
        ConversacionBotService $cerebro,
        VoiceTtsService $tts
    ): int {
        if ($this->option('revisar')) {
            return $this->revisar();
        }

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

    /**
     * ¿Tiene María todo para contestar? Lo usa el instalador del add-on (paso 60).
     * No llama a OpenAI ni imprime claves: solo si cada función de IA tiene una
     * clave legible, si el servicio escucha y cómo está configurada.
     * Sale con error si falta algo que le impida atender.
     */
    private function revisar(): int
    {
        $faltas = 0;
        $funciones = [
            'voip.bot_voz'       => 'Conversación (lo que piensa)',
            'voip.voz'           => 'Voz (lo que dice)',
            'voip.transcripcion' => 'Oído (lo que entiende)',
        ];

        foreach ($funciones as $clave => $que) {
            try {
                $c = IA::credencialesPara($clave);
            } catch (IANoConfigurada $e) {
                $this->warn("✘ {$que}: {$e->getMessage()}");
                $faltas++;
                continue;
            }

            if (trim((string) $c['llave']) !== '') {
                $this->line("✔ {$que}: {$c['proveedor']} · {$c['modelo']}");
                continue;
            }

            $faltas++;
            $this->warn($c['integracion']->encrypted_value
                ? "✘ {$que}: la clave de «{$c['proveedor']}» no se puede leer (se guardó con otra APP_KEY). Pégala de nuevo en Integraciones."
                : "✘ {$que}: la integración «{$c['proveedor']}» no tiene clave. Pégala en Integraciones.");
        }

        $host = (string) config('voip.bot_voz.host', '127.0.0.1');
        $port = (int) config('voip.bot_voz.port', 9099);
        $sock = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($sock) {
            fclose($sock);
            $this->line("✔ Servicio: escucha en {$host}:{$port}");
        } else {
            $this->warn("✘ Servicio: nadie escucha en {$host}:{$port} (systemctl status megaisp-bot-voz)");
            $faltas++;
        }

        $cfg = IaBotConfig::current();
        $horario = substr((string) $cfg->horario_inicio, 0, 5) . '–' . substr((string) $cfg->horario_fin, 0, 5);
        if ($cfg->enabled && $cfg->piloto_porcentaje > 0) {
            $this->line("✔ Atiende el {$cfg->piloto_porcentaje} % de las llamadas, de {$horario}");
        } else {
            $this->line('· Apagada' . ($cfg->enabled ? ' (piloto en 0 %)' : '')
                . ': las llamadas van directo a las personas. Para encenderla: VoIP → Asistente IA.');
        }

        return $faltas ? self::FAILURE : self::SUCCESS;
    }
}
