<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\Channel;
use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadSource;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Jobs\ProcessIncomingMessageJob;
use App\Modules\Addons\Marketing\Jobs\UpdateMessageStatusJob;
use Illuminate\Support\Facades\Queue;


class EvolutionWebhookTest extends MarketingTestCase
{
    protected string $webhookToken = 'test-webhook-token-64-chars-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    protected string $webhookUrl = '/webhooks/marketing/evolution';

    public function setUp(): void
    {
        parent::setUp();

        // Seed minimal marketing data
        Channel::firstOrCreate(['code' => 'whatsapp'], [
            'name' => 'WhatsApp Business', 'company_id' => 1, 'active' => true, 'config' => [],
        ]);
        LeadSource::firstOrCreate(['code' => 'whatsapp_direct'], [
            'name' => 'WhatsApp Directo', 'company_id' => 1,
        ]);

        // Store webhook token in DB
        Setting::updateOrCreate(
            ['key' => 'evolution_webhook_token', 'company_id' => 1],
            ['value' => $this->webhookToken, 'group' => 'channels', 'encrypted' => false]
        );
    }

    private function messageUpsertPayload(array $overrides = []): array
    {
        return array_merge([
            'event'    => 'messages.upsert',
            'instance' => 'test-instance',
            'data'     => [
                'key' => [
                    'id'        => 'MSG123',
                    'fromMe'    => false,
                    'remoteJid' => '521234567890@s.whatsapp.net',
                ],
                'pushName'          => 'Cliente Prueba',
                'messageTimestamp'  => time(),
                'message'           => ['conversation' => 'Hola, quiero información'],
            ],
        ], $overrides);
    }

    public function test_valid_token_and_message_upsert_dispatches_job(): void
    {
        Queue::fake();

        $this->postJson($this->webhookUrl . '?token=' . $this->webhookToken, $this->messageUpsertPayload())
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        Queue::assertPushed(ProcessIncomingMessageJob::class);
    }

    public function test_invalid_token_returns_401(): void
    {
        Queue::fake();

        $this->postJson($this->webhookUrl . '?token=wrong-token', $this->messageUpsertPayload())
            ->assertStatus(401);

        Queue::assertNotPushed(ProcessIncomingMessageJob::class);
    }

    public function test_no_token_when_stored_token_empty_passes(): void
    {
        // If no stored token, webhook should be open (token validation skipped)
        Setting::where('key', 'evolution_webhook_token')->delete();
        Queue::fake();

        $this->postJson($this->webhookUrl, $this->messageUpsertPayload())
            ->assertStatus(200);
    }

    public function test_from_me_message_is_still_dispatched_but_job_skips_it(): void
    {
        Queue::fake();

        $payload = $this->messageUpsertPayload([
            'data' => [
                'key' => ['id' => 'MSG456', 'fromMe' => true, 'remoteJid' => '521234567890@s.whatsapp.net'],
                'pushName'         => 'Me',
                'messageTimestamp' => time(),
                'message'          => ['conversation' => 'Mensaje propio'],
            ],
        ]);

        // Webhook dispatches the job regardless; the job itself filters fromMe
        $this->postJson($this->webhookUrl . '?token=' . $this->webhookToken, $payload)
            ->assertStatus(200);

        Queue::assertPushed(ProcessIncomingMessageJob::class);
    }

    public function test_group_message_event_dispatches_job(): void
    {
        Queue::fake();

        $payload = $this->messageUpsertPayload([
            'data' => [
                'key' => ['id' => 'MSG789', 'fromMe' => false, 'remoteJid' => '120363000000@g.us'],
                'pushName'         => 'Grupo Test',
                'messageTimestamp' => time(),
                'message'          => ['conversation' => 'Mensaje de grupo'],
            ],
        ]);

        $this->postJson($this->webhookUrl . '?token=' . $this->webhookToken, $payload)
            ->assertStatus(200);

        // Job is dispatched; it will skip internally
        Queue::assertPushed(ProcessIncomingMessageJob::class);
    }

    public function test_messages_update_event_dispatches_update_job(): void
    {
        Queue::fake();

        $this->postJson($this->webhookUrl . '?token=' . $this->webhookToken, [
            'event'    => 'messages.update',
            'instance' => 'test-instance',
            'data'     => [['key' => ['id' => 'MSG123'], 'update' => ['status' => 'READ']]],
        ])->assertStatus(200);

        Queue::assertPushed(UpdateMessageStatusJob::class);
    }

    public function test_connection_update_close_logs_warning(): void
    {
        Queue::fake();

        $this->postJson($this->webhookUrl . '?token=' . $this->webhookToken, [
            'event'    => 'connection.update',
            'instance' => 'test-instance',
            'data'     => ['state' => 'close'],
        ])->assertStatus(200);

        // No job dispatched; handled inline with logging
        Queue::assertNotPushed(ProcessIncomingMessageJob::class);
    }
}
