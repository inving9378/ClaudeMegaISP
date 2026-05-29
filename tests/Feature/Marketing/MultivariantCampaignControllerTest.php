<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\MultivariantCampaign;
use App\Modules\Addons\Marketing\Jobs\GenerateMultivariantCampaignJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MultivariantCampaignControllerTest extends TestCase
{
    public function test_store_creates_campaign_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->actingAsAdmin()->postJson('/api/marketing/multivariant-campaigns', [
            'name'              => 'Test Campaign',
            'campaign_type'     => 'multivariant_plan_promo',
            'plan_name'         => 'Plan Test 100',
            'plan_speed'        => '100 MEGAS',
            'plan_price'        => '599',
            'phone_cta'         => '55 0000 0000',
            'highlight_feature' => 'Sin contratos',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'generating');

        Queue::assertPushed(GenerateMultivariantCampaignJob::class);
    }

    public function test_index_returns_paginated_list(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/marketing/multivariant-campaigns');
        $response->assertStatus(200);
    }

    public function test_progress_endpoint_returns_structure(): void
    {
        $campaign = MultivariantCampaign::create([
            'company_id'     => 1,
            'name'           => 'Progress Test',
            'campaign_type'  => 'multivariant_plan_promo',
            'status'         => 'generating',
            'input_data'     => ['plan_name' => 'Test'],
        ]);

        $response = $this->actingAsAdmin()->getJson("/api/marketing/multivariant-campaigns/{$campaign->id}/progress");

        $response->assertStatus(200);
        $response->assertJsonStructure(['campaign_status', 'progress_percent', 'done', 'total', 'variants']);

        $campaign->forceDelete();
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/marketing/multivariant-campaigns', [
            'name' => 'Missing fields',
        ]);

        $response->assertStatus(422);
    }

    public function test_niches_endpoint_returns_all_niches(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/marketing/niches');
        $response->assertStatus(200);
        $response->assertJsonPath('data.0.slug', 'gamer');
    }

    protected function actingAsAdmin()
    {
        $user = \App\Models\User::where('email', 'admin@meganet.com')->first();
        return $this->actingAs($user);
    }
}
