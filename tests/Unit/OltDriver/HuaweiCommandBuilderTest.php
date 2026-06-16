<?php

namespace Tests\Unit\OltDriver;

use App\Services\OltDriver\Huawei\HuaweiCommandBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — no DB, no transport, no network.
 * Verifies that HuaweiCommandBuilder produces the correct VRP command sequences.
 */
class HuaweiCommandBuilderTest extends TestCase
{
    // ── rebootOnt ────────────────────────────────────────────────────────────

    public function test_reboot_ont_contains_interface_nav(): void
    {
        $steps = HuaweiCommandBuilder::rebootOnt(0, 3, 2, 0);

        $this->assertSame('interface gpon 0/3', $steps[0]['cmd']);
        $this->assertTrue($steps[0]['nav']);
    }

    public function test_reboot_ont_contains_ont_reset_command(): void
    {
        $steps = HuaweiCommandBuilder::rebootOnt(0, 3, 2, 0);

        $resetStep = $steps[1];
        $this->assertSame('ont reset 2 0', $resetStep['cmd']);
        $this->assertFalse($resetStep['nav']);
        $this->assertSame('y', $resetStep['confirm']);
    }

    public function test_reboot_ont_ends_with_return(): void
    {
        $steps = HuaweiCommandBuilder::rebootOnt(0, 3, 2, 0);

        $last = end($steps);
        $this->assertSame('return', $last['cmd']);
        $this->assertTrue($last['nav']);
    }

    public function test_reboot_ont_port_and_ont_id_are_substituted(): void
    {
        $steps = HuaweiCommandBuilder::rebootOnt(0, 5, 7, 3);

        $cmds = HuaweiCommandBuilder::cmdStrings($steps);
        $this->assertContains('interface gpon 0/5', $cmds);
        $this->assertContains('ont reset 7 3', $cmds);
    }

    // ── deleteOnt ────────────────────────────────────────────────────────────

    public function test_delete_ont_contains_ont_delete_command(): void
    {
        $steps = HuaweiCommandBuilder::deleteOnt(0, 3, 2, 0);

        $deleteStep = $steps[1];
        $this->assertSame('ont delete 2 0', $deleteStep['cmd']);
        $this->assertFalse($deleteStep['nav']);
        $this->assertSame('y', $deleteStep['confirm']);
    }

    public function test_delete_ont_three_steps(): void
    {
        $steps = HuaweiCommandBuilder::deleteOnt(0, 3, 2, 0);
        $this->assertCount(3, $steps);
    }

    // ── addOnt ───────────────────────────────────────────────────────────────

    public function test_add_ont_contains_sn_and_profiles(): void
    {
        $steps = HuaweiCommandBuilder::addOnt(0, 3, 2, 0, 'HWTCFEFCC9A2', 1, 2, 'PRUEBA');

        $addStep = $steps[1];
        $this->assertStringContainsString('sn-auth HWTCFEFCC9A2', $addStep['cmd']);
        $this->assertStringContainsString('ont-lineprofile-id 1', $addStep['cmd']);
        $this->assertStringContainsString('ont-srvprofile-id 2', $addStep['cmd']);
    }

    public function test_add_ont_includes_desc_when_provided(): void
    {
        $steps = HuaweiCommandBuilder::addOnt(0, 3, 2, 0, 'HWTCFEFCC9A2', 1, 2, 'PRUEBA');

        $this->assertStringContainsString('desc "PRUEBA"', $steps[1]['cmd']);
    }

    public function test_add_ont_omits_desc_when_empty(): void
    {
        $steps = HuaweiCommandBuilder::addOnt(0, 3, 2, 0, 'HWTCFEFCC9A2', 1, 2);

        $this->assertStringNotContainsString('desc', $steps[1]['cmd']);
    }

    public function test_add_ont_port_id_and_ont_id_in_command(): void
    {
        $steps = HuaweiCommandBuilder::addOnt(0, 3, 2, 0, 'HWTCFEFCC9A2', 1, 2);

        $this->assertStringStartsWith('ont add 2 0 ', $steps[1]['cmd']);
    }

    // ── addServicePort ───────────────────────────────────────────────────────

