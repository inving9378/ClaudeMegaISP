<?php

namespace App\Services\Ipv6;

use App\Http\Traits\RouterConnection;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;

/**
 * Cliente de lectura/dry-run para el auto-descubrimiento IPv6 (roadmap #951,
 * épica #811). Reusa la conexión ya existente en RouterConnection (mismo
 * patrón que MikrotikService, incluida la protección MIKROTIK_DEV_* fuera de
 * producción) en vez de montar un segundo mecanismo de conexión al MikroTik.
 *
 * Alcance duro de esta fase: SOLO lectura y dry-run. El modo "aplicar"
 * (escribir en el router) NO está implementado aquí a propósito — depende de
 * #949 (módulo, permisos y tabla ipv6_despliegues) y #950 (calculadora de
 * direccionamiento), ninguno ejecutado todavía. Ver decisión registrada en el
 * item #951 de la Hoja de Ruta.
 */
class MikrotikIpv6Client
{
    use RouterConnection;

    public function connect(string $ip, string $login, string $password, $port): ?Client
    {
        return $this->connection($ip, $login, $password, $port);
    }

    /**
     * Detección de versión vía /system resource print (version, board-name,
     * architecture-name) con override manual del operador para la familia.
     *
     * @return array{version: ?string, board_name: ?string, architecture_name: ?string, family: string, family_source: string}
     */
    public function detectVersion($client, ?string $familyOverride = null): array
    {
        $resource = $this->getVersion($client) ?? collect();

        $boardName = $resource['board-name'] ?? null;
        $architectureName = $resource['architecture-name'] ?? null;
        $family = MikrotikBoardFamilyResolver::resolve($boardName, $architectureName, $familyOverride);

        return [
            'version' => $resource['version'] ?? null,
            'board_name' => $boardName,
            'architecture_name' => $architectureName,
            'family' => $family['family'],
            'family_source' => $family['source'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function discoverVlans($client): array
    {
        return $this->fetchPrintRows($client, '/interface/vlan/');
    }

    /** @return array<int, array<string, mixed>> */
    public function discoverPppProfiles($client): array
    {
        return $this->fetchPrintRows($client, '/ppp/profile/');
    }

    /** @return array<int, array<string, mixed>> */
    public function discoverPppoeServers($client): array
    {
        return $this->fetchPrintRows($client, '/interface/pppoe-server/server/');
    }

    /** @return array<int, array<string, mixed>> */
    public function discoverAssignedIpv6($client): array
    {
        return $this->fetchPrintRows($client, '/ipv6/address/');
    }

    /** @return array<int, array<string, mixed>> */
    public function discoverDedicatedQueues($client): array
    {
        return $this->getAllSimpleQueue($client);
    }

    /**
     * Descubrimiento completo en modo lectura/dry-run: junta los objetos que
     * pide #951 (VLANs, perfiles PPP, servidores PPPoE, IPv6 ya asignados,
     * queues dedicadas) y delega el filtrado/propuesta a Ipv6TopologyProposer
     * (lógica pura, sin escribir nada en el router).
     *
     * @return array<string, mixed>
     */
    public function discoverTopology($client, string $marker = Ipv6TopologyProposer::DEFAULT_MARKER, ?string $familyOverride = null): array
    {
        $assignedIpv6 = $this->discoverAssignedIpv6($client);

        return [
            'version' => $this->detectVersion($client, $familyOverride),
            'marker' => $marker,
            'vlans_candidatas' => Ipv6TopologyProposer::candidatesFromVlans($this->discoverVlans($client), $assignedIpv6, $marker),
            'ppp_profiles_candidatos' => Ipv6TopologyProposer::candidatesFromPppProfiles($this->discoverPppProfiles($client), $marker),
            'pppoe_servers_por_distrito' => Ipv6TopologyProposer::groupPppoeServersByDistrict($this->discoverPppoeServers($client)),
            'ipv6_ya_asignados' => $assignedIpv6,
            'queues_dedicadas_candidatas' => Ipv6TopologyProposer::candidatesFromDedicatedQueues($this->discoverDedicatedQueues($client), $marker),
        ];
    }

    /**
     * Réplica del patrón ya usado en RouterConnection (getAllEntriesInX) para
     * comandos "print" sin helper propio todavía. Genérico para no repetir el
     * mismo bloque 4 veces dentro de esta clase nueva.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchPrintRows($client, string $command): array
    {
        $responses = $client->sendSync(new Request(rtrim($command, '/') . '/print'));
        $rows = [];
        $index = 0;
        foreach ($responses as $response) {
            if ($response->getType() === Response::TYPE_DATA) {
                foreach ($response as $name => $value) {
                    $rows[$index][$name] = $value;
                }
            }
            $index++;
        }
        return $rows;
    }
}
