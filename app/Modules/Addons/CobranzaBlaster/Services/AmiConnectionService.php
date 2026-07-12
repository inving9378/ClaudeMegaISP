<?php

namespace App\Modules\Addons\CobranzaBlaster\Services;

use App\Modules\Addons\CobranzaBlaster\Models\VoipConfiguracion;
use Illuminate\Support\Facades\Log;

class AmiConnectionService
{
    protected string $host;
    protected int $port;
    protected string $username;
    protected string $secret;

    /** @var resource|false */
    protected $socket = false;

    public function __construct()
    {
        $this->host     = env('AMI_HOST', '127.0.0.1');
        $this->port     = (int) env('AMI_PORT', 5038);
        $this->username = env('AMI_USERNAME', 'megaisp');
        $this->secret   = env('AMI_SECRET', '');
    }

    public function connect(): bool
    {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);

        if (!$this->socket) {
            Log::error("AMI connect failed: [{$errno}] {$errstr}");
            return false;
        }

        stream_set_timeout($this->socket, 5);

        // Leer banner de Asterisk
        fgets($this->socket, 1024);

        $loginAction = "Action: Login\r\nUsername: {$this->username}\r\nSecret: {$this->secret}\r\n\r\n";
        fwrite($this->socket, $loginAction);

        $response = $this->readResponse();

        if (!str_contains($response, 'Response: Success')) {
            Log::error('AMI login failed', ['response' => $response]);
            $this->disconnect();
            return false;
        }

        return true;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            @fwrite($this->socket, "Action: Logoff\r\n\r\n");
            @fclose($this->socket);
            $this->socket = false;
        }
    }

    public function isConnected(): bool
    {
        return is_resource($this->socket) && !feof($this->socket);
    }

    public function originate(string $telefono, string $audioPath, int $llamadaId, string $clienteNombre): array
    {
        $actionId = 'blaster-' . $llamadaId . '-' . time();
        // ChannelId fija el Uniqueid del canal originado (Asterisk >= 12). Así
        // conocemos el uniqueid ANTES de marcar y podemos correlacionar cada
        // evento (Newstate/Hangup/DTMF) con la fila de cobranza_llamadas sin
        // depender del timing del evento OriginateResponse (que es asíncrono).
        $channelId = 'cob-' . $llamadaId . '-' . time();
        // C5: troncal Servnet unificada a PJSIP Realtime (endpoint id 'servnet',
        // provisionado por VoiceGateway::configureTrunk). Antes: SIP/servnet-trunk (chan_sip).
        $channel   = 'PJSIP/servnet/' . $telefono;

        $action = implode("\r\n", [
            'Action: Originate',
            'Channel: ' . $channel,
            'ChannelId: ' . $channelId,
            'Context: ' . env('AMI_CONTEXT', 'cobranza-blaster'),
            'Exten: s',
            'Priority: 1',
            'Timeout: 30000',
            'CallerID: ' . $this->resolveCallerId(),
            'Variable: COBRANZA_LLAMADA_ID=' . $llamadaId,
            'Variable: COBRANZA_NOMBRE=' . $clienteNombre,
            'Variable: COBRANZA_AUDIO=' . $audioPath,
            'ActionID: ' . $actionId,
            'Async: true',
            '', '',
        ]);

        $raw = $this->sendRaw($action);
        $parsed = $this->parseResponse($raw);

        $success = isset($parsed['Response']) && $parsed['Response'] === 'Success';

        if (!$success) {
            Log::warning('AMI Originate falló', ['llamada_id' => $llamadaId, 'response' => $parsed]);
        }

        return [
            'success'  => $success,
            'actionid' => $actionId,
            'channel'  => $channel,
            // Uniqueid determinista: el ChannelId que asignamos arriba.
            'uniqueid' => $channelId,
        ];
    }

    /**
     * Lee callerid_nombre/callerid_numero de la VoipConfiguracion (BD, config única id=1, mismo
     * patrón que VoipConfiguracionController::first()). Si la config no existe o ambos campos
     * vienen vacíos, cae al literal histórico (fallback, #189).
     */
    private function resolveCallerId(): string
    {
        try {
            $cfg = VoipConfiguracion::first();
        } catch (\Throwable $e) {
            Log::warning('AMI resolveCallerId: no se pudo leer VoipConfiguracion', ['error' => $e->getMessage()]);
            $cfg = null;
        }

        $nombre = trim((string) ($cfg->callerid_nombre ?? ''));
        $numero = trim((string) ($cfg->callerid_numero ?? ''));

        if ($nombre === '' && $numero === '') {
            return 'Meganet Telecomunicaciones <5551234567>';
        }

        return $numero !== '' ? "{$nombre} <{$numero}>" : $nombre;
    }

    public function sendRaw(string $action): string
    {
        if (!$this->isConnected()) {
            return '';
        }

        fwrite($this->socket, $action);
        return $this->readResponse();
    }

    private function readResponse(): string
    {
        $response = '';
        $timeout  = microtime(true) + 3;

        while (microtime(true) < $timeout) {
            $line = fgets($this->socket, 4096);
            if ($line === false) {
                break;
            }
            $response .= $line;
            // Respuesta AMI termina con línea en blanco
            if ($line === "\r\n" || $line === "\n") {
                break;
            }
        }

        return $response;
    }

    private function parseResponse(string $raw): array
    {
        $result = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (str_contains($line, ': ')) {
                [$key, $value] = explode(': ', $line, 2);
                $result[trim($key)] = trim($value);
            }
        }
        return $result;
    }

    /**
     * Lee un único bloque (evento o respuesta) del stream AMI.
     *
     * Devuelve el bloque parseado como array asociativo, o null si no llegó
     * nada dentro de $timeoutSeconds (para que el daemon pueda revisar señales
     * o reconectar). Pensado para el listener persistente cobranza:ami-listener.
     */
    public function readEvent(int $timeoutSeconds = 1): ?array
    {
        if (!$this->isConnected()) {
            return null;
        }

        // Esperar actividad sin bloquear indefinidamente.
        $read   = [$this->socket];
        $write  = $except = [];
        $ready  = @stream_select($read, $write, $except, $timeoutSeconds);

        if ($ready === false || $ready === 0) {
            return null;
        }

        $raw   = '';
        $first = true;

        // Cada mensaje AMI termina en una línea en blanco (\r\n).
        while (($line = fgets($this->socket, 4096)) !== false) {
            if ($line === "\r\n" || $line === "\n") {
                break;
            }
            $raw .= $line;
            // Tras la primera línea ya hay datos; si el socket se vacía paramos.
            $first = false;
        }

        if ($raw === '' && $first) {
            return null;
        }

        return $this->parseResponse($raw);
    }
}
