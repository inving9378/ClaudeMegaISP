<?php

namespace Tests\Feature\Core;

use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationLog;
use App\Services\Core\ApiIntegrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ApiIntegrationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ApiIntegrationService $svc;

    public function setUp(): void
    {
        parent::setUp();
        $this->svc = new ApiIntegrationService();
    }

    public function test_get_key_returns_null_when_no_integration_exists(): void
    {
        $key = $this->svc->getKey('nonexistent_provider_xyz', 999);
        $this->assertNull($key);
    }

    public function test_get_key_returns_decrypted_value(): void
    {
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'test_svc_provider',
            'slug'                   => 'test-svc-' . uniqid(),
            'name'                   => 'Test',
            'active'                 => true,
            'is_default_for_provider'=> true,
        ]);
        $record->value = 'my-svc-key-value';
        $record->save();

        $this->assertEquals('my-svc-key-value', $this->svc->getKey('test_svc_provider', 1));
    }

    public function test_audit_creates_log_entry(): void
    {
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'audit_provider',
            'slug'                   => 'audit-svc-' . uniqid(),
            'name'                   => 'Audit Test',
            'active'                 => true,
            'is_default_for_provider'=> false,
        ]);

        $this->svc->audit($record, 'key_created', ['source' => 'test'], 'test@example.com');

        $this->assertDatabaseHas('api_integration_logs', [
            'integration_id' => $record->id,
            'event_type'     => 'key_created',
            'actor'          => 'test@example.com',
        ]);
    }

    public function test_validate_returns_invalid_when_no_key(): void
    {
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'openai',
            'slug'                   => 'test-nokey-' . uniqid(),
            'name'                   => 'No key test',
            'active'                 => false,
            'is_default_for_provider'=> false,
        ]);

        $result = $this->svc->validate($record);

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid', $result['status']);
    }

    public function test_get_providers_returns_expected_providers(): void
    {
        $providers = $this->svc->getProviders();
        $ids = array_column($providers, 'id');

        $this->assertContains('anthropic',  $ids);
        $this->assertContains('openai',     $ids);
        $this->assertContains('evolution',  $ids);
        $this->assertContains('pexels',     $ids);
        $this->assertContains('google_maps',$ids);
    }
}
