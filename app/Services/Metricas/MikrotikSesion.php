<?php

namespace App\Services\Metricas;

use App\Services\Metricas\RouterOs\RouterOsApi;
use Throwable;

/**
 * Lecturas de un MikroTik para el monitoreo de dedicados (item #988). Solo comandos de
 * lectura: /system/identity/print, /interface/print, /interface/vlan/print y /ping.
 */
final class MikrotikSesion implements SesionRouter
{
    private const PROPS_INTERFAZ = ['name', 'rx-byte', 'tx-byte', 'rx-packet', 'tx-packet', 'running', 'disabled'];

    /** @var array<string, array{por: ?string, nombres: string[]}> */
    private array $resolucion = [];

    public function __construct(
        private readonly RouterOsApi $api,
        private readonly string $host,
        private readonly string $usuario,
        private readonly bool $soloLectura,
        private readonly bool $redirigido = false,
    ) {
    }

    public function redirigido(): bool
    {
        return $this->redirigido;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function usuario(): string
    {
        return $this->usuario;
    }

    public function soloLectura(): bool
    {
        return $this->soloLectura;
    }

    public function identidad(): string
    {
        $filas = $this->api->leer('/system/identity/print', [], ['name']);

        return trim((string) ($filas[0]['name'] ?? ''));
    }

    public function interfaces(array $pedidas): array
    {
        // 1) Por nombre, todas en una sola lectura (el nombre se usa tal cual, con espacios).
        $porNombre = array_filter(array_map(fn ($p) => $p['interfaz'] ?? null, $pedidas), fn ($n) => trim((string) $n) !== '');
        $contadores = $this->contadores(array_values(array_unique($porNombre)));

        $resultado = [];
        $faltan = [];
        foreach ($pedidas as $clave => $p) {
            $nombre = $porNombre[$clave] ?? null;
            if ($nombre !== null && isset($contadores[$nombre])) {
                $resultado[$clave] = $contadores[$nombre];
                $this->resolucion[$clave] = ['por' => 'nombre', 'nombres' => [$nombre]];
            } elseif (! empty($p['vlan_id'])) {
                $faltan[$clave] = (int) $p['vlan_id'];
            } else {
                $resultado[$clave] = null;
                $this->resolucion[$clave] = ['por' => null, 'nombres' => []];
            }
        }

        // 2) Sin nombre, o con un nombre que el router ya no tiene: por VLAN ID. Si ese VLAN ID
        // está en más de una interfaz no se adivina cuál es.
        if ($faltan !== []) {
            $vlans = $this->api->leer('/interface/vlan/print', [], ['name', 'vlan-id'],
                array_map(fn ($id) => ['vlan-id', (string) $id], array_values(array_unique($faltan))));
            $nombresPorVlan = [];
            foreach ($vlans as $v) {
                $nombresPorVlan[(int) $v['vlan-id']][] = (string) $v['name'];
            }
            $resueltos = [];
            foreach ($faltan as $clave => $vlanId) {
                $nombres = $nombresPorVlan[$vlanId] ?? [];
                $resueltos[$clave] = count($nombres) === 1 ? $nombres[0] : null;
                $this->resolucion[$clave] = ['por' => 'vlan_id', 'nombres' => $nombres];
            }
            $extra = $this->contadores(array_values(array_unique(array_filter($resueltos))));
            foreach ($resueltos as $clave => $nombre) {
                $resultado[$clave] = $nombre !== null ? ($extra[$nombre] ?? null) : null;
            }
        }

        return $resultado;
    }

    public function resolucion(): array
    {
        return $this->resolucion;
    }

    public function ping(string $ip, int $paquetes, string $intervalo): LecturaPing
    {
        try {
            $filas = $this->api->leer('/ping', ['address' => $ip, 'count' => (string) $paquetes, 'interval' => $intervalo],
                ['seq', 'time', 'status', 'sent', 'received']);
        } catch (Throwable $e) {
            return LecturaPing::conError($paquetes, $e->getMessage());
        }

        return LecturaPing::desdeRespuestas($filas, $paquetes);
    }

    public function tasasEnVivo(array $vlanIds): array
    {
        if ($vlanIds === []) {
            return [];
        }
        $vlans = $this->api->leer('/interface/vlan/print', [], ['name', 'vlan-id', 'interface'],
            array_map(fn ($id) => ['vlan-id', (string) $id], array_values(array_unique($vlanIds))));
        if ($vlans === []) {
            return [];
        }

        $tasas = [];
        foreach ($this->api->leer('/interface/monitor-traffic', ['interface' => implode(',', array_column($vlans, 'name')), 'once' => ''],
            ['name', 'rx-bits-per-second', 'tx-bits-per-second', 'rx-packets-per-second', 'tx-packets-per-second']) as $t) {
            $tasas[(string) $t['name']] = $t;
        }

        return array_map(fn ($v) => [
            'vlan_id' => (int) $v['vlan-id'],
            'nombre'  => (string) $v['name'],
            'padre'   => $v['interface'] ?? null,
            'rx_bps'  => self::enteroONulo($tasas[$v['name']]['rx-bits-per-second'] ?? null),
            'tx_bps'  => self::enteroONulo($tasas[$v['name']]['tx-bits-per-second'] ?? null),
            'rx_pps'  => self::enteroONulo($tasas[$v['name']]['rx-packets-per-second'] ?? null),
            'tx_pps'  => self::enteroONulo($tasas[$v['name']]['tx-packets-per-second'] ?? null),
        ], $vlans);
    }

    public function cerrar(): void
    {
        $this->api->cerrar();
    }

    /** @return array<string, LecturaInterfaz> nombre => lectura */
    private function contadores(array $nombres): array
    {
        if ($nombres === []) {
            return [];
        }
        $filas = $this->api->leer('/interface/print', [], self::PROPS_INTERFAZ, array_map(fn ($n) => ['name', $n], $nombres));
        $leidoEn = microtime(true);

        $lecturas = [];
        foreach ($filas as $f) {
            $lecturas[(string) $f['name']] = new LecturaInterfaz(
                rxByte: self::entero($f['rx-byte'] ?? null),
                txByte: self::entero($f['tx-byte'] ?? null),
                rxPacket: self::entero($f['rx-packet'] ?? null),
                txPacket: self::entero($f['tx-packet'] ?? null),
                running: ($f['running'] ?? 'false') === 'true' && ($f['disabled'] ?? 'false') !== 'true',
                leidoEn: $leidoEn,
            );
        }

        return $lecturas;
    }

    private static function entero(?string $v): int
    {
        return ctype_digit((string) $v) ? (int) $v : 0;
    }

    private static function enteroONulo(?string $v): ?int
    {
        return ctype_digit((string) $v) ? (int) $v : null;
    }
}
