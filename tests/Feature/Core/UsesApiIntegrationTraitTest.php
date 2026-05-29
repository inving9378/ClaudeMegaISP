<?php

namespace Tests\Feature\Core;

use App\Models\Core\ApiIntegration;
use App\Services\Core\UsesApiIntegration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UsesApiIntegrationTraitTest extends TestCase
{
    use DatabaseTransactions;

    private object $subject;

    public function setUp(): void
    {
        parent::setUp();
        $this->subject = new class {
            use UsesApiIntegration;

            public function resolveKey(string $provider, string $envFallback = '', string $settingKey = '', int $companyId = 1): ?string
            {
                return $this->resolveApiKey($provider, $envFallback, $settingKey, $companyId);
            }

            public function resolveIntegration(string $provider, int $companyId = 1)
            {
                return $this->resolveApiIntegration($provider, $companyId);
            }
        };
    }

    public function test_resolve_key_returns_hub_value_when_integration_exists(): void
    {
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'trait_provider',
            'slug'                   => 'trait-hub-' . uniqid(),
            'name'                   => 'Trait Test',
            'active'                 => true,
            'is_default_for_provider'=> true,
        ]);
        $record->value = 'hub-value-xyz';
        $record->save();

        $this->assertEquals('hub-value-xyz', $this->subject->resolveKey('trait_provider', '', '', 1));
    }

    public function test_resolve_key_falls_back_to_env_when_no_hub(): void
    {
        putenv('TEST_TRAIT_KEY=env-fallback-value');
        $key = $this->subject->resolveKey('no_such_provider_xyz', 'TEST_TRAIT_KEY', '', 999);
        $this->assertEquals('env-fallback-value', $key);
        putenv('TEST_TRAIT_KEY');
    }

    public function test_resolve_key_returns_null_when_nothing_configured(): void
    {
        $key = $this->subject->resolveKey('totally_unknown_provider_abc', '', '', 999);
        $this->assertNull($key);
    }

    public function test_resolve_integration_returns_null_for_nonexistent_provider(): void
    {
        $integration = $this->subject->resolveIntegration('no_such_provider_xyz', 999);
        $this->assertNull($integration);
    }

    public function test_resolve_integration_returns_model_when_exists(): void
    {
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'trait_model_provider',
            'slug'                   => 'trait-model-' . uniqid(),
            'name'                   => 'Trait Model Test',
            'active'                 => true,
            'is_default_for_provider'=> true,
        ]);

        $found = $this->subject->resolveIntegration('trait_model_provider', 1);
        $this->assertNotNull($found);
        $this->assertEquals($record->id, $found->id);
    }
}
