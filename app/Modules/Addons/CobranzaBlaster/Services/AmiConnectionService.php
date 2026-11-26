<?php

namespace App\Modules\Addons\CobranzaBlaster\Services;

use App\Modules\Addons\CobranzaBlaster\Models\VoipConfiguracion;
use Illuminate\Support\Facades\Log;

/**
 * Item #185: la unificación incremental (opción elegida por Irving) agregó
 * VoiceGateway::originate() (app/Modules/Core/Voice/VoiceGateway.php) como la
 * abstracción AMI compartida para originar llamadas. Esta clase NO se migró a
 * ella todavía a propósito: aquí usamos una conexión AMI PERSISTENTE reusada
 * por lote (BlastCampanaJob conecta una vez y origina N llamadas), mientras
 * VoiceGateway::originate() abre/cierra conexión por llamada (vía AmiClient,
 * stateless). Migrar este hot-path de cobranza en vivo a la abstracción
 * stateless es una fase separada que requiere validar contra la troncal real
 * antes de tocarlo.
 */
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
        $this->host     = config('voip.ami_host');
        $this->port     = (int) config('voip.ami_port');
        $this->username = config('voip.ami_user');
        $this->secret   = (string) config('voip.ami_pass');

        // Item #280: un default vacío permitía intentar el login AMI sin secret.
        // Fallar ruidoso aquí en vez de dejar que connect() intente con Secret vacío.
        if ($this->secret === '') {
            Log::error('AMI_SECRET no está seteado; AmiConnectionService rehúsa conectar.');
            throw new \RuntimeException(
                'AMI_SECRET no está configurado en .env. Configúralo antes de usar el blaster de cobranza.'
            );
        }
    }

    /**
     * @param bool $eventos false = sesión solo para mandar acciones (el blaster): Asterisk
     *                      no le manda eventos y nada se cuela entre acción y respuesta.
     *                      El listener sí los quiere.
     */
    public function connect(bool $eventos = true): bool
    {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);

        if (!$this->socket) {
            Log::error("AMI connect failed: [{$errno}] {$errstr}");
            return false;
        }

        stream_set_timeout($this->socket, 5);

        // Leer banner de Asterisk
        fgets($this->socket, 1024);

        $loginId = 'login-' . getmypid() . '-' . time();
        $loginAction = "Action: Login\r\nUsername: {$this->username}\r\nSecret: {$this->secret}\r\n"
            . ($eventos ? '' : "Events: off\r\n")
            . "ActionID: {$loginId}\r\n\r\n";
        fwrite($this->socket, $loginAction);

        $response = $this->readResponse($loginId);

        if (!str_contains($response, 'Response: Success')) {
            Log::error('AMI login failed', ['response' => $response]);
            $this->disconnect();
            return false;
        }

        return true;
    }

    public function disconnect(): void
    {
        // is_resource: un socket ya cerrado sigue siendo «verdadero» y fwrite sobre él
        // lanza TypeError (no lo calla la @).
        if (is_resource($this->socket)) {
            @fwrite($this->socket, "Action: Logoff\r\n\r\n");
            @fclose($this->socket);
        }
        $this->socket = false;
    }

    public function isConnected(): bool
    {
        return is_resource($this->socket) && !feof($this->socket);
    }

    public function originate(string $telefono, string $audioPath, int $llamadaId, string $clienteNombre, ?string $channelId = null): array
    {
        $actionId = 'blaster-' . $llamadaId . '-' . time();
        // ChannelId fija el Uniqueid del canal originado (Asterisk >= 12). Así
        // conocemos el uniqueid ANTES de marcar y podemos correlacionar cada
        // evento (Newstate/Hangup/DTMF) con la fila de cobranza_llamadas sin
        // depender del timing del evento OriginateResponse (que es asíncrono).
        // El blaster lo pasa ya guardado en la fila (25-nov).
        $channelId ??= 'cob-' . $llamadaId . '-' . time();
        // C5: troncal unificada a PJSIP Realtime (identidad configurable, ver
        // config/voip.php), provisionada por VoiceGateway::configureTrunk. Antes:
        // SIP/servnet-trunk (chan_sip). Item #9990714: el endpoint id ya no viaja
        // hardcodeado aquí — el default vive únicamente en config/voip.php.
        // Siempre la troncal del módulo (Config. VoIP); las campañas ya no eligen otra
        // (pedido de Irving, 23-nov).
        $channel   = 'PJSIP/' . config('voip.trunk_endpoint_id') . '/' . $telefono;

        // #277: sin CallerID configurado, resolveCallerId() ya no cae al placeholder
        // 'Meganet Telecomunicaciones <5551234567>'. Se ABORTA el originate (fallback duro,
        // aprobado por Irving) en vez de dejar salir un número placeholder a un cliente real.
        $callerId = $this->resolveCallerId();

        if ($callerId === null) {
            Log::error('AMI Originate abortado: VoipConfiguracion sin callerid_nombre/callerid_numero configurado; no se llama con un CallerID placeholder.', [
                'llamada_id' => $llamadaId,
            ]);

            return [
                'success'  => false,
                'actionid' => $actionId,
                'channel'  => $channel,
                'uniqueid' => $channelId,
            ];
        }

        $action = implode("\r\n", [
            'Action: Originate',
            'Channel: ' . $channel,
            'ChannelId: ' . $channelId,
            'Context: ' . config('voip.ami_context'),
            'Exten: s',
            'Priority: 1',
            'Timeout: 30000',
            'CallerID: ' . $callerId,
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
     * vienen vacíos, devuelve null (#277: sin fallback a placeholder; originate() aborta la
     * llamada en vez de identificarse con un número que no es real).
     */
    private function resolveCallerId(): ?string
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
            return null;
        }

        return $numero !== '' ? "{$nombre} <{$numero}>" : $nombre;
    }

    public function sendRaw(string $action): string
    {
        if (!$this->isConnected()) {
            return '';
        }

        fwrite($this->socket, $action);

        return $this->readResponse(preg_match('/^ActionID:\s*(\S+)/mi', $action, $m) ? $m[1] : null);
    }

    /**
     * La respuesta a la acción que se mandó, no lo primero que llegue (25-nov). Antes se
     * leía un solo bloque: tras el login llega el evento FullyBooted y, en una sesión con
     * eventos, Newchannel y compañía, así que cada Originate leía lo de la acción anterior.
     * La mitad de las llamadas quedaban como fallidas mientras timbraban y se volvían a
     * marcar. Ahora se saltan los eventos y se espera la respuesta con el mismo ActionID.
     */
    private function readResponse(?string $actionId = null, float $segundos = 5.0): string
    {
        $limite = microtime(true) + $segundos;

        while (microtime(true) < $limite) {
            $bloque = $this->leerBloque($limite);
            if ($bloque === null) {
                break;
            }
            if (!preg_match('/^Response:/mi', $bloque)) {
                continue;   // un evento, no la respuesta
            }
            if ($actionId === null
                || preg_match('/^ActionID:\s*' . preg_quote($actionId, '/') . '\s*$/mi', $bloque)) {
                return $bloque;
            }
        }

        return '';
    }

    /** Un bloque AMI (líneas hasta la línea en blanco); null si el socket se cerró o no llegó nada. */
    private function leerBloque(float $limite): ?string
    {
        $bloque = '';

        while (microtime(true) < $limite) {
            $linea = fgets($this->socket, 8192);
            if ($linea === false) {
                return $bloque === '' ? null : $bloque;
            }
            if ($linea === "\r\n" || $linea === "\n") {
                if ($bloque === '') {
                    continue;
                }

                return $bloque;
            }
            $bloque .= $linea;
        }

        return $bloque === '' ? null : $bloque;
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
