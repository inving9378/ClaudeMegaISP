<?php

namespace Tests\Unit\Services\Ipv6;

use App\Services\Ipv6\Ipv6TopologyProposer;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO toca BD, NO migrate:fresh.

class Ipv6TopologyProposerTest extends TestCase
{
    public function test_filter_by_marker_ignora_objetos_sin_marcador(): void
    {
        $items = [
            ['name' => 'vlan-clientes', 'comment' => 'sin relacion'],
            ['name' => 'vlan-infra-10', 'comment' => 'MgNet-IPv6 zona norte'],
        ];
        $result = Ipv6TopologyProposer::filterByMarker($items);
        $this->assertCount(1, $result);
        $this->assertSame('vlan-infra-10', $result[0]['name']);
    }

    public function test_candidates_from_vlans_detecta_ya_asignado_y_no_calcula_el_prefijo(): void
    {
        $vlans = [
            ['name' => 'vlan10', 'vlan-id' => '10', 'comment' => 'MgNet-IPv6'],
            ['name' => 'vlan20', 'vlan-id' => '20', 'comment' => 'MgNet-IPv6'],
        ];
        $assigned = [
            ['interface' => 'vlan10', 'address' => '2001:db8:1::1/64'],
        ];

        $result = Ipv6TopologyProposer::candidatesFromVlans($vlans, $assigned);

        $this->assertTrue($result[0]['ya_asignado']);
        $this->assertSame(['2001:db8:1::1/64'], $result[0]['direcciones_existentes']);
        $this->assertFalse($result[1]['ya_asignado']);
        $this->assertNull($result[1]['proposed_prefix']); // el número lo calcula #950, no esta pieza
    }

    public function test_candidates_from_vlans_ignora_las_que_no_tienen_marcador(): void
    {
        $vlans = [['name' => 'vlan-wan', 'vlan-id' => '1', 'comment' => 'no tocar']];
        $result = Ipv6TopologyProposer::candidatesFromVlans($vlans, []);
        $this->assertCount(0, $result);
    }

    public function test_group_pppoe_servers_by_district_agrupa_por_service_name(): void
    {
        $servers = [
            ['service-name' => 'norte', 'interface' => 'ether1'],
            ['service-name' => 'norte', 'interface' => 'ether2'],
            ['service-name' => 'sur', 'interface' => 'ether3'],
        ];
        $groups = Ipv6TopologyProposer::groupPppoeServersByDistrict($servers);
        $this->assertCount(2, $groups['norte']);
        $this->assertCount(1, $groups['sur']);
    }

    public function test_candidates_from_dedicated_queues_respeta_marcador(): void
    {
        $queues = [
            ['name' => 'cliente-1', 'target' => '10.0.0.1/32', 'comment' => 'normal'],
            ['name' => 'infra-core', 'target' => '10.0.0.2/32', 'comment' => 'MgNet-IPv6'],
        ];
        $result = Ipv6TopologyProposer::candidatesFromDedicatedQueues($queues);
        $this->assertCount(1, $result);
        $this->assertSame('infra-core', $result[0]['name']);
    }
}
