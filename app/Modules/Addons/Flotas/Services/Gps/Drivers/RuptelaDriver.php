<?php

namespace App\Modules\Addons\Flotas\Services\Gps\Drivers;

use App\Modules\Addons\Flotas\Services\Gps\GpsDriverInterface;
use App\Modules\Addons\Flotas\Services\Gps\Position;
use Carbon\Carbon;

/**
 * Sub-fase 2.3a — Driver del protocolo Ruptela (records, command 0x01).
 *
 * ⚠️ IMPLEMENTACIÓN MÍNIMA VIABLE basada en la estructura pública/común del
 * protocolo Ruptela. La documentación oficial (doc.ruptela.com) requiere
 * registro; esto se construyó self-consistent con el simulador
 * (flotas:simulate-ruptela) para validar el pipeline TCP completo SIN hardware.
 *
 * SUPUESTOS A VERIFICAR CON GPS REAL (marcados ⚠️VERIFY a lo largo del código):
 *   - CRC16: se asume CCITT (poly 0x1021, init 0x0000) sobre [IMEI..fin payload].
 *           Por seguridad el parseo es TOLERANTE: si el CRC no coincide NO se
 *           descarta el frame (solo se loguea), para no perder datos reales si
 *           el algoritmo difiere. Endurecer (rechazar) una vez verificado.
 *   - IMEI: 8 bytes big-endian uint64 → decimal. (Algunas fuentes citan BCD.)
 *   - Layout de cada record y sección IO (ver parseRecord()).
 *   - Longitud: uint16 BE = nº de bytes entre el campo length y el CRC.
 *
 * Frame:  [len:2][IMEI:8][cmd:1][recordsLeft:1][recordsCount:1][records...][crc:2]
 */
class RuptelaDriver implements GpsDriverInterface
{
    public const COMMAND_RECORDS     = 0x01; // records básicos
    public const COMMAND_EXT_RECORDS = 0x44; // extended (no implementado: ⚠️VERIFY)
    private const COORD_DIVISOR = 10000000;  // 10^7 → grados decimales

    /** Último CRC observado (para diagnóstico/logging del listener). */
    public bool $lastCrcOk = false;

    public function name(): string
    {
        return 'ruptela';
    }

    /**
     * Heurística de detección: ≥13 bytes, length declarada coherente con el
     * tamaño recibido y un command conocido en el offset esperado.
     */
    public function supports(string $handshake): bool
    {
        return $this->isValidRuptelaPacket($handshake);
    }

    /**
     * @return Position[]
     */
    public function parse(string $binaryData): array
    {
        if (!$this->isValidRuptelaPacket($binaryData)) {
            return [];
        }

        $len  = $this->u16($binaryData, 0);                 // bytes entre length y CRC
        $imei = $this->decodeImei(substr($binaryData, 2, 8));
        $cmd  = ord($binaryData[10]);

        // Validación de CRC (tolerante: ver docblock).
        $payload = substr($binaryData, 2, $len);            // IMEI..fin payload
        $crcRecv = $this->u16($binaryData, 2 + $len);
        $this->lastCrcOk = ($this->calculateCrc16($payload) === $crcRecv);

        if ($cmd !== self::COMMAND_RECORDS) {
            // ⚠️VERIFY: extended records (0x44/0x68) no implementado en MVP.
            return [];
        }

        $count = ord($binaryData[12]);                      // offset 11 = recordsLeft, 12 = recordsCount
        $pos = 13;
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $record = $this->parseRecord($binaryData, $pos, $imei); // mueve $pos por referencia
            if ($record === null) {
                break; // frame truncado/corrupto: detener
            }
            // Descarta fixes inválidos (sin señal: lat/lng 0 o satélites < 3).
            if ($record->satellites !== null && $record->satellites < 3) {
                continue;
            }
            if (abs($record->lat) < 0.000001 && abs($record->lng) < 0.000001) {
                continue;
            }
            $out[] = $record;
        }

