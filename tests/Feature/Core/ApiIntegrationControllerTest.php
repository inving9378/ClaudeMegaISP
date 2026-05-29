<?php

namespace Tests\Feature\Core;

use App\Models\Core\ApiIntegration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApiIntegrationControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_providers_endpoint_returns_list(): void
    {
        $res = $this->getJson('/api/hub/providers');
        $res->assertOk();
        $ids = array_column($res->json('data'), 'id');
        $this->assertContains('anthropic', $ids);
    }

    public function test_index_returns_pageable_list(): void
    {
        $res = $this->getJson('/api/hub/integrations');
        $res->assertOk();
        $this->assertArrayHasKey('data', $res->json());
    }

    public function test_store_creates_integration(): void
    {
        $slug = 'ctrl-test-' . uniqid();
        $res  = $this->postJson('/api/hub/integrations', [
            'provider' => 'openai',
            'slug'     => $slug,
            'name'     => 'Test Integration',
            'key'      => 'sk-test-key-12345678',
            'active'   => true,
        ]);
        $res->assertCreated();
        $this->assertDatabaseHas('api_integrations', ['slug' => $slug]);
    }

    public function test_update_changes_name(): void
    {
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'pexels',
            'slug'                   => 'ctrl-upd-' . uniqid(),
            'name'                   => 'Old Name',
            'active'                 => true,
            'is_default_for_provider'=> false,
        ]);

        $res = $this->putJson("/api/hub/integrations/{$record->id}", ['name' => 'New Name']);
        $res->assertOk();
        $this->assertDatabaseHas('api_integrations', ['id' => $record->id, 'name' => 'New Name']);
    }

    public function test_destroy_deletes_non_default(): void
    {
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'pexels',
            'slug'                   => 'ctrl-del-' . uniqid(),
            'name'                   => 'Delete Me',
            'active'                 => true,
            'is_default_for_provider'=> false,
        ]);

        $res = $this->deleteJson("/api/hub/integrations/{$record->id}");
        $res->assertOk();
        $this->assertSoftDeleted('api_integrations', ['id' => $record->id]);
    }

    public function test_destroy_rejects_default_integration(): void
    {
        $record = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => 'pexels',
            'slug'                   => 'ctrl-nodelete-' . uniqid(),
            'name'                   => 'Default',
            'active'                 => true,
            'is_default_for_provider'=> true,
        ]);

        $res = $this->deleteJson("/api/hub/integrations/{$record->id}");
        $res->assertStatus(422);
    }

    private function actingAsAdmin(): void
    {
        $user = \App\Models\User::where('email', 'admin@meganet.com')->first();
        if ($user) {
            $this->actingAs($user);
        }
    }
}
