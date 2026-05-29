<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\Channel;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadSource;
use App\Models\Marketing\Message;
use App\Models\User;
use App\Modules\Addons\Marketing\Jobs\SendOutboundMessageJob;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;


class ConversationControllerTest extends MarketingTestCase
{
    protected User $user;
    protected Channel $channel;
    protected Lead $lead;
    protected Conversation $conversation;

    public function setUp(): void
    {
        parent::setUp();

        // Reset permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create user
        $this->user = User::factory()->create();

        // Ensure permissions exist
        foreach (['view-conversations', 'manage-conversations', 'send-message-whatsapp'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $this->user->givePermissionTo(['view-conversations', 'manage-conversations', 'send-message-whatsapp']);

        // Marketing data
        $this->channel = Channel::firstOrCreate(['code' => 'whatsapp'], [
            'name' => 'WhatsApp Business', 'company_id' => 1, 'active' => true, 'config' => [],
        ]);

        $source = LeadSource::firstOrCreate(['code' => 'whatsapp_direct'], [
            'name' => 'WhatsApp Directo', 'company_id' => 1,
        ]);

        $this->lead = Lead::create([
            'company_id'  => 1,
            'source_id'   => $source->id,
            'full_name'   => 'Controller Test Lead',
            'phone'       => '5551112222',
            'whatsapp'    => '5551112222',
            'status'      => 'new',
            'captured_at' => now(),
        ]);

        $this->conversation = Conversation::create([
            'company_id'         => 1,
            'lead_id'            => $this->lead->id,
            'channel_id'         => $this->channel->id,
            'external_thread_id' => '525551112222@s.whatsapp.net',
            'ai_handled'         => true,
            'status'             => 'open',
            'unread_count'       => 3,
        ]);
    }

    public function test_unauthenticated_returns_redirect(): void
    {
        $this->getJson('/api/marketing/conversations')->assertStatus(401);
    }

    public function test_index_returns_paginated_conversations(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/marketing/conversations');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);

        // Verify the conversation created in setUp is in the results
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->conversation->id, $ids);
    }

    public function test_index_filter_by_status(): void
    {
        // Create a closed conversation
        Conversation::create([
            'company_id'  => 1, 'lead_id' => $this->lead->id,
            'channel_id'  => $this->channel->id,
            'external_thread_id' => '525551112223@s.whatsapp.net',
            'ai_handled'  => false, 'status' => 'closed', 'unread_count' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/marketing/conversations?status=closed');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('closed', $response->json('data.0.status'));
    }

    public function test_show_returns_conversation_with_lead(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/marketing/conversations/{$this->conversation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->conversation->id)
            ->assertJsonPath('data.lead.full_name', 'Controller Test Lead');
    }

    public function test_messages_returns_paginated_list(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'content'         => 'Hola',
            'content_type'    => 'text',
            'sender'          => 'lead',
            'sent_at'         => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/marketing/conversations/{$this->conversation->id}/messages");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_send_message_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/api/marketing/conversations/{$this->conversation->id}/send-message", [
                'text' => 'Hola, soy un agente humano',
            ]);

        $response->assertStatus(202)->assertJson(['queued' => true]);
        Queue::assertPushed(SendOutboundMessageJob::class);
    }

    public function test_send_message_requires_text(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/marketing/conversations/{$this->conversation->id}/send-message", [])
            ->assertStatus(422);
    }

    public function test_toggle_ai_flips_ai_handled(): void
    {
        $this->assertTrue($this->conversation->ai_handled);

        $this->actingAs($this->user)
            ->postJson("/api/marketing/conversations/{$this->conversation->id}/toggle-ai")
            ->assertStatus(200)
            ->assertJson(['ai_handled' => false]);

        $this->conversation->refresh();
        $this->assertFalse($this->conversation->ai_handled);

        // Toggle back
        $this->actingAs($this->user)
            ->postJson("/api/marketing/conversations/{$this->conversation->id}/toggle-ai")
            ->assertJson(['ai_handled' => true]);
    }

    public function test_assign_sets_user_and_disables_ai(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/marketing/conversations/{$this->conversation->id}/assign", [
                'user_id' => $this->user->id,
            ])->assertStatus(200)->assertJson(['success' => true]);

        $this->conversation->refresh();
        $this->assertFalse($this->conversation->ai_handled);
        $this->assertEquals('human_review', $this->conversation->status);
        $this->assertEquals($this->user->id, $this->conversation->assigned_user_id);
    }

    public function test_close_sets_status_to_closed(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/marketing/conversations/{$this->conversation->id}/close")
            ->assertStatus(200)->assertJson(['success' => true]);

        $this->conversation->refresh();
        $this->assertEquals('closed', $this->conversation->status);
    }

    public function test_mark_as_read_resets_unread_count(): void
    {
        $this->assertEquals(3, $this->conversation->unread_count);

        $this->actingAs($this->user)
            ->postJson("/api/marketing/conversations/{$this->conversation->id}/mark-as-read")
            ->assertStatus(200)->assertJson(['success' => true]);

        $this->conversation->refresh();
        $this->assertEquals(0, $this->conversation->unread_count);
    }

    public function test_nonexistent_conversation_returns_404(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/marketing/conversations/99999')
            ->assertStatus(404);
    }
}
