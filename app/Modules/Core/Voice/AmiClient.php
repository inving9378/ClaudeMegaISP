<?php

namespace App\Modules\Core\Voice;

/**
 * Cliente AMI (Asterisk Manager Interface) único y compartido.
 *
 * Socket crudo (fsockopen) — no hay librería AMI en el proyecto (ni pami ni
 * phpari). Login con el usuario del gateway (config voip.ami_*, por defecto
 * `megaisp`). Cada llamada a action()/send() abre, autentica, envía, lee y
 * cierra la conexión (stateless), igual que el patrón previo del que se
 * extrajo: AsteriskProvisioningService::amiSend.
 *
 * Este es el ÚNICO cliente AMI del sistema; VoIP/María y CobranzaBlaster
 * (vía VoiceGateway) deben hablar AMI por aquí, no por sockets propios.
 */
class AmiClient
{
    private string $host;
    private int $port;
    private string $user;
    private string $secret;

    public function __construct(?string $host = null, ?int $port = null, ?string $user = null, ?string $secret = null)
    {
        $this->host   = $host   ?? config('voip.ami_host', '127.0.0.1');
        $this->port   = $port   ?? (int) config('voip.ami_port', 5038);
        $this->user   = $user   ?? config('voip.ami_user', 'megaisp');
        $this->secret = $secret ?? config('voip.ami_pass', '');
    }

    /**
     * Envía una acción AMI ya formateada (headers + línea en blanco final) y
     * devuelve la respuesta cruda. Abre y cierra la conexión en cada llamada.
     *
     * @param string $action  "Action: ...\r\n...\r\n\r\n"
     * @param bool   $readAll true para EventList (lee hasta "EventList: Complete")
     */
    public function action(string $action, bool $readAll = false): string
    {
        $sock = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if (!$sock) {
            throw new \RuntimeException("AMI connect failed: [{$errno}] {$errstr}");
        }

        stream_set_timeout($sock, 6);
        fgets($sock, 1024); // Banner de Asterisk

        fwrite($sock, "Action: Login\r\nUsername: {$this->user}\r\nSecret: {$this->secret}\r\n\r\n");
        $loginResp = $this->readBlock($sock);
        if (!str_contains($loginResp, 'Response: Success')) {
            @fclose($sock);
            throw new \RuntimeException("AMI login failed: {$loginResp}");
        }

        fwrite($sock, $action);
        $response = $readAll ? $this->readUntilComplete($sock) : $this->readBlock($sock);

        @fwrite($sock, "Action: Logoff\r\n\r\n");
        @fclose($sock);

        return $response;
    }

    /**
     * Construye la acción a partir de nombre + params y la envía.
     *
     * @param array<string,scalar> $params
     */
    public function send(string $actionName, array $params = [], bool $readAll = false): string
    {
        $str = "Action: {$actionName}\r\n";
        foreach ($params as $k => $v) {
            $str .= "{$k}: {$v}\r\n";
        }
        $str .= "\r\n";

        return $this->action($str, $readAll);
    }

    /** Lee un único bloque (termina en línea en blanco \r\n\r\n). */
    public function readBlock($sock, int $timeout = 4): string
    {
        $buf      = '';
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $line = @fgets($sock, 4096);
            if ($line === false) {
                break;
            }
            $buf .= $line;
            if (str_ends_with(rtrim($buf, "\n"), "\r\n\r\n") || str_ends_with($buf, "\r\n\r\n")) {
                break;
            }
        }
        return $buf;
    }

    /** Lee hasta "EventList: Complete" (para acciones tipo EventList). */
    public function readUntilComplete($sock, int $timeout = 8): string
    {
        $buf      = '';
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $line = @fgets($sock, 4096);
            if ($line === false) {
                if (feof($sock)) {
                    break;
                }
                continue;
            }
            $buf .= $line;
            if (str_contains($buf, 'EventList: Complete')) {
                break;
            }
        }
        return $buf;
    }

    /**
     * Parsea una respuesta cruda AMI en bloques (array de arrays clave=>valor).
     *
     * @return array<int,array<string,string>>
     */
    public static function parseEvents(string $raw): array
    {
        $events = [];
        $block  = [];
        foreach (explode("\r\n", $raw) as $line) {
            if ($line === '') {
                if (!empty($block)) {
                    $events[] = $block;
                    $block    = [];
                }
            } elseif (str_contains($line, ': ')) {
                [$key, $val] = explode(': ', $line, 2);
                $block[$key] = $val;
            }
        }
        if (!empty($block)) {
            $events[] = $block;
        }
        return $events;
    }
}
