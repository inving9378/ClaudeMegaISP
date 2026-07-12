<?php

namespace App\Modules\Addons\Flotas\Console;

use App\Modules\Addons\Flotas\Models\FleetDevice;
use App\Modules\Addons\Flotas\Services\FleetPositionService;
use App\Modules\Addons\Flotas\Services\Gps\DeviceFactory;
use App\Modules\Addons\Flotas\Services\Gps\Drivers\RuptelaDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sub-fase 2.3a — Listener TCP para dispositivos GPS (Ruptela hoy; Concox/GT06 a futuro).
 *
 * TCP nativo (stream_socket_server), sin ReactPHP/Ratchet (regla 3).
 * Flujo por conexión: handshake → DeviceFactory detecta marca → parse →
 * resuelve device/vehículo por IMEI → FleetPositionService::saveBatch (que ya
 * dispara detección de geocercas 3.2 y notificaciones 3.3, intactas) → ACK.
 *
 * NO se ejecuta en automático (puerto 5027 cerrado). Se activa vía systemd
 * cuando Irving apunte el GPS real (ver deploy/README-gps-real.md).
 */
class GpsListenCommand extends Command
{
    protected $signature = 'flotas:gps-listen {--host=0.0.0.0} {--port=5027} {--read-timeout=60}';
    protected $description = 'TCP listener para dispositivos GPS Ruptela/Concox/GT06';

    private const LOG_CHANNEL = 'gps-listener';

    public function handle(FleetPositionService $positions): int
    {
        $host = (string) $this->option('host');
        $port = (int) $this->option('port');
        $timeout = (int) $this->option('read-timeout');

        $server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (!$server) {
            $this->error("No se pudo abrir el socket tcp://{$host}:{$port} — {$errstr} ({$errno})");
            return self::FAILURE;
        }

        $this->info("📡 GPS listener escuchando en tcp://{$host}:{$port} (timeout lectura {$timeout}s). Ctrl+C para detener.");
        $this->log("Listener iniciado en {$host}:{$port}");

        while (true) {
            $conn = @stream_socket_accept($server, -1, $peer);
            if (!$conn) {
                continue;
            }
            // Conexión secuencial (un dispositivo a la vez). Para alta concurrencia,
            // futuro: pcntl_fork o stream_select multiplexado.
            try {
                $this->handleConnection($conn, $peer, $timeout, $positions);
            } catch (\Throwable $e) {
                $this->log("ERROR conexión {$peer}: " . $e->getMessage(), 'error');
            } finally {
                @fclose($conn);
            }
        }

        return self::SUCCESS; // inalcanzable salvo señal
    }

    private function handleConnection($conn, string $peer, int $timeout, FleetPositionService $positions): void
    {
        $ip = $this->maskIp($peer);
        stream_set_timeout($conn, $timeout);

        // Primer frame (handshake): identifica la marca.
        $data = $this->readFrame($conn);
        if ($data === '' || $data === false) {
            $this->log("Conexión {$ip}: sin datos (timeout/cierre).");
            return;
        }

        $driver = DeviceFactory::fromHandshake($data);
        if (!$driver) {
            $this->log("Conexión {$ip}: marca NO reconocida (" . strlen($data) . " bytes). Cerrando.", 'warning');
            return;
        }
        $this->log("Conexión {$ip}: marca={$driver->name()}");

        // Loop: procesa el frame inicial y los subsecuentes mientras la conexión viva.
        do {
            $this->processFrame($driver, $data, $conn, $ip, $positions);
            $data = $this->readFrame($conn);
        } while ($data !== '' && $data !== false);

        $this->log("Conexión {$ip}: cerrada.");
    }

