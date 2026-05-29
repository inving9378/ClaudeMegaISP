<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\Channel;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadSource;
use App\Modules\Addons\Marketing\Services\ConversationResolverService;


class ConversationResolverServiceTest extends MarketingTestCase
{
    protected ConversationResolverService $resolver;
    protected Channel $channel;
    protected LeadSource $source;

    public function setUp(): void
    {
        parent::setUp();

        $this->channel = Channel::firstOrCreate(['code' => 'whatsapp'], [
            'name' => 'WhatsApp Business', 'company_id' => 1, 'active' => true, 'config' => [],
        ]);
        $this->source = LeadSource::firstOrCreate(['code' => 'whatsapp_direct'], [
            'name' => 'WhatsApp Directo', 'company_id' => 1,
        ]);

        $this->resolver = app(ConversationResolverService::class);
    }

    private function payload(string $jid, string $pushName = 'Test User', bool $fromMe = false): array
    {
        return [
            'event'    => 'messages.upsert',
            'instance' => 'test',
            'data'     => [
                'key'              => ['id' => 'MSG_' . uniqid(), 'fromMe' => $fromMe, 'remoteJid' => $jid],
                'pushName'         => $pushName,
                'messageTimestamp' => time(),
                'message'          => ['conversation' => 'Hola'],
            ],
        ];
    }

    public function test_new_phone_creates_lead(): void
    {
        $result = $this->resolver->resolveFromEvolutionMessage($this->payload('521234567890@s.whatsapp.net', 'Juan Nuevo'));

        $this->assertTrue($result['is_new_lead']);
        $this->assertInstanceOf(Lead::class, $result['lead']);
        $this->assertEquals('Juan Nuevo', $result['lead']->full_name);
        $this->assertDatabaseHas('marketing_leads', ['whatsapp' => '1234567890']);
    }

    public function test_existing_phone_reuses_lead(): void
    {
        $existing = Lead::create([
            'company_id'  => 1,
            'whatsapp'    => '5551234567',
            'phone'       => '5551234567',
            'full_name'   => 'Lead Existente',
            'status'      => 'new',
            'captured_at' => now(),
        ]);

        $result = $this->resolver->resolveFromEvolutionMessage($this->payload('5215551234567@s.whatsapp.net'));

        $this->assertFalse($result['is_new_lead']);
        $this->assertEquals($existing->id, $result['lead']->id);
    }

    public function test_new_lead_gets_whatsapp_source(): void
    {
        $result = $this->resolver->resolveFromEvolutionMessage($this->payload('529998887776@s.whatsapp.net'));

        $this->assertEquals($this->source->id, $result['lead']->source_id);
    }

    public function test_lead_with_no_open_conversation_creates_new_one(): void
    {
        $result = $this->resolver->resolveFromEvolutionMessage($this->payload('521112223333@s.whatsapp.net'));

        $this->assertInstanceOf(Conversation::class, $result['conversation']);
        $this->assertEquals($this->channel->id, $result['conversation']->channel_id);
        $this->assertTrue($result['conversation']->ai_handled);
        $this->assertEquals('open', $result['conversation']->status);
    }

    public function test_lead_with_existing_open_conversation_reuses_it(): void
    {
        $lead = Lead::create([
            'company_id'  => 1,
            'whatsapp'    => '4441234567',
            'phone'       => '4441234567',
            'full_name'   => 'Lead Con Conv',
            'status'      => 'new',
            'captured_at' => now(),
        ]);

        $conv = Conversation::create([
            'company_id'         => 1,
            'lead_id'            => $lead->id,
            'channel_id'         => $this->channel->id,
            'external_thread_id' => '52444123456@s.whatsapp.net',
            'ai_handled'         => true,
            'status'             => 'open',
            'unread_count'       => 2,
        ]);

        $countBefore = Conversation::count();
        $result = $this->resolver->resolveFromEvolutionMessage($this->payload('524441234567@s.whatsapp.net'));

        $this->assertEquals($conv->id, $result['conversation']->id);
        $this->assertEquals($countBefore, Conversation::count(), 'No new conversation should be created');
    }

    public function test_lead_with_closed_conversation_creates_new_one(): void
    {
        $lead = Lead::create([
            'company_id'  => 1,
            'whatsapp'    => '3331234567',
            'phone'       => '3331234567',
            'full_name'   => 'Lead Cerrado',
            'status'      => 'won',
            'captured_at' => now(),
        ]);

        Conversation::create([
            'company_id'         => 1,
            'lead_id'            => $lead->id,
            'channel_id'         => $this->channel->id,
            'external_thread_id' => '523331234567@s.whatsapp.net',
            'ai_handled'         => false,
            'status'             => 'closed',
            'unread_count'       => 0,
        ]);

        $countBefore = Conversation::count();
        $result = $this->resolver->resolveFromEvolutionMessage($this->payload('523331234567@s.whatsapp.net'));

        // New conversation created since existing is closed
        $this->assertEquals($countBefore + 1, Conversation::count(), 'A new conversation should be created');
        $this->assertEquals('open', $result['conversation']->status);
    }
}
