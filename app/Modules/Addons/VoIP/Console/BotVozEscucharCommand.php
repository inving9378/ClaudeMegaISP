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
 * Conectado al dialplan real (grupo-1, cola de Atención a Clientes) desde
 * el 2026-09-24 — con `ia_bot_config.piloto_porcentaje=0` de default, así
 * que ningún cliente real habla con María hasta que alguien suba ese
 * número en `/voip/ia-bot`. El dialplan generado antepone un candado de
 * pre-vuelo (`TrySystem(nc -z ...)`) antes de intentar `AudioSocket()`: si
 * ESTE daemon no está corriendo, la llamada sigue derecho a la cola real
 * — nunca se cuelga (ver `DialplanGeneratorService::buildColaExten()` y
 * `deploy/README-bot-voz.md` para el detalle del bug que eso corrige).
 * Para que el piloto realmente atienda algo, este comando debe quedar
 * corriendo de forma persistente — ver `deploy/megaisp-bot-voz.service`
 * (NO instalado todavía, requiere systemd con privilegios que este
 * usuario no tiene).
 */
class BotVozEscucharCommand extends Command
{
    protected $signature = 'voip:bot-voz-escuchar {--host=} {--port=}';

    protected $description = 'Daemon AudioSocket de María (MegaVoz Fase 6) — NO conectado al dialplan real todavía';

    private const LOG_CHANNEL_PATH = 'logs/megavoz-bot-voz.log';

    // Frases de relleno — se habla UNA justo después de detectar que la
    // persona terminó su turno y ANTES de arrancar transcribir→pensar→
    // convertir a voz (los 3 pasos reales, que entre los tres suman varios
    // segundos). Sin esto el que llama escucha puro silencio mientras
    // María "procesa" — encontrado en vivo (24-sep-2026, David probando):
    // "se demora en responder". Cacheadas igual que el saludo — el costo
    // real de generarlas solo se paga la primera vez.
    private const FRASES_ESPERA = [
        'Mmm, dame un segundo...',
        'Déjame revisar eso...',
        'Un momento, por favor...',
        'Ok, permíteme un momento...',
    ];

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

        $this->info("🎙️  Bot de voz escuchando en tcp://{$host}:{$port} (AISLADO, sin conectar a llamadas reales). Ctrl+C para detener.");
        $this->log("Daemon iniciado en {$host}:{$port}");

        // Pre-calienta la caché del saludo ANTES de aceptar la primera
        // llamada — si el saludo se sintetizara en frío en la primera
        // conversación real, esa primera llamada pagaría la misma latencia
        // de OpenAI+ffmpeg que causó el bug del timeout de 2000ms (ver
        // VoiceTtsService::sintetizarCacheado()). Best-effort: si falla
        // (ej. sin red al arrancar), no tumba el daemon — la primera
        // llamada simplemente cachearía en caliente como antes.
        //
        // ⚠️ DEBE correr ANTES de poner SIGCHLD=SIG_IGN (abajo): ese ignore
        // es para no dejar zombies de LLAMADAS ya terminadas, pero también
        // rompe silenciosamente la espera de Symfony Process por EL PROPIO
        // ffmpeg de este pre-calentado (mismo bug ya conocido y corregido
        // para los hijos por-llamada — encontrado de nuevo aquí en vivo:
        // "Caché de saludo FALLÓ: No se pudo convertir el audio a PCM" con
        // SIGCHLD ya en SIG_IGN, aunque OpenAI sí respondió bien).
        try {
            $ttsCache = app(VoiceTtsService::class);

            $config = IaBotConfig::current();
            $saludo = trim(str_replace('[nombre]', '', $config->greeting_lead));
            if ($saludo !== '') {
                $r = $ttsCache->sintetizarCacheado($saludo);
                $this->log('Caché de saludo ' . (($r['success'] ?? false) ? 'lista' : ('FALLÓ: ' . ($r['error'] ?? '?'))));
            }

            foreach (self::FRASES_ESPERA as $frase) {
                $r = $ttsCache->sintetizarCacheado($frase);
                if (! ($r['success'] ?? false)) {
                    $this->log("Caché de frase de espera FALLÓ (\"{$frase}\"): " . ($r['error'] ?? '?'), 'warning');
                }
            }
            $this->log('Caché de frases de espera lista (' . count(self::FRASES_ESPERA) . ')');
        } catch (\Throwable $e) {
            $this->log('No se pudo pre-calentar la caché de audio: ' . $e->getMessage(), 'warning');
        }

        // No dejar zombies de los hijos que van terminando (llamadas, no
        // este pre-calentado, que ya corrió arriba).
        pcntl_signal(SIGCHLD, SIG_IGN);

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
        // Cacheado (ver VoiceTtsService::sintetizarCacheado): el texto es
        // fijo, así que se manda casi instantáneo en vez de esperar una
        // llamada real a OpenAI — evita el timeout de 2000ms de AudioSocket.
        $this->hablarCacheado($socket, $tts, str_replace('[nombre]', '', $config->greeting_lead), $conversacion);

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

            // Relleno cacheado ANTES de los 3 pasos reales (transcribir→
            // pensar→hablar) — esos suman varios segundos, sin esto el que
            // llama escucha silencio muerto en lo que María "procesa".
            $this->hablarCacheado($socket, $tts, self::FRASES_ESPERA[array_rand(self::FRASES_ESPERA)], $conversacion);

            $t0 = microtime(true);
            $r = $stt->transcribir($wav);
            @unlink($wav);
            $msStt = round((microtime(true) - $t0) * 1000);

            if (! ($r['success'] ?? false) || trim((string) ($r['texto'] ?? '')) === '') {
                $this->log("{$uuid}: turno descartado (silencio/alucinación) — STT={$msStt}ms");
                $turno++;
                continue;
            }

            $this->log("{$uuid}: cliente dijo: \"{$r['texto']}\" (STT={$msStt}ms)");

            $t0 = microtime(true);
            $respuesta = $cerebro->turno($conversacion, $r['texto']);
            $msLlm = round((microtime(true) - $t0) * 1000);
            $this->log("{$uuid}: María responde: \"{$respuesta['texto_hablado']}\"" . ($respuesta['transferir'] ? ' [TRANSFERIR]' : '') . " (LLM={$msLlm}ms)");

            $t0 = microtime(true);
            $this->hablar($socket, $tts, $respuesta['texto_hablado'], $conversacion);
            $msTts = round((microtime(true) - $t0) * 1000);
            $this->log("{$uuid}: turno completo — STT={$msStt}ms LLM={$msLlm}ms TTS={$msTts}ms total=" . ($msStt + $msLlm + $msTts) . 'ms');

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

    /**
     * Igual que hablar(), pero para texto FIJO cacheable en disco (el
     * saludo) — NUNCA borra el .pcm después de usarlo, es el mismo archivo
     * que reutilizan todas las llamadas siguientes.
     */
    private function hablarCacheado(AudioSocketServer $socket, VoiceTtsService $tts, string $texto, IaBotConversation $conversacion): void
    {
        $texto = trim($texto);
        if ($texto === '') {
            return;
        }
        $audio = $tts->sintetizarCacheado($texto);
        if (! ($audio['success'] ?? false)) {
            $this->log('TTS (cacheado) falló al hablar: ' . ($audio['error'] ?? '?'), 'warning');
            return;
        }
        $conversacion->cost_usd = (float) $conversacion->cost_usd + (float) ($audio['costo_usd'] ?? 0);
        $conversacion->save();

        $socket->enviarAudio($audio['pcm_path']);
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