        return $out;
    }

    /**
     * Parsea un record a partir de $pos (lo avanza). Devuelve null si no hay
     * suficientes bytes.
     *
     * Layout (⚠️VERIFY con GPS real):
     *   ts:4 tsExt:1 prio:1 lng:4(s) lat:4(s) alt:2 angle:2 sat:1 speed:2
     *   IO: eventId:1, luego 4 grupos (1B,2B,4B,8B): cada uno [count:1] + count*(id:1 + value:N)
     */
    private function parseRecord(string $data, int &$pos, string $imei): ?Position
    {
        if (strlen($data) < $pos + 21) {
            return null;
        }

        $ts     = $this->u32($data, $pos);        $pos += 4;
        $pos += 1;                                 // timestamp extension (ignorado)
        $pos += 1;                                 // priority (ignorado)
        $lng    = $this->s32($data, $pos) / self::COORD_DIVISOR; $pos += 4;
        $lat    = $this->s32($data, $pos) / self::COORD_DIVISOR; $pos += 4;
        $alt    = $this->u16($data, $pos);         $pos += 2; // metros (⚠️VERIFY si es 0.1m)
        $angle  = $this->u16($data, $pos) / 100.0; $pos += 2; // grados (⚠️VERIFY divisor)
        $sat    = ord($data[$pos]);                $pos += 1;
        $speed  = $this->u16($data, $pos);         $pos += 2; // km/h

        // Sección IO — se recorre para avanzar el puntero correctamente.
        if (!$this->skipIoSection($data, $pos)) {
            return null;
        }

        return new Position(
            imei: $imei,
            lat: $lat,
            lng: $lng,
            speed: (float) $speed,
            heading: (float) $angle,
            altitude: (float) $alt,
            satellites: $sat,
            recorded_at: Carbon::createFromTimestampUTC($ts),
        );
    }

    /** Avanza $pos por la sección IO (eventId + 4 grupos por tamaño). false si truncado. */
    private function skipIoSection(string $data, int &$pos): bool
    {
        if (strlen($data) < $pos + 5) {
            return false;
        }
        $pos += 1; // event IO id

        foreach ([1, 2, 4, 8] as $valueSize) {
            $groupCount = ord($data[$pos]); $pos += 1;
            $need = $groupCount * (1 + $valueSize);
            if (strlen($data) < $pos + $need) {
                return false;
            }
            $pos += $need; // ⚠️VERIFY: no se extraen IOs (ignition/battery) en el MVP
        }
        return true;
    }

    /**
     * ACK que el dispositivo espera tras un paquete de records.
     * Formato Ruptela documentado: [len:2 = 0x0002][0x64][ack:1].  ack=0x01 = aceptado.
     * ⚠️VERIFY: algunas versiones esperan el CRC del ack; el simulador acepta este formato.
     */
    public function buildAck(int $recordsCount = 1): string
    {
        $accepted = $recordsCount > 0 ? 0x01 : 0x00;
        return pack('n', 0x0002) . chr(0x64) . chr($accepted);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isValidRuptelaPacket(string $data): bool
    {
        if (strlen($data) < 13) {
            return false;
        }
        $len = $this->u16($data, 0);
        // El frame completo debe medir length + 2(len) + 2(crc).
        if (strlen($data) < $len + 4 || $len < 11) {
            return false;
        }
        $cmd = ord($data[10]);
        return in_array($cmd, [self::COMMAND_RECORDS, self::COMMAND_EXT_RECORDS, 0x68], true);
    }

    /** IMEI del frame sin parsear records (útil para registrar el device en la 1ª conexión). */
    public function extractImei(string $frame): ?string
    {
        return $this->isValidRuptelaPacket($frame) ? $this->decodeImei(substr($frame, 2, 8)) : null;
    }

    /** IMEI: 8 bytes big-endian uint64 → string decimal. (⚠️VERIFY: ¿BCD?) */
    private function decodeImei(string $bytes): string
    {
        $bytes = str_pad($bytes, 8, "\x00", STR_PAD_LEFT);
        $parts = unpack('Jval', $bytes);
        return (string) $parts['val'];
    }

    /** CRC16-CCITT (poly 0x1021, init 0x0000). ⚠️VERIFY contra GPS real. */
    public function calculateCrc16(string $data): int
    {
        $crc = 0x0000;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return $crc & 0xFFFF;
    }

    private function u16(string $d, int $o): int { return unpack('n', substr($d, $o, 2))[1]; }
    private function u32(string $d, int $o): int { return unpack('N', substr($d, $o, 4))[1]; }

    private function s32(string $d, int $o): int
    {
        $v = $this->u32($d, $o);
        return $v >= 0x80000000 ? $v - 0x100000000 : $v;
    }
}
