<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\AiAgentConfig;
use App\Models\Marketing\Channel;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Lead;
use App\Models\Marketing\Message;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Services\AiAgentService;
use App\Modules\Addons\Marketing\Services\ClaudeApiClient;


class AiAgentServiceTest extends MarketingTestCase
{
    protected Channel $channel;
    protected Lead $lead;
    protected Conversation $conversation;
    protected AiAgentConfig $config;

    public function setUp(): void
    {
        parent::setUp();

        $this->channel = Channel::firstOrCreate(['code' => 'whatsapp'], [
            'name' => 'WhatsApp Business', 'company_id' => 1, 'active' => true, 'config' => [],
        ]);

        $this->config = AiAgentConfig::create([
            'company_id'         => 1,
            'name'               => 'Test Agent',
            'channel_id'         => $this->channel->id,
            'system_prompt'      => 'Eres Mega, asistente de prueba.',
            'persona'            => 'Eres Mega, asistente de prueba.',
            'knowledge_base'     => 'Información de prueba.',
            'model'              => 'claude-opus-4-7',
            'temperature'        => 0.7,
            'max_tokens'         => 512,
            'max_turns_before_human' => 3,
            'tools_enabled'      => ['update_lead', 'assign_to_human', 'query_plans', 'check_coverage', 'schedule_appointment'],
            'active'             => true,
        ]);

        $this->lead = Lead::create([
            'company_id'  => 1,
            'full_name'   => 'Test Lead',
            'phone'       => '5551234567',
            'whatsapp'    => '5551234567',
            'status'      => 'new',
            'captured_at' => now(),
        ]);

        $this->conversation = Conversation::create([
            'company_id'         => 1,
            'lead_id'            => $this->lead->id,
            'channel_id'         => $this->channel->id,
            'external_thread_id' => '525551234567@s.whatsapp.net',
            'ai_handled'         => true,
            'status'             => 'open',
            'unread_count'       => 1,
        ]);

        // Seed safety settings
        Setting::firstOrCreate(['key' => 'agent_max_turns_safety', 'company_id' => 1], [
            'group' => 'ai', 'value' => '20', 'encrypted' => false,
        ]);
        Setting::firstOrCreate(['key' => 'agent_monthly_budget_usd', 'company_id' => 1], [
            'group' => 'ai', 'value' => '50.00', 'encrypted' => false,
        ]);
        Setting::firstOrCreate(['key' => 'agent_handoff_on_failure', 'company_id' => 1], [
            'group' => 'ai', 'value' => 'true', 'encrypted' => false,
        ]);
    }

