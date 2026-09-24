<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Modules\Addons\VoIP\Models\IaBotConfig;
use App\Modules\Addons\VoIP\Models\IaBotConversation;
use App\Modules\Addons\VoIP\Services\Voz\AudioSocketServer;
use App\Modules\Addons\VoIP\Services\Voz\ConversacionBotService;
use App\Modules\Addons\VoIP\Services\Voz\VoiceSttService;
use App\Modules\Addons\VoIP\Services\Voz\VoiceTtsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MegaVoz Fase 6 — daemon AudioSocket de "María". Mismo molde que
 * flotas:gps-listen (stream_socket_server nativo, framing por longitud, log
 * dedicado) — la diferencia real es que una central telefónica SÍ necesita
 * concurrencia desde el día uno (varias llamadas a la vez), por eso
 * pcntl_fork() por conexión entrante en vez del "una a la vez" del GPS.
 *
 * ⚠️ Esta pasada NO conecta este daemon al dialplan real (grupo-1). Se
 * prueba aislado — ver `voip:bot-voz-probar` primero, y un cliente
 * AudioSocket de prueba antes de tocar Asterisk siquiera. Conectarlo al
 * flujo real de clientes es una pasada aparte (con luz verde aparte).
 */
class BotVozEscucharCommand extends Command
{
    protected $signature = 'voip:bot-voz-escuchar {--host=} {--port=}';

    protected $description = 'Daemon AudioSocket de María (MegaVoz Fase 6) — NO conectado al dialplan real todavía';

    private const LOG_CHANNEL_PATH = 'logs/megavoz-bot-voz.log';

    public function handle(): int
    {
        // Sin --host/--port explícitos, misma fuente que ya lee el dialplan
        // generado (config/voip.php → bot_voz) — un solo lugar donde vive
        // esta dirección, no dos valores que puedan desincronizarse.
        $host = (string) ($this->option('host') ?: config('voip.bot_voz.host', '127.0.0.1'));
        $port = (int) ($this->option('port') ?: config('voip.bot_voz.port', 9099));

        $server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (! $server) {
            $this->error("No se pudo abrir tcp://{$host}:{$port} — {$errstr} ({$errno})");
            return self::FAILURE;
        }

        if (! extension_loaded('pcntl')) {
            $this->error('Falta la extensión pcntl — necesaria para atender llamadas simultáneas.');
            return self::FAILURE;
        }

        // No dejar zombies de los hijos que van terminando.
        pcntl_signal(SIGCHLD, SIG_IGN);

        $this->info("🎙️  Bot de voz escuchando en tcp://{$host}:{$port} (AISLADO, sin conectar a llamadas reales). Ctrl+C para detener.");
        $this->log("Daemon iniciado en {$host}:{$port}");

        while (true) {
            $conn = @stream_socket_accept($server, -1, $peer);
            if (! $conn) {
                continue;
            }

            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->log('pcntl_fork falló — atendiendo esta llamada en el proceso principal.', 'error');
                $this->atenderLlamada($conn, $peer);
                @fclose($conn);
                continue;
            }

            if ($pid === 0) {
                // Hijo: SOLO esta llamada.
                //
                // El SIG_IGN de SIGCHLD que puso el padre (para no acumular
                // zombies de llamadas terminadas) se HEREDA al hijo — y eso
                // rompe en silencio cada vez que el hijo necesita esperar a
                // UN PROCESO PROPIO (ffmpeg, vía Symfony Process en
                // VoiceTtsService): con SIGCHLD=SIG_IGN, el sistema reapea
                // solo, así que waitpid() ya no encuentra nada que esperar y
                // Process::run() reporta exitcode=-1/isSuccessful()=false
                // AUNQUE ffmpeg haya terminado bien y el archivo exista —
                // encontrado en vivo probando el daemon completo ("TTS falló:
                // No se pudo convertir el audio a PCM" con el .pcm realmente
                // creado en disco). Se restaura el comportamiento normal de
                // señales para este hijo — el padre conserva su SIG_IGN.
                pcntl_signal(SIGCHLD, SIG_DFL);

                // Reconectar DB — el file descriptor heredado del padre por
                // fork() no es seguro compartirlo entre procesos.
                DB::purge();
                @fclose($server);
                try {
                    $this->atenderLlamada($conn, $peer);
                } catch (\Throwable $e) {
                    $this->log("ERROR en llamada {$peer}: " . $e->getMessage(), 'error');
                } finally {
                    @fclose($conn);
                }
                exit(0);
            }

            // Padre: sigue aceptando conexiones nuevas, no necesita esta.
            @fclose($conn);
        }

