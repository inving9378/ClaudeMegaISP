<?php

namespace App\Modules\Addons\CobranzaBlaster\Services;

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

        $action = implode("\r\n", [
            'Action: Originate',
            'Channel: SIP/servnet-trunk/' . $telefono,
            'Context: ' . env('AMI_CONTEXT', 'cobranza-blaster'),
            'Exten: s',
            'Priority: 1',
            'Timeout: 30000',
            'CallerID: Meganet Telecomunicaciones <5551234567>',
            'Variable: COBRANZA_CLIENT_ID=' . $llamadaId,
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
            'uniqueid' => $parsed['Uniqueid'] ?? null,
        ];
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
}
