<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Modules\Addons\VoIP\Models\IaBotConfig;
use App\Modules\Addons\VoIP\Models\IaBotConversation;
use App\Modules\Addons\VoIP\Services\IABotCustomerService;
use App\Modules\Addons\VoIP\Services\Voz\AudioSocketServer;
use App\Modules\Addons\VoIP\Services\Voz\ConversacionBotService;
use App\Modules\Addons\VoIP\Services\Voz\VoiceSttService;
use App\Modules\Addons\VoIP\Services\Voz\VoiceTtsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MegaVoz Fase 6 — servicio AudioSocket de la asistente de voz (systemd: megaisp-bot-voz, lo
 * deja «Instalar dependencias» de VoIP, paso 50). Una llamada = un proceso hijo (pcntl_fork).
 *
 * El plan de marcado de los grupos tipo cola le pasa cada llamada: el candado de pre-vuelo
 * (`TrySystem(nc -z ...)`) la manda directo a la cola si este servicio no escucha, y si aquí
 * se decide no atenderla (piloto, horario, líneas ocupadas) se cierra el socket y la llamada
 * sigue a la cola igual. Ver DialplanGeneratorService::buildColaExten().
 *
 * 27-nov («funcionando al máximo»):
 *  - Sabe quién llama (el número viene en el UUID) y, si es cliente, lo saluda por su nombre y
 *    contesta con su estado, plan y saldo.
 *  - La frase de espera suena mientras se transcribe, se piensa y se genera la respuesta.
 *  - Voz, tono, velocidad y líneas a la vez salen de la pantalla.
 *  - Dos silencios seguidos, o llegar al tope de turnos o de tiempo, pasan a una persona con
 *    aviso (antes preguntaba «¿sigues ahí?» hasta agotar los turnos).
 *  - Al colgar deja el resumen y, si aplica, el lead, ya con la llamada en la cola.
 *  - Tras una actualización (el deploy hace queue:restart) deja de aceptar llamadas, espera a
 *    que terminen las que atiende y sale; systemd lo levanta con el código nuevo. Antes seguía
 *    con el código viejo hasta que alguien lo reiniciara a mano.
 */
class BotVozEscucharCommand extends Command
{
    protected $signature = 'voip:bot-voz-escuchar {--host=} {--port=}';

    protected $description = 'Servicio AudioSocket de la asistente de voz (lo deja corriendo «Instalar dependencias» de VoIP)';

    private const LOG_CHANNEL_PATH = 'logs/megavoz-bot-voz.log';

    /** Cada cuánto revisa si llegó una versión nueva o cambió la voz (segundos). */
    private const REVISAR_CADA = 5;

    /** Silencio que marca que la persona terminó de hablar (ms). Más corto partía frases en una pausa. */
    private const SILENCIO_FIN_TURNO_MS = 1000;

    // Frases de relleno: suenan mientras se transcribe, se piensa y se genera la voz de la
    // respuesta (entre los tres, varios segundos). Cacheadas: el costo se paga una vez.
    // 27-nov: más largas (~2.5 s): la espera real es de 3 a 5 s y con las de 1 s quedaba un hueco mudo.
    public const FRASES_ESPERA = [
        'Mmm, déjame revisarlo, dame un segundito...',
        'Muy bien, permíteme un momento mientras lo reviso...',
        'Ok, un momento por favor, ya lo estoy viendo...',
        'A ver, déjame checarlo, un segundo por favor...',
    ];

    public const SIGUES_AHI = '¿Sigues ahí? Dime en qué te puedo ayudar.';
    public const NO_TE_ESCUCHE = 'Perdón, no te escuché bien. ¿Me lo repites?';
    public const PASO_A_PERSONA = 'Te comunico con una persona para que te siga atendiendo. Un momento, por favor.';

    /** @var array<int, true> procesos de llamadas y del precalentado que siguen vivos */
    private array $hijos = [];

    private ?string $ultimaFrase = null;