    private function seedMessage(string $content, string $direction = 'inbound'): Message
    {
        return Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => $direction,
            'content'         => $content,
            'content_type'    => 'text',
            'sender'          => $direction === 'inbound' ? 'lead' : 'ai_agent',
            'sent_at'         => now(),
        ]);
    }

    private function makeClaudeMock(array $response): ClaudeApiClient
    {
        $mock = $this->createMock(ClaudeApiClient::class);
        $mock->method('messages')->willReturn($response);
        $mock->method('calculateCost')->willReturn(0.001);
        return $mock;
    }

    public function test_simple_response_returns_text(): void
    {
        $inbound = $this->seedMessage('Hola, ¿cuáles son sus planes?');

        $claudeResponse = [
            'stop_reason' => 'end_turn',
            'content'     => [['type' => 'text', 'text' => '¡Hola! Tenemos varios planes disponibles.']],
            'usage'       => ['input_tokens' => 100, 'output_tokens' => 50],
        ];

        $agent = $this->buildAgent($this->makeClaudeMock($claudeResponse));
        $result = $agent->respondTo($this->conversation, $inbound);

        $this->assertEquals('¡Hola! Tenemos varios planes disponibles.', $result);
    }

    public function test_tool_use_then_end_turn_loops_and_returns_text(): void
    {
        $inbound = $this->seedMessage('¿Tienen cobertura en mi colonia Centro?');

        $toolUseResponse = [
            'stop_reason' => 'tool_use',
            'content'     => [[
                'type'  => 'tool_use',
                'id'    => 'toolu_01',
                'name'  => 'check_coverage',
                'input' => ['address' => 'Centro', 'neighborhood' => 'Centro'],
            ]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 30],
        ];

        $finalResponse = [
            'stop_reason' => 'end_turn',
            'content'     => [['type' => 'text', 'text' => 'Sí, tenemos cobertura en el Centro.']],
            'usage'       => ['input_tokens' => 150, 'output_tokens' => 40],
        ];

        $mock = $this->createMock(ClaudeApiClient::class);
        $mock->expects($this->exactly(2))
            ->method('messages')
            ->willReturnOnConsecutiveCalls($toolUseResponse, $finalResponse);
        $mock->method('calculateCost')->willReturn(0.001);

        $agent = $this->buildAgent($mock);
        $result = $agent->respondTo($this->conversation, $inbound);

        $this->assertEquals('Sí, tenemos cobertura en el Centro.', $result);
    }

    public function test_assign_to_human_tool_sets_conversation_to_human_review(): void
    {
        $inbound = $this->seedMessage('Quiero hablar con un humano por favor');

        $toolUseResponse = [
            'stop_reason' => 'tool_use',
            'content'     => [[
                'type'  => 'tool_use',
                'id'    => 'toolu_02',
                'name'  => 'assign_to_human',
                'input' => ['reason' => 'Cliente solicitó hablar con humano'],
            ]],
            'usage' => ['input_tokens' => 80, 'output_tokens' => 20],
        ];

        $finalResponse = [
            'stop_reason' => 'end_turn',
            'content'     => [['type' => 'text', 'text' => 'Te estoy conectando con un asesor.']],
            'usage'       => ['input_tokens' => 100, 'output_tokens' => 30],
        ];

        $mock = $this->createMock(ClaudeApiClient::class);
        $mock->method('messages')
            ->willReturnOnConsecutiveCalls($toolUseResponse, $finalResponse);
        $mock->method('calculateCost')->willReturn(0.001);

        $agent = $this->buildAgent($mock);
        $agent->respondTo($this->conversation, $inbound);

        $this->conversation->refresh();
        $this->assertFalse($this->conversation->ai_handled);
        $this->assertEquals('human_review', $this->conversation->status);
    }

    public function test_exceeding_max_turns_triggers_handoff(): void
    {
        Setting::updateOrCreate(
            ['key' => 'agent_max_turns_safety', 'company_id' => 1],
            ['group' => 'ai', 'value' => '2', 'encrypted' => false]
        );

        $inbound = $this->seedMessage('Pregunta compleja');

        // Always return tool_use to force infinite loop, hitting max_turns
        $toolUseResponse = [
            'stop_reason' => 'tool_use',
            'content'     => [[
                'type'  => 'tool_use',
                'id'    => 'toolu_loop',
                'name'  => 'check_coverage',
                'input' => ['address' => 'test'],
            ]],
            'usage' => ['input_tokens' => 50, 'output_tokens' => 10],
        ];

        $mock = $this->createMock(ClaudeApiClient::class);
        $mock->method('messages')->willReturn($toolUseResponse);
        $mock->method('calculateCost')->willReturn(0.0001);

        $agent = $this->buildAgent($mock);
        $result = $agent->respondTo($this->conversation, $inbound);

        $this->assertNull($result);
        $this->conversation->refresh();
        $this->assertEquals('human_review', $this->conversation->status);
    }

    public function test_non_ai_handled_conversation_returns_null(): void
    {
        $this->conversation->update(['ai_handled' => false]);
        $inbound = $this->seedMessage('Mensaje a conversación manual');

        $mock = $this->createMock(ClaudeApiClient::class);
        $mock->expects($this->never())->method('messages');

        $agent = $this->buildAgent($mock);
        $result = $agent->respondTo($this->conversation, $inbound);

        $this->assertNull($result);
    }

    public function test_costs_are_recorded_in_generated_content(): void
    {
        $inbound = $this->seedMessage('Prueba de costo');

        $mock = $this->makeClaudeMock([
            'stop_reason' => 'end_turn',
            'content'     => [['type' => 'text', 'text' => 'Respuesta de prueba']],
            'usage'       => ['input_tokens' => 200, 'output_tokens' => 100],
        ]);

        $agent = $this->buildAgent($mock);
        $agent->respondTo($this->conversation, $inbound);

        $this->assertDatabaseHas('marketing_generated_content', [
            'type'        => 'ai_response',
            'company_id'  => 1,
        ]);
    }

    private function buildAgent(ClaudeApiClient $claude): AiAgentService
    {
        return new AiAgentService(
            $claude,
            app(\App\Modules\Addons\Marketing\Services\PromptBuilder::class),
            app(\App\Modules\Addons\Marketing\Services\AgentTools\UpdateLeadTool::class),
            app(\App\Modules\Addons\Marketing\Services\AgentTools\AssignToHumanTool::class),
            app(\App\Modules\Addons\Marketing\Services\AgentTools\ScheduleAppointmentTool::class),
            app(\App\Modules\Addons\Marketing\Services\AgentTools\QueryPlansTool::class),
            app(\App\Modules\Addons\Marketing\Services\AgentTools\CheckCoverageTool::class),
        );
    }
}
