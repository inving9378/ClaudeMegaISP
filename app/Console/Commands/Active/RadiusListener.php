<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use App\Models\RadiusSession; // Asegúrate de que este modelo exista
use Illuminate\Support\Facades\Log;

class RadiusListener extends Command
{
    protected $signature = 'radius:listen';
    protected $description = 'Servidor RADIUS (Auth + Accounting)';

    private $secret = "meganet123";
    private $portAuth = 1812; // Puerto para dejar entrar al cliente
    private $portAcct = 1813; // Puerto para el consumo de datos

    public function handle()
    {
        // Creamos dos sockets: uno para Auth y otro para Acct
        $sockAuth = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $sockAcct = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        socket_bind($sockAuth, '0.0.0.0', $this->portAuth);
        socket_bind($sockAcct, '0.0.0.0', $this->portAcct);

        // Hacemos que los sockets no se bloqueen para poder escuchar ambos en un bucle
        socket_set_nonblock($sockAuth);
        socket_set_nonblock($sockAcct);

        $this->info("Servidor RADIUS Dual activo (1812/1813)...");

        while (true) {
            $buf = ''; $from = ''; $port = 0;

            // --- Lógica de AUTENTICACIÓN (1812) ---
            if (socket_recvfrom($sockAuth, $buf, 4096, 0, $from, $port) > 0) {
                $identifier = ord($buf[1]);
                $requestAuth = substr($buf, 4, 16);

                // Enviar "Access-Accept" (Código 2) para dejarlo conectar siempre
                $response = pack("CCn", 2, $identifier, 20); // Código 2 = Aceptar
                $md5 = md5($response . $requestAuth . $this->secret, true);
                socket_sendto($sockAuth, $response . $md5, 20, 0, $from, $port);
                $this->warn("Aceptando conexión de: " . $from);
            }

            // --- Lógica de ACCOUNTING (1813) ---
            if (socket_recvfrom($sockAcct, $buf, 4096, 0, $from, $port) > 0) {
                $code = ord($buf[0]);
                if ($code === 4) { // Accounting-Request
                    $identifier = ord($buf[1]);
                    $requestAuth = substr($buf, 4, 16);
                    $attributes = $this->parseAttributes(substr($buf, 20));

                    $this->processAccounting($attributes, $from);

                    // Enviar Respuesta (Código 5)
                    $resHeader = pack("CCn", 5, $identifier, 20);
                    $md5 = md5($resHeader . $requestAuth . $this->secret, true);
                    socket_sendto($sockAcct, $resHeader . $md5, 20, 0, $from, $port);
                    $this->info("¡Consumo recibido de: " . ($attributes['username'] ?? 'anonimo') . "!");
                }
            }

            usleep(5000); // Pequeño descanso para no saturar la CPU
        }
    }

    private function parseAttributes($data)
    {
        $attrs = [];
        $offset = 0;
        $len = strlen($data);

        while ($offset < $len) {
            $type = ord($data[$offset]);
            $length = ord($data[$offset + 1]);
            $value = substr($data, $offset + 2, $length - 2);

            switch ($type) {
                case 1:  $attrs['username'] = $value; break;
                case 40: $attrs['status'] = unpack("N", $value)[1]; break; // 1=Start, 2=Stop, 3=Interim
                case 44: $attrs['session_id'] = $value; break;
                case 42: $attrs['input_octets'] = unpack("N", $value)[1]; break;
                case 43: $attrs['output_octets'] = unpack("N", $value)[1]; break;
                case 52: $attrs['input_gigawords'] = unpack("N", $value)[1]; break;
                case 53: $attrs['output_gigawords'] = unpack("N", $value)[1]; break;
                case 45: $attrs['session_time'] = unpack("N", $value)[1]; break;
                case 8:  $attrs['ip'] = inet_ntop($value); break;
            }
            $offset += $length;
        }
        return $attrs;
    }

    private function processAccounting($attrs, $nasIp)
    {
        // Cálculo de consumo real (32bit Octets + 64bit Gigawords)
        // 4294967296 = 4GB (2^32)
        $upload = ($attrs['input_octets'] ?? 0) + (($attrs['input_gigawords'] ?? 0) * 4294967296);
        $download = ($attrs['output_octets'] ?? 0) + (($attrs['output_gigawords'] ?? 0) * 4294967296);

        $statusMap = [1 => 'start', 2 => 'stop', 3 => 'interim'];
        $statusText = $statusMap[$attrs['status']] ?? 'unknown';

        // --- BLOQUE DE INFO PARA DEBUG ---
        $mbIn = round($upload / 1024 / 1024, 2);
        $mbOut = round($download / 1024 / 1024, 2);
        $uptime = isset($attrs['session_time']) ? gmdate("H:i:s", $attrs['session_time']) : '00:00:00';

        $this->info("=========================================");
        $this->info(" EVENTO: " . strtoupper($statusText));
        $this->info(" CLIENTE: " . ($attrs['username'] ?? 'unknown'));
        $this->info(" SUBIDA (TX): " . ($mbIn > 1024 ? round($mbIn/1024, 2)." GB" : $mbIn." MB"));
        $this->info(" BAJADA (RX): " . ($mbOut > 1024 ? round($mbOut/1024, 2)." GB" : $mbOut." MB"));
        $this->info(" UPTIME: " . $uptime);
        $this->info(" IP NAS: " . $nasIp);
        $this->info(" ID SESIÓN: " . ($attrs['session_id'] ?? 'N/A'));
        $this->info("=========================================");

//        $session = RadiusSession::updateOrCreate(
//            ['session_id' => $attrs['session_id']],
//            [
//                'username'     => $attrs['username'] ?? 'unknown',
//                'nas_ip'       => $nasIp,
//                'framed_ip'    => $attrs['ip'] ?? '',
//                'bytes_in'     => $upload,
//                'bytes_out'    => $download,
//                'session_time' => $attrs['session_time'] ?? 0,
//                'status'       => $statusText,
//                'update_time'  => now()
//            ]
//        );
//
//        if ($statusText === 'start' && !$session->start_time) {
//            $session->update(['start_time' => now()]);
//        }
//        if ($statusText === 'stop') {
//            $session->update(['stop_time' => now()]);
//        }
    }

    private function assembleResponse($identifier, $requestAuthenticator)
    {
        // Cabecera: Code(5=Response), ID, Length(20)
        $header = pack("CCn", 5, $identifier, 20);

        // El Authenticator de respuesta es MD5(Code + ID + Length + RequestAuth + Attributes + Secret)
        // Como no enviamos atributos en el ACK,Attributes está vacío.
        $md5 = md5($header . $requestAuthenticator . $this->secret, true);

        return $header . $md5;
    }
}
