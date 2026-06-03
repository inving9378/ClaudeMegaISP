<?php

namespace App\Modules\Addons\Flotas\Console;

use App\Modules\Addons\Flotas\Services\Gps\Drivers\RuptelaDriver;
use Illuminate\Console\Command;

/**
 * Sub-fase 2.3a — Simula un dispositivo Ruptela conectándose al listener TCP.
 *
 * Construye frames binarios Ruptela VÁLIDOS (mismo layout y CRC que RuptelaDriver,
 * coords de CDMX — regla 5) y los envía a flotas:gps-listen para validar el
 * pipeline completo (listener → parse → saveBatch → geocercas → notif) SIN GPS físico.
 *
 * Coexiste con flotas:simulate-gps (que escribe directo en BD); este habla TCP.
 */
class SimulateRuptelaCommand extends Command
{
    protected $signature = 'flotas:simulate-ruptela {imei}
                            {--host=127.0.0.1}
                            {--port=5027}
                            {--count=3 : Número de records en el frame}
                            {--lat=19.4326}
                            {--lng=-99.1332}';

    protected $description = 'Simula un dispositivo Ruptela enviando packets binarios al listener TCP';

    private const COORD_MULT = 10000000;

    public function handle(): int
    {
        $imei  = (string) $this->argument('imei');
        $host  = (string) $this->option('host');
        $port  = (int) $this->option('port');
        $count = max(1, (int) $this->option('count'));
        $lat   = (float) $this->option('lat');
        $lng   = (float) $this->option('lng');

        $frame = $this->buildFrame($imei, $count, $lat, $lng);
        $this->info("Frame Ruptela construido: " . strlen($frame) . " bytes, {$count} record(s), imei={$imei}");
        $this->line("  HEX (primeros 48): " . strtoupper(substr(bin2hex($frame), 0, 48)) . '…');

        $conn = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 8);
        if (!$conn) {
            $this->error("No se pudo conectar a tcp://{$host}:{$port} — {$errstr} ({$errno}). ¿Está corriendo flotas:gps-listen?");
            return self::FAILURE;
        }

        fwrite($conn, $frame);
        $this->info("✅ Frame enviado a {$host}:{$port}.");

        stream_set_timeout($conn, 5);
        $ack = fread($conn, 16);
        fclose($conn);

        if ($ack === '' || $ack === false) {
            $this->warn("⚠️ Sin ACK del listener (¿timeout?).");
        } else {
            $this->info("📥 ACK recibido: " . strtoupper(bin2hex($ack)) . " (" . strlen($ack) . " bytes)");
        }

        $this->line('');
        $this->line('Revisa: storage/logs/gps-listener.log y fleet_positions / fleet_geofence_events.');
        return self::SUCCESS;
    }

    /** Construye un frame Ruptela command 0x01 con N records alrededor de (lat,lng). */
    private function buildFrame(string $imei, int $count, float $lat, float $lng): string
    {
        $records = '';
        $base = now()->subSeconds($count * 30);
        for ($i = 0; $i < $count; $i++) {
            $records .= $this->buildRecord(
                timestamp: $base->copy()->addSeconds($i * 30)->timestamp,
                lat: $lat + $i * 0.0008,   // ligero movimiento norte
                lng: $lng + $i * 0.0008,   // y este
                speed: 30 + $i * 5,
                heading: 45.0,
            );
        }

        // payload = IMEI(8) + cmd(1) + recordsLeft(1) + recordsCount(1) + records
        $payload = pack('J', (int) $imei)      // IMEI uint64 BE
            . chr(RuptelaDriver::COMMAND_RECORDS)
            . chr(0x00)                         // records left
            . chr($count)
            . $records;

        $crc = app(RuptelaDriver::class)->calculateCrc16($payload);

        return pack('n', strlen($payload)) . $payload . pack('n', $crc);
    }

    private function buildRecord(int $timestamp, float $lat, float $lng, int $speed, float $heading): string
    {
        return pack('N', $timestamp)              // ts
            . chr(0x00)                            // ts extension
            . chr(0x00)                            // priority
            . pack('N', $this->s32($lng * self::COORD_MULT)) // lng signed
            . pack('N', $this->s32($lat * self::COORD_MULT)) // lat signed
            . pack('n', 2240)                      // altitude (m)
            . pack('n', (int) round($heading * 100)) // angle *100
            . chr(8)                               // satellites
            . pack('n', $speed)                    // speed km/h
            // sección IO vacía: eventId + 4 grupos en cero
            . chr(0x00) . chr(0x00) . chr(0x00) . chr(0x00) . chr(0x00);
    }

    /** int → representación uint32 para pack('N') con complemento a dos. */
    private function s32(float $val): int
    {
        $v = (int) round($val);
        return $v < 0 ? $v + 0x100000000 : $v;
    }
}