    private function processFrame($driver, string $frame, $conn, string $ip, FleetPositionService $positions): void
    {
        $parsed = $driver->parse($frame);

        // IMEI (aunque el frame no traiga fixes válidos, para registrar el device).
        $imei = $parsed[0]->imei
            ?? ($driver instanceof RuptelaDriver ? $driver->extractImei($frame) : null);

        if (!$imei) {
            $this->log("{$ip}: frame sin IMEI resoluble (" . strlen($frame) . " bytes).", 'warning');
            return;
        }
        $crcOk = !($driver instanceof RuptelaDriver) || $driver->lastCrcOk;
        $crcNote = $crcOk ? '' : ' (CRC no coincide ⚠️)';
        $this->log("{$ip}: imei={$this->maskImei($imei)} records=" . count($parsed) . $crcNote);

        // #283 — CRC estricto: frame corrupto/manipulado se descarta ANTES de tocar
        // el device o persistir posiciones. Flag por env, default ON.
        if (!$crcOk && config('gps.strict_crc', true)) {
            $this->log("{$ip}: CRC inválido para imei={$this->maskImei($imei)} — frame descartado (gps.strict_crc).", 'warning');
            return;
        }

        $device = FleetDevice::where('imei', $imei)->first();

        // Device desconocido → el alta legítima de un GPS físico SIEMPRE pasa antes
        // por la UI (FleetGpsController::activateDevice), que ya vincula el device a
        // un vehículo. Un IMEI no dado de alta no es un caso de uso normal: #283
        // gatea el auto-registro (default OFF) y, si se habilita, lo limita por IP.
        if (!$device) {
            if (!config('gps.auto_register_unknown_imei', false)) {
                $this->log("{$ip}: IMEI {$this->maskImei($imei)} desconocido — auto-registro deshabilitado (gps.auto_register_unknown_imei). Descartado.", 'warning');
                return;
            }

            $rateKey = 'gps-new-imei:' . $ip;
            $perHour = (int) config('gps.imei_new_per_ip_per_hour', 3);
            if (RateLimiter::tooManyAttempts($rateKey, $perHour)) {
                $this->log("{$ip}: límite de IMEIs nuevos por hora alcanzado ({$perHour}). IMEI {$this->maskImei($imei)} descartado.", 'warning');
                return;
            }
            RateLimiter::hit($rateKey, 3600);

            $device = FleetDevice::create([
                'imei'         => $imei,
                'brand'        => $driver->name(),
                'status'       => 'unregistered',
                'last_seen_at' => now(),
                'notes'        => "Auto-registrado por el listener desde {$ip}. Vincular a un vehículo en la UI.",
            ]);
            $this->log("{$ip}: IMEI {$this->maskImei($imei)} NO vinculado a vehículo → device #{$device->id} status=unregistered. Sin guardar posiciones.", 'warning');
            $this->ack($driver, $conn, count($parsed));
            return;
        }

        // Device sin vehículo asignado → no se pueden guardar posiciones (fleet_positions.vehicle_id NOT NULL).
        if (!$device->vehicle_id) {
            $device->update(['last_seen_at' => now(), 'status' => 'unregistered']);
            $this->log("{$ip}: device #{$device->id} sin vehicle_id. ACK pero sin persistir.", 'warning');
            $this->ack($driver, $conn, count($parsed));
            return;
        }

        if (!empty($parsed)) {
            $saved = $positions->saveBatch($parsed, $device->vehicle_id, $device->id);
            // Primera conexión real → pasa a 'active'.
            if ($device->status !== 'active') {
                $device->update(['status' => 'active']);
            }
            $this->log("{$ip}: device #{$device->id} vehículo #{$device->vehicle_id} → {$saved} posiciones guardadas.");
        } else {
            $device->update(['last_seen_at' => now()]);
            $this->log("{$ip}: device #{$device->id} frame sin fixes válidos (0 posiciones).");
        }

        $this->ack($driver, $conn, count($parsed));
    }

    private function ack($driver, $conn, int $recordsCount): void
    {
        if ($driver instanceof RuptelaDriver) {
            @fwrite($conn, $driver->buildAck($recordsCount));
        }
    }

    /**
     * Lee un frame Ruptela completo: [len:2][...len bytes...][crc:2].
     * Lee el header de longitud y luego el resto. Devuelve '' en timeout/cierre.
     */
    private function readFrame($conn): string|false
    {
        $header = $this->readExactly($conn, 2);
        if (strlen($header) < 2) {
            return $header === '' ? '' : false;
        }
        $len = unpack('n', $header)[1];
        if ($len < 1 || $len > 100000) {
            return false; // longitud inverosímil → frame corrupto
        }
        $rest = $this->readExactly($conn, $len + 2); // payload + CRC
        return $header . $rest;
    }

    private function readExactly($conn, int $n): string
    {
        $buf = '';
        while (strlen($buf) < $n) {
            $chunk = @fread($conn, $n - strlen($buf));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($conn);
                if (!empty($meta['timed_out'])) {
                    break;
                }
                break; // cierre
            }
            $buf .= $chunk;
        }
        return $buf;
    }

    // ── Logging (archivo dedicado, IMEI/IP enmascarados — regla 4) ──────────────

    private function log(string $message, string $level = 'info'): void
    {
        $line = '[' . now()->format('Y-m-d H:i:s') . "] {$message}";
        $this->line($line);
        try {
            Log::build([
                'driver' => 'single',
                'path'   => storage_path('logs/gps-listener.log'),
            ])->{$level}($message);
        } catch (\Throwable $e) {
            Log::{$level}('[gps-listener] ' . $message);
        }
    }

    private function maskImei(string $imei): string
    {
        return strlen($imei) > 6 ? substr($imei, 0, 4) . str_repeat('*', strlen($imei) - 6) . substr($imei, -2) : $imei;
    }

    private function maskIp(string $peer): string
    {
        $ip = explode(':', $peer)[0] ?? $peer;
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return "{$parts[0]}.{$parts[1]}.*.{$parts[3]}";
        }
        return $ip;
    }
}