    public function test_add_service_port_single_tag(): void
    {
        $steps = HuaweiCommandBuilder::addServicePort(200, 0, 3, 2, 0, 1, 200, null);

        $this->assertCount(1, $steps);
        $cmd = $steps[0]['cmd'];
        $this->assertStringContainsString('service-port vlan 200', $cmd);
        $this->assertStringContainsString('gpon 0/3/2', $cmd);
        $this->assertStringContainsString('ont 0', $cmd);
        $this->assertStringContainsString('tag-transform default', $cmd);
    }

    public function test_add_service_port_double_tag(): void
    {
        $steps = HuaweiCommandBuilder::addServicePort(105, 0, 3, 2, 0, 1, 200, 200);

        $cmd = $steps[0]['cmd'];
        $this->assertStringContainsString('service-port vlan 105', $cmd);
        $this->assertStringContainsString('user-vlan 200', $cmd);
        $this->assertStringContainsString('translate-and-add inner-vlan 200', $cmd);
    }

    public function test_add_service_port_lab_onu_pattern(): void
    {
        // Pattern from the lab ONT: SVLAN=105, CVLAN=200, user-vlan=200, gemport=1
        $steps = HuaweiCommandBuilder::addServicePort(
            svlan:    105,
            frame:    0,
            slot:     3,
            port:     2,
            ontId:    0,
            gemport:  1,
            userVlan: 200,
            cvlan:    200,
        );

        $cmd = $steps[0]['cmd'];
        $this->assertSame(
            'service-port vlan 105 gpon 0/3/2 ont 0 gemport 1 multi-service user-vlan 200 tag-transform translate-and-add inner-vlan 200',
            $cmd
        );
    }

    // ── activateOnt / deactivateOnt ──────────────────────────────────────────

    public function test_activate_ont_command(): void
    {
        $steps = HuaweiCommandBuilder::activateOnt(0, 3, 2, 0);

        $this->assertSame('ont activate 2 0', $steps[1]['cmd']);
        $this->assertNull($steps[1]['confirm']);
    }

    public function test_deactivate_ont_command(): void
    {
        $steps = HuaweiCommandBuilder::deactivateOnt(0, 3, 2, 0);

        $this->assertSame('ont deactivate 2 0', $steps[1]['cmd']);
        $this->assertNull($steps[1]['confirm']);
    }

    // ── cmdStrings() ─────────────────────────────────────────────────────────

    public function test_cmd_strings_extracts_only_cmd_field(): void
    {
        $steps = HuaweiCommandBuilder::rebootOnt(0, 3, 2, 0);
        $cmds  = HuaweiCommandBuilder::cmdStrings($steps);

        $this->assertSame(['interface gpon 0/3', 'ont reset 2 0', 'return'], $cmds);
    }

    // ── Step shape contract ───────────────────────────────────────────────────

    /** @dataProvider allBuilderMethods */
    public function test_every_step_has_required_keys(array $steps): void
    {
        foreach ($steps as $step) {
            $this->assertArrayHasKey('cmd',     $step);
            $this->assertArrayHasKey('nav',     $step);
            $this->assertArrayHasKey('confirm', $step);
            $this->assertIsString($step['cmd']);
            $this->assertIsBool($step['nav']);
        }
    }

    public static function allBuilderMethods(): array
    {
        return [
            'rebootOnt'       => [HuaweiCommandBuilder::rebootOnt(0, 3, 2, 0)],
            'deleteOnt'       => [HuaweiCommandBuilder::deleteOnt(0, 3, 2, 0)],
            'addOnt'          => [HuaweiCommandBuilder::addOnt(0, 3, 2, 0, 'HWTCFEFCC9A2', 1, 2, 'TEST')],
            'addServicePort'  => [HuaweiCommandBuilder::addServicePort(105, 0, 3, 2, 0, 1, 200, 200)],
            'activateOnt'     => [HuaweiCommandBuilder::activateOnt(0, 3, 2, 0)],
            'deactivateOnt'   => [HuaweiCommandBuilder::deactivateOnt(0, 3, 2, 0)],
        ];
    }
}