    public function handle(): int
    {
        // Sin --host/--port explícitos, misma fuente que lee el plan de marcado generado
        // (config/voip.php → bot_voz): un solo lugar donde vive esta dirección.
        $host = (string) ($this->option('host') ?: config('voip.bot_voz.host', '127.0.0.1'));
        $port = (int) ($this->option('port') ?: config('voip.bot_voz.port', 9099));

        if (! extension_loaded('pcntl') || ! extension_loaded('posix')) {
            $this->error('Faltan las extensiones pcntl/posix: son necesarias para atender llamadas simultáneas.');
            return self::FAILURE;
        }

        $server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (! $server) {
            $this->error("No se pudo abrir tcp://{$host}:{$port} — {$errstr} ({$errno})");
            return self::FAILURE;
        }

        $this->log("Servicio iniciado en {$host}:{$port}");

        $marcaReinicio = $this->marcaReinicio();
        $firmaVoz = null;
        $ultimaRevision = 0;
        $detener = false;

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function () use (&$detener) {
            $detener = true;
        });
        pcntl_signal(SIGINT, function () use (&$detener) {
            $detener = true;
        });

        while (! $detener) {
            if (time() - $ultimaRevision >= self::REVISAR_CADA) {
                $ultimaRevision = time();

                if ($this->marcaReinicio() !== $marcaReinicio) {
                    $this->log('Llegó una versión nueva: ya no se aceptan llamadas; se sale cuando terminen las que están en curso (systemd lo vuelve a levantar).');
                    break;
                }

                // La voz o los saludos cambiaron en la pantalla (o recién arrancó): se precalientan
                // en otro proceso para que el saludo y las frases de espera suenen al instante.
                $firma = $this->firmaVoz();
                if ($firma !== null && $firma !== $firmaVoz) {
                    $firmaVoz = $firma;
                    $this->precalentarEnHijo($server);
                }
            }

            $conn = @stream_socket_accept($server, self::REVISAR_CADA, $peer);
            $this->recogerHijos();

            if ($conn) {
                $this->atenderEnHijo($server, $conn, (string) $peer);
            }
        }

        @fclose($server);
        while ($this->hijos !== []) {
            $pid = pcntl_waitpid(-1, $estado);
            if ($pid <= 0) {
                break;
            }
            unset($this->hijos[$pid]);
        }
        $this->log('Servicio detenido.');

