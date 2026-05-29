<?php

namespace Tests\Feature\Core;

use App\Models\Core\ApiIntegration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ApiIntegrationModelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_encrypted_value_is_hidden_from_serialization(): void
    {
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'test_provider',
            'slug'                   => 'test-hidden-' . uniqid(),
            'name'                   => 'Test',
            'active'                 => true,
            'is_default_for_provider'=> false,
        ]);
        $record->value = 'super-secret-key-xyz';
        $record->save();

        $arr = $record->toArray();
        $this->assertArrayNotHasKey('encrypted_value', $arr);
        $this->assertArrayHasKey('key_preview', $arr);
    }

    public function test_value_mutator_encrypts_and_builds_preview(): void
    {
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'test_provider',
            'slug'                   => 'test-mutator-' . uniqid(),
            'name'                   => 'Test',
            'active'                 => true,
            'is_default_for_provider'=> false,
        ]);

        $record->value = 'sk-ant-abcdef1234567890last';
        $record->save();

        $this->assertNotEmpty($record->encrypted_value);
        $this->assertStringContainsString('...', $record->key_preview);
        $this->assertEquals(64, strlen($record->key_fingerprint));
    }

    public function test_value_accessor_decrypts_correctly(): void
    {
        $plain  = 'my-test-key-decryption-check';
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'test_provider',
            'slug'                   => 'test-decrypt-' . uniqid(),
            'name'                   => 'Test',
            'active'                 => true,
            'is_default_for_provider'=> false,
        ]);
        $record->value = $plain;
        $record->save();

        $fresh = ApiIntegration::find($record->id);
        $this->assertEquals($plain, $fresh->value);
    }

    public function test_scope_default_for_returns_active_default(): void
    {
        $slug = 'test-scope-' . uniqid();
        ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'scope_provider',
            'slug'                   => $slug,
            'name'                   => 'Test',
            'active'                 => true,
            'is_default_for_provider'=> true,
        ]);

        $found = ApiIntegration::forCompany(1)->defaultFor('scope_provider')->first();
        $this->assertNotNull($found);
        $this->assertEquals($slug, $found->slug);
    }

    public function test_short_key_preview_is_all_asterisks(): void
    {
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'test_provider',
            'slug'                   => 'test-short-' . uniqid(),
            'name'                   => 'Test',
            'active'                 => true,
            'is_default_for_provider'=> false,
        ]);
        $record->value = 'abc';
        $record->save();

        $this->assertEquals('***', $record->key_preview);
    }
}