        return self::SUCCESS; // inalcanzable salvo señal
    }

    private function atenderLlamada($conn, string $peer): void
    {
        stream_set_timeout($conn, 30);
        $socket = new AudioSocketServer($conn);

        $uuid = $socket->leerUuid();
        if (! $uuid) {
            $this->log("{$peer}: no llegó UUID — cerrando.", 'warning');
            return;
        }

        $config = IaBotConfig::current();

        // El interruptor de piloto (%) + horario de oficina se decide AQUÍ,
        // por llamada, en vivo — nunca en el dialplan (texto estático). Si
        // esta llamada NO le toca a María, se cierra el socket sin decir
        // nada — el dialplan generado ya está preparado para eso:
        // AudioSocket() regresa y sigue exactamente como si Fase 6 no
        // existiera. No se crea IaBotConversation para las que se declinan
        // — solo ensuciaría la lista de conversaciones con "llamadas" que
        // María nunca llegó a atender.
        if (! $config->debeAtenderAhora()) {
            $this->log("{$peer}: uuid={$uuid} — fuera del piloto/horario, se declina (dialplan sigue solo).");
            return;
        }

        $this->log("{$peer}: llamada uuid={$uuid} — inicia conversación.");

        $conversacion = IaBotConversation::create([
            'call_id'           => $uuid,
            'conversation_type' => 'sales_lead',
            'started_at'        => now(),
            'status'            => 'completed',
        ]);

        $stt     = app(VoiceSttService::class);
        $tts     = app(VoiceTtsService::class);
        $cerebro = app(ConversacionBotService::class);

        // Saludo inicial — se habla ANTES de escuchar el primer turno.
        $this->hablar($socket, $tts, str_replace('[nombre]', '', $config->greeting_lead), $conversacion);

        $turno = 0;
        $inicio = time();
        $transferido = false;

        while ($turno < $config->max_turns && (time() - $inicio) < $config->max_duration_seconds) {
            $wav = $socket->escucharTurno(900, $config->timeout_seconds);

            if ($socket->colgado()) {
                $this->log("{$uuid}: colgaron.");
                break;
            }
            if (! $wav) {
                // Silencio total del lado del que llama — se le pregunta si sigue ahí.
                $this->hablar($socket, $tts, '¿Sigues ahí? Puedes decirme en qué te ayudo.', $conversacion);
                $turno++;
                continue;
            }

            $r = $stt->transcribir($wav);
            @unlink($wav);
            if (! ($r['success'] ?? false) || trim((string) ($r['texto'] ?? '')) === '') {
                $turno++;
                continue;
            }

            $this->log("{$uuid}: cliente dijo: \"{$r['texto']}\"");
            $respuesta = $cerebro->turno($conversacion, $r['texto']);
            $this->log("{$uuid}: María responde: \"{$respuesta['texto_hablado']}\"" . ($respuesta['transferir'] ? ' [TRANSFERIR]' : ''));

            $this->hablar($socket, $tts, $respuesta['texto_hablado'], $conversacion);

            if ($respuesta['transferir']) {
                $transferido = true;
                break;
            }

            $turno++;
        }

        $conversacion->ended_at = now();
        $conversacion->duration = time() - $inicio;
        $conversacion->status   = $socket->colgado() ? 'completed' : ($transferido ? 'transferred' : 'timeout');
        $conversacion->resolved = ! $transferido && $socket->colgado();
        $conversacion->save();

        // No cierra el socket aquí — el `finally` de handle() ya hace
        // fclose($conn) sobre el mismo recurso; cerrarlo dos veces tronaba
        // con TypeError en PHP 8 (fclose() sobre un resource ya inválido no
        // se calla con @, a diferencia de versiones viejas de PHP).
        $this->log("{$uuid}: conversación cerrada — status={$conversacion->status} turnos={$turno} costo=\${$conversacion->cost_usd}");
    }

    private function hablar(AudioSocketServer $socket, VoiceTtsService $tts, string $texto, IaBotConversation $conversacion): void
    {
        $texto = trim($texto);
        if ($texto === '') {
            return;
        }
        $audio = $tts->sintetizar($texto);
        if (! ($audio['success'] ?? false)) {
            $this->log('TTS falló al hablar: ' . ($audio['error'] ?? '?'), 'warning');
            return;
        }
        $conversacion->cost_usd = (float) $conversacion->cost_usd + (float) ($audio['costo_usd'] ?? 0);
        $conversacion->save();

        $socket->enviarAudio($audio['pcm_path']);
        @unlink($audio['pcm_path']);
    }

    private function log(string $message, string $level = 'info'): void
    {
        $line = '[' . now()->format('Y-m-d H:i:s') . "] [{$this->pid()}] {$message}";
        $this->line($line);
        try {
            Log::build(['driver' => 'single', 'path' => storage_path(self::LOG_CHANNEL_PATH)])->{$level}($message);
        } catch (\Throwable) {
            Log::{$level}('[megavoz-bot-voz] ' . $message);
        }
    }

    private function pid(): int
    {
        return function_exists('posix_getpid') ? posix_getpid() : getmypid();
    }
}