        return self::SUCCESS;
    }

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

    /** Lo que cambia los audios cacheados: voz, tono, velocidad, saludos y el modelo de voz. */
    private function firmaVoz(): ?string
    {
        try {
            $c = IaBotConfig::current();
            $firma = md5(json_encode([$c->opcionesVoz(), $c->greeting_lead, $c->greeting_customer, VoiceTtsService::modeloAsignado()]));
        } catch (\Throwable $e) {
            $this->log('No se pudo leer la configuración: ' . $e->getMessage(), 'warning');
            $firma = null;
        }
        // El padre no se queda con la conexión: los hijos nacen sin ella y abren la suya.
        DB::disconnect();

        return $firma;
    }

    private function recogerHijos(): void
    {
        while (($pid = pcntl_waitpid(-1, $estado, WNOHANG)) > 0) {
            unset($this->hijos[$pid]);
        }
    }

    private function atenderEnHijo($server, $conn, string $peer): void
    {
        DB::disconnect();
        $pid = pcntl_fork();

        if ($pid === -1) {
            $this->log('pcntl_fork falló: la llamada se atiende en el proceso principal.', 'error');
            $this->atenderLlamada($conn, $peer);
            if (is_resource($conn)) {
                @fclose($conn);
            }
            DB::disconnect();
            return;
        }

        if ($pid === 0) {
            pcntl_signal(SIGTERM, SIG_DFL);
            pcntl_signal(SIGINT, SIG_DFL);
            @fclose($server);
            try {
                $this->atenderLlamada($conn, $peer);
            } catch (\Throwable $e) {
                $this->log("ERROR en llamada {$peer}: " . $e->getMessage(), 'error');
            } finally {
                // atenderLlamada() ya puede haberlo cerrado: fclose() de un recurso cerrado truena en PHP 8.
                if (is_resource($conn)) {
                    @fclose($conn);
                }
            }
            exit(0);
        }

        $this->hijos[$pid] = true;
        @fclose($conn);
    }

    private function precalentarEnHijo($server): void
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            return;
        }
        if ($pid === 0) {
            pcntl_signal(SIGTERM, SIG_DFL);
            pcntl_signal(SIGINT, SIG_DFL);
            // Sin el puerto: si el padre lo cierra para reiniciarse, que de verdad deje de aceptar.
            @fclose($server);
            try {
                $this->precalentar();
            } catch (\Throwable $e) {
                $this->log('No se pudo precalentar la caché de audio: ' . $e->getMessage(), 'warning');
            }
            exit(0);
        }
        $this->hijos[$pid] = true;
    }

    /** Genera y guarda los audios fijos con la voz de la pantalla. */
    private function precalentar(): void
    {
        $config = IaBotConfig::current();
        $voz = $config->opcionesVoz();
        $tts = app(VoiceTtsService::class);

        $fijas = array_merge(
            [self::saludo($config, null), self::SIGUES_AHI, self::NO_TE_ESCUCHE, self::PASO_A_PERSONA],
            self::FRASES_ESPERA
        );
        if (! str_contains((string) $config->greeting_customer, '[nombre]')) {
            $fijas[] = self::saludo($config, ['found' => true, 'nombre_pila' => '']);
        }

        $tts->purgarCache();

        $fallas = 0;
        foreach (array_unique(array_filter($fijas)) as $frase) {
            $r = $tts->sintetizarCacheado($frase, $voz);
            if (! ($r['success'] ?? false)) {
                $fallas++;
                $this->log("Caché de audio FALLÓ («{$frase}»): " . ($r['error'] ?? '?'), 'warning');
            }
        }
        $this->log($fallas ? "Caché de audio con {$fallas} fallas" : 'Caché de audio lista (saludo y frases fijas)');
    }

    /** El saludo: con nombre si es cliente y el saludo de clientes lo pide; si no, el general. */
    public static function saludo(IaBotConfig $config, ?array $cliente): string
    {
        $esCliente = $cliente && ! empty($cliente['found']) && trim((string) $config->greeting_customer) !== '';
        $plantilla = $esCliente ? (string) $config->greeting_customer : (string) $config->greeting_lead;
        $nombre = $esCliente ? trim((string) ($cliente['nombre_pila'] ?? '')) : '';

        $texto = str_replace('[nombre]', $nombre, $plantilla);
        // «Hola , bienvenido» / «Hola  bienvenido» cuando no hay nombre.
        $texto = preg_replace('/\s+([,.!?])/u', '$1', $texto);
        $texto = preg_replace('/([¡¿])\s+/u', '$1', $texto);
        $texto = preg_replace('/,(\s*,)+/u', ',', $texto);

        return trim(preg_replace('/\s{2,}/u', ' ', $texto));
    }

    /**
     * Toma una de las líneas de la asistente (archivo con flock). null = todas ocupadas.
     * El recurso devuelto debe seguir vivo mientras dure la llamada.
     *
     * @return resource|null
     */
    private function tomarLinea(int $max)
    {
        $dir = storage_path('app/megavoz-lineas');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        for ($i = 1; $i <= $max; $i++) {
            $fh = @fopen("{$dir}/linea-{$i}.lock", 'c');
            if ($fh && flock($fh, LOCK_EX | LOCK_NB)) {
                return $fh;
            }
            if ($fh) {
                fclose($fh);
            }
        }

        return null;
    }

    private function identificar(?string $numero): ?array
    {
        if ($numero === null) {
            return null;
        }
        try {
            $clientes = app(IABotCustomerService::class);
            $cliente = $clientes->identifyCustomer($numero);
            if (empty($cliente['found'])) {
                return null;
            }

            return $cliente + $clientes->contextoParaBot((int) $cliente['id']);
        } catch (\Throwable $e) {
            $this->log("No se pudo identificar al cliente: {$e->getMessage()}", 'warning');
            return null;
        }
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

        // El piloto (%) y el horario se deciden AQUÍ, por llamada. Si no le toca, se cierra el
        // socket sin decir nada y el plan de marcado sigue a la cola. No se crea conversación:
        // solo ensuciaría la lista con llamadas que la asistente nunca atendió.
        if (! $config->debeAtenderAhora()) {
            $this->log("{$uuid}: fuera del piloto/horario, se declina (sigue a la cola).");
            return;
        }

        // Una línea por llamada: con todas ocupadas, esta pasa a la cola con una persona.
        // El candado se suelta solo si el proceso truena.
        $linea = $this->tomarLinea($config->lineasEfectivas());
        if ($linea === null) {
            $this->log("{$uuid}: todas las líneas ocupadas ({$config->lineasEfectivas()}), pasa a la cola.");
            return;
        }

        $numero = AudioSocketServer::numeroQueLlama($uuid);
        $cliente = $this->identificar($numero);
        $voz = $config->opcionesVoz();

        $this->log("{$uuid}: inicia — " . ($numero ? "llama {$numero}" : 'sin número') . ($cliente ? ", cliente #{$cliente['id']}" : ''));

        $saludo = self::saludo($config, $cliente);
        $conversacion = IaBotConversation::create([
            'call_id'           => $uuid,
            'phone_number'      => $numero ? substr($numero, -20) : null,
            'customer_id'       => $cliente['id'] ?? null,
            'customer_name'     => $cliente['name'] ?? null,
            'customer_email'    => $cliente['email'] ?? null,
            'conversation_type' => $cliente ? 'customer_support' : 'sales_lead',
            'started_at'        => now(),
            'status'            => 'completed',
            // El saludo va en la conversación: así la IA no vuelve a saludar en su primera respuesta.
            'transcript'        => $saludo !== '' ? [['rol' => 'assistant', 'contenido' => $saludo]] : [],
        ]);

        $stt     = app(VoiceSttService::class);
        $tts     = app(VoiceTtsService::class);
        $cerebro = app(ConversacionBotService::class);

        $turno = 0;
        $silencios = 0;
        $inicio = time();
        // Sin voz no hay conversación: si ni el saludo sale, la llamada sigue directo a la cola.
        $transferido = ! $this->decir($socket, $tts, $saludo, $voz, $conversacion, true);
        $sinVoz = $transferido;

        while (! $transferido && ! $socket->colgado() && $turno < $config->max_turns && (time() - $inicio) < $config->max_duration_seconds) {
            $wav = $socket->escucharTurno(self::SILENCIO_FIN_TURNO_MS, (int) $config->timeout_seconds);
            if ($socket->colgado()) {
                break;
            }

            if (! $wav && $socket->vozCorta()) {
                // Dijo algo muy corto o con mucho ruido: no es silencio, se le pide que lo repita.
                $this->decir($socket, $tts, self::NO_TE_ESCUCHE, $voz, $conversacion, true);
                $turno++;
                continue;
            }
            if (! $wav) {
                // Silencio total: una vez se pregunta si sigue ahí; la segunda pasa a una persona.
                if (++$silencios >= 2) {
                    $transferido = true;
                    break;
                }
                $this->decir($socket, $tts, self::SIGUES_AHI, $voz, $conversacion, true);
                $turno++;
                continue;
            }
            $silencios = 0;

            // La frase de espera arranca YA y suena mientras se transcribe, se piensa y se
            // genera la voz de la respuesta.
            $finDelCliente = microtime(true);
            $fondo = $socket->reproducirDeFondo($tts->sintetizarCacheadoSiExiste($this->fraseDeEspera(), $voz));

            $r = $stt->transcribir($wav, (string) $config->language);
            @unlink($wav);
            $msStt = (int) round((microtime(true) - $finDelCliente) * 1000);
            $texto = trim((string) ($r['texto'] ?? ''));

            if (! ($r['success'] ?? false)) {
                // El oído falló (sin IA, sin saldo, sin red): no tiene caso seguir preguntando.
                $socket->detenerFondo($fondo);
                $this->log("{$uuid}: falló la transcripción, pasa a una persona — " . ($r['error'] ?? '?'), 'warning');
                $transferido = true;
                break;
            }
            if ($texto === '') {
                $socket->detenerFondo($fondo);
                $this->log("{$uuid}: no se entendió el turno (silencio/ruido) — STT={$msStt}ms");
                $this->decir($socket, $tts, self::NO_TE_ESCUCHE, $voz, $conversacion, true);
                $turno++;
                continue;
            }

            $t0 = microtime(true);
            $respuesta = $cerebro->turno($conversacion, $texto, $cliente);
            $msLlm = (int) round((microtime(true) - $t0) * 1000);

            $t0 = microtime(true);
            $audio = $respuesta['texto_hablado'] !== '' ? $tts->sintetizarFrases($respuesta['texto_hablado'], $voz) : ['success' => false];
            $msTts = (int) round((microtime(true) - $t0) * 1000);

            $socket->detenerFondo($fondo);
            $msEspera = (int) round((microtime(true) - $finDelCliente) * 1000);

            $this->log("{$uuid}: «{$texto}» → «{$respuesta['texto_hablado']}»" . ($respuesta['transferir'] ? ' [TRANSFERIR]' : '')
                . " — STT={$msStt}ms IA={$msLlm}ms voz={$msTts}ms, espera total={$msEspera}ms");

            if ($audio['success'] ?? false) {
                $this->sumarCosto($conversacion, (float) ($audio['costo_usd'] ?? 0));
                $socket->enviarAudio($audio['pcm_path']);
                @unlink($audio['pcm_path']);
            } else {
                // Sin voz no puede contestar: mejor una persona que una línea muda.
                $this->log("{$uuid}: la voz de la respuesta falló, pasa a una persona — " . ($audio['error'] ?? '?'), 'warning');
                $transferido = true;
                break;
            }

            if ($respuesta['transferir']) {
                $transferido = true;
                break;
            }

            $turno++;
        }

        // Se acabaron los turnos o el tiempo, o dos silencios: se avisa antes de pasar a la cola.
        if (! $socket->colgado() && ! $transferido) {
            $transferido = true;
        }
        if ($transferido && ! $socket->colgado() && ! $conversacion->escalated) {
            if (! $sinVoz) {
                $this->decir($socket, $tts, self::PASO_A_PERSONA, $voz, $conversacion, true);
            }
            $conversacion->escalated = true;
            $conversacion->transferred_at = now();
        }

        $colgado = $socket->colgado();
        $conversacion->ended_at = now();
        $conversacion->duration = time() - $inicio;
        $conversacion->status   = $colgado ? 'completed' : 'transferred';
        $conversacion->save();

        // Primero se suelta la llamada (sigue a la cola sin esperar) y la línea; luego el análisis.
        $socket->cerrar();
        flock($linea, LOCK_UN);
        fclose($linea);

        $this->log("{$uuid}: termina — {$conversacion->status}, {$turno} turnos, \${$conversacion->cost_usd}");

        try {
            $cerebro->analizar($conversacion);
        } catch (\Throwable $e) {
            $this->log("{$uuid}: no se pudo analizar: {$e->getMessage()}", 'warning');
        }
    }

    /** Una frase de espera distinta a la anterior. */
    private function fraseDeEspera(): string
    {
        $opciones = array_values(array_diff(self::FRASES_ESPERA, [$this->ultimaFrase]));
        $this->ultimaFrase = $opciones[array_rand($opciones)];

        return $this->ultimaFrase;
    }

    /**
     * Dice $texto. Si el audio no está hecho, mientras se genera suena silencio en otro proceso
     * (AudioSocket cuelga si el servidor pasa ~2 s sin mandar audio).
     */
    /** @return bool false si la voz falló (no se dijo nada). */
    private function decir(AudioSocketServer $socket, VoiceTtsService $tts, string $texto, array $voz, IaBotConversation $conversacion, bool $cacheable): bool
    {
        $texto = trim($texto);
        if ($texto === '' || $socket->colgado()) {
            return true;
        }

        $listo = $cacheable ? $tts->sintetizarCacheadoSiExiste($texto, $voz) : null;
        if ($listo !== null) {
            $socket->enviarAudio($listo);
            return true;
        }

        $fondo = $socket->reproducirDeFondo(null);
        $audio = $cacheable ? $tts->sintetizarCacheado($texto, $voz) : $tts->sintetizar($texto, $voz);
        $socket->detenerFondo($fondo);

        if (! ($audio['success'] ?? false)) {
            $this->log('La voz falló: ' . ($audio['error'] ?? '?'), 'warning');
            return false;
        }
        $this->sumarCosto($conversacion, (float) ($audio['costo_usd'] ?? 0));
        $socket->enviarAudio($audio['pcm_path']);
        if (! $cacheable) {
            @unlink($audio['pcm_path']);
        }

        return true;
    }

    private function sumarCosto(IaBotConversation $conversacion, float $costo): void
    {
        if ($costo <= 0) {
            return;
        }
        $conversacion->cost_usd = (float) $conversacion->cost_usd + $costo;
        $conversacion->save();
    }

    private function log(string $message, string $level = 'info'): void
    {
        $pid = function_exists('posix_getpid') ? posix_getpid() : getmypid();
        $this->line('[' . now()->format('Y-m-d H:i:s') . "] [{$pid}] {$message}");
        try {
            Log::build(['driver' => 'single', 'path' => storage_path(self::LOG_CHANNEL_PATH)])->{$level}($message);
        } catch (\Throwable) {
            Log::{$level}('[megavoz-bot-voz] ' . $message);
        }
    }
}
