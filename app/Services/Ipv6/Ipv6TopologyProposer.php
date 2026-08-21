<?php

namespace App\Services\Ipv6;

/**
 * Lógica PURA (sin I/O) de auto-descubrimiento de topología IPv6 sobre objetos
 * MikroTik ya leídos. Filtra por el marcador MgNet-IPv6 (alcance restringido
 * del item #951: nunca WAN/rutas por defecto/IPv4/bridge/VLAN sin marcar) y
 * detecta qué ya tiene IPv6 asignado para no pisarlo. El cálculo numérico del
 * /64 (cuál prefijo le toca a cada candidato) es responsabilidad de la
 * calculadora de #950 (todavía sin ejecutar) — aquí solo se identifica QUÉ
 * necesita prefijo, no CUÁL.
 */
class Ipv6TopologyProposer
{
    public const DEFAULT_MARKER = 'MgNet-IPv6';

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public static function filterByMarker(array $items, string $marker = self::DEFAULT_MARKER, string $field = 'comment'): array
    {
        return array_values(array_filter($items, function (array $item) use ($marker, $field) {
            return isset($item[$field]) && str_contains((string) $item[$field], $marker);
        }));
    }

    /**
     * Indexa /ipv6 address print por interfaz para poder responder
     * "¿esta interfaz ya tiene un /64 asignado?" sin pisarlo.
     *
     * @param array<int, array<string, mixed>> $assignedIpv6
     * @return array<string, array<int, string>>
     */
    public static function indexAssignedByInterface(array $assignedIpv6): array
    {
        $index = [];
        foreach ($assignedIpv6 as $row) {
            $interface = $row['interface'] ?? null;
            $address = $row['address'] ?? null;
            if (!$interface || !$address) {
                continue;
            }
            $index[$interface][] = $address;
        }
        return $index;
    }

    /**
     * VLANs marcadas -> candidatas a un /64 de infra.
     *
     * @param array<int, array<string, mixed>> $vlans
     * @param array<int, array<string, mixed>> $assignedIpv6
     * @return array<int, array<string, mixed>>
     */
    public static function candidatesFromVlans(array $vlans, array $assignedIpv6, string $marker = self::DEFAULT_MARKER): array
    {
        $index = self::indexAssignedByInterface($assignedIpv6);

        return array_map(function (array $vlan) use ($index) {
            $interface = $vlan['name'] ?? null;
            $existentes = $interface ? ($index[$interface] ?? []) : [];
            return [
                'interface' => $interface,
                'vlan_id' => $vlan['vlan-id'] ?? null,
                'comment' => $vlan['comment'] ?? null,
                'ya_asignado' => count($existentes) > 0,
                'direcciones_existentes' => $existentes,
                'proposed_prefix' => null,
            ];
        }, self::filterByMarker($vlans, $marker));
    }

    /**
     * Perfiles PPPoE marcados -> candidatos a zona de delegación (sin numérica todavía).
     *
     * @param array<int, array<string, mixed>> $profiles
     * @return array<int, array<string, mixed>>
     */
    public static function candidatesFromPppProfiles(array $profiles, string $marker = self::DEFAULT_MARKER): array
    {
        return array_map(function (array $profile) {
            return [
                'profile' => $profile['name'] ?? null,
                'comment' => $profile['comment'] ?? null,
                'proposed_delegation_zone' => null,
            ];
        }, self::filterByMarker($profiles, $marker));
    }

    /**
     * Agrupa servidores PPPoE por "distrito": no existe un campo distrito en
     * /interface pppoe-server server print, así que se agrupa por
     * service-name (la única llave de agrupación natural disponible).
     *
     * @param array<int, array<string, mixed>> $servers
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function groupPppoeServersByDistrict(array $servers): array
    {
        $groups = [];
        foreach ($servers as $server) {
            $district = $server['service-name'] ?? 'sin-service-name';
            $groups[$district][] = $server;
        }
        return $groups;
    }

    /**
     * Queues dedicadas marcadas -> candidatas a necesitar prefijo IPv6 en el target.
     *
     * @param array<int, array<string, mixed>> $queues
     * @return array<int, array<string, mixed>>
     */
    public static function candidatesFromDedicatedQueues(array $queues, string $marker = self::DEFAULT_MARKER): array
    {
        return array_map(function (array $queue) {
            return [
                'name' => $queue['name'] ?? null,
                'target' => $queue['target'] ?? null,
                'comment' => $queue['comment'] ?? null,
            ];
        }, self::filterByMarker($queues, $marker));
    }
}
