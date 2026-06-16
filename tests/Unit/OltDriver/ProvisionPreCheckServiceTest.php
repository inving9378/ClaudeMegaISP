<?php

namespace Tests\Unit\OltDriver;

use App\Services\OltDriver\Huawei\ProvisionPreCheckService;
use App\Services\OltDriver\Huawei\ReadOnlyGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Pure unit test — no DB, no transport, no network.
 * Covers checks 1, 5, 6 (no DB) and the allPassed() helper.
 * Checks 2-4 (DB reads) are verified via tinker against the production DB
 * to avoid migrate:fresh which would destroy it.
 */
class ProvisionPreCheckServiceTest extends TestCase
{
    private ProvisionPreCheckService $svc;

    protected function setUp(): void
    {
        $this->svc = new ProvisionPreCheckService(new ReadOnlyGuard(new NullLogger()));
    }

    // ── Check 1 — allow-list ─────────────────────────────────────────────────

    public function test_check1_passes_for_lab_sn(): void
    {
        $result = $this->svc->checkSnInAllowList('HWTCFEFCC9A2');

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('HWTCFEFCC9A2', $result['message']);
    }

    public function test_check1_passes_case_insensitive(): void
    {
        $result = $this->svc->checkSnInAllowList('hwtcfefcc9a2');
        $this->assertTrue($result['ok']);
    }

    public function test_check1_fails_for_unknown_sn(): void
    {
        $result = $this->svc->checkSnInAllowList('DEADBEEF00000000');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('DEADBEEF00000000', $result['message']);
    }

    public function test_check1_has_required_keys(): void
    {
        $result = $this->svc->checkSnInAllowList('HWTCFEFCC9A2');

        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('label', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('data', $result);
    }

    // ── Check 5 — line-profile ───────────────────────────────────────────────

    public function test_check5_passes_when_profile_found(): void
    {
        $profiles = [
            ['id' => 1, 'name' => 'SMARTOLT_FLEXIBLE_GPON', 'bindings' => 2206],
        ];

        $result = $this->svc->checkLineProfileExists(1, $profiles);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('SMARTOLT_FLEXIBLE_GPON', $result['message']);
    }

    public function test_check5_fails_when_profile_not_found(): void
    {
        $profiles = [
            ['id' => 1, 'name' => 'SMARTOLT_FLEXIBLE_GPON', 'bindings' => 2206],
        ];

        $result = $this->svc->checkLineProfileExists(999, $profiles);

        $this->assertFalse($result['ok']);
    }

    public function test_check5_skipped_when_profiles_null(): void
    {
        $result = $this->svc->checkLineProfileExists(1, null);

        $this->assertNull($result['ok']);
        $this->assertStringContainsString('omitida', $result['message']);
    }

    // ── Check 6 — srv-profile ────────────────────────────────────────────────

    public function test_check6_passes_when_srv_profile_found(): void
    {
        $profiles = [
            ['id' => 2, 'name' => 'HG8145V5', 'bindings' => 711],
        ];

        $result = $this->svc->checkSrvProfileExists(2, $profiles);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('HG8145V5', $result['message']);
    }

    public function test_check6_fails_when_srv_profile_not_found(): void
    {
        $profiles = [
            ['id' => 2, 'name' => 'HG8145V5', 'bindings' => 711],
        ];

        $result = $this->svc->checkSrvProfileExists(999, $profiles);

        $this->assertFalse($result['ok']);
    }

    public function test_check6_skipped_when_profiles_null(): void
    {
        $result = $this->svc->checkSrvProfileExists(2, null);

        $this->assertNull($result['ok']);
    }

    // ── allPassed() ──────────────────────────────────────────────────────────

    public function test_all_passed_true_when_all_ok(): void
    {
        $results = [
            ['ok' => true,  'label' => 'A', 'message' => ''],
            ['ok' => true,  'label' => 'B', 'message' => ''],
            ['ok' => null,  'label' => 'C', 'message' => ''],  // skipped = ignored
        ];

        $this->assertTrue(ProvisionPreCheckService::allPassed($results));
    }

    public function test_all_passed_false_when_one_fails(): void
    {
        $results = [
            ['ok' => true,  'label' => 'A', 'message' => ''],
            ['ok' => false, 'label' => 'B', 'message' => ''],
            ['ok' => true,  'label' => 'C', 'message' => ''],
        ];

        $this->assertFalse(ProvisionPreCheckService::allPassed($results));
    }

    public function test_all_passed_true_for_empty_results(): void
    {
        $this->assertTrue(ProvisionPreCheckService::allPassed([]));
    }
}
