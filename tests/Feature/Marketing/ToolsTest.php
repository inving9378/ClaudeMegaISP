<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\Channel;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadActivity;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Services\AgentTools\AssignToHumanTool;
use App\Modules\Addons\Marketing\Services\AgentTools\CheckCoverageTool;
use App\Modules\Addons\Marketing\Services\AgentTools\QueryPlansTool;
use App\Modules\Addons\Marketing\Services\AgentTools\ScheduleAppointmentTool;
use App\Modules\Addons\Marketing\Services\AgentTools\UpdateLeadTool;


class ToolsTest extends MarketingTestCase
{
    protected Lead $lead;
    protected Conversation $conversation;

    public function setUp(): void
    {
        parent::setUp();

        $channel = Channel::firstOrCreate(['code' => 'whatsapp'], [
            'name' => 'WhatsApp Business', 'company_id' => 1, 'active' => true, 'config' => [],
        ]);

        $this->lead = Lead::create([
            'company_id'  => 1,
            'full_name'   => 'Tool Test Lead',
            'phone'       => '5559990000',
            'whatsapp'    => '5559990000',
            'status'      => 'new',
            'captured_at' => now(),
        ]);

        $this->conversation = Conversation::create([
            'company_id'         => 1,
            'lead_id'            => $this->lead->id,
            'channel_id'         => $channel->id,
            'external_thread_id' => '525559990000@s.whatsapp.net',
            'ai_handled'         => true,
            'status'             => 'open',
            'unread_count'       => 0,
        ]);
    }

    // ── UpdateLeadTool ──────────────────────────────────────────────────────────

    public function test_update_lead_with_valid_fields(): void
    {
        $tool   = new UpdateLeadTool();
        $result = $tool->execute($this->lead, [
            'full_name' => 'Juan García',
            'email'     => 'juan@test.com',
            'city'      => 'Querétaro',
        ]);

        $this->assertTrue($result['success']);
        $this->assertContains('full_name', $result['updated_fields']);
        $this->assertContains('email', $result['updated_fields']);

        $this->lead->refresh();
        $this->assertEquals('Juan García', $this->lead->full_name);
        $this->assertEquals('juan@test.com', $this->lead->email);
        $this->assertEquals('Querétaro', $this->lead->city);
    }

    public function test_update_lead_ignores_disallowed_fields(): void
    {
        $tool   = new UpdateLeadTool();
        $result = $tool->execute($this->lead, [
            'full_name' => 'Permitido',
            'status'    => 'hacked',    // Not in allowed list
            'company_id'=> 999,          // Not in allowed list
        ]);

        $this->assertTrue($result['success']);
        $this->assertContains('full_name', $result['updated_fields']);

        $this->lead->refresh();
        $this->assertEquals('new', $this->lead->status); // unchanged
        $this->assertEquals(1, $this->lead->company_id);  // unchanged
    }

    public function test_update_lead_with_no_valid_fields_returns_error(): void
    {
        $tool   = new UpdateLeadTool();
        $result = $tool->execute($this->lead, ['status' => 'hacked', 'company_id' => 999]);

        $this->assertFalse($result['success']);
    }

    public function test_update_lead_tags_accepts_array(): void
    {
        $tool = new UpdateLeadTool();
        $tool->execute($this->lead, ['tags' => ['fibra', 'residencial', 'caliente']]);

        $this->lead->refresh();
        $this->assertIsArray($this->lead->tags);
        $this->assertContains('fibra', $this->lead->tags);
    }

    // ── AssignToHumanTool ───────────────────────────────────────────────────────

    public function test_assign_to_human_updates_conversation(): void
    {
        $tool   = new AssignToHumanTool();
        $result = $tool->execute($this->conversation, 'Cliente solicita hablar con humano');

        $this->assertTrue($result['success']);

        $this->conversation->refresh();
        $this->assertFalse($this->conversation->ai_handled);
        $this->assertEquals('human_review', $this->conversation->status);
        $this->assertEquals('Cliente solicita hablar con humano', $this->conversation->metadata['handoff_reason']);
    }

    public function test_assign_to_human_creates_lead_activity(): void
    {
        $tool = new AssignToHumanTool();
        $tool->execute($this->conversation, 'Escalamiento por queja');

        $this->assertDatabaseHas('marketing_lead_activities', [
            'lead_id' => $this->lead->id,
            'type'    => 'assigned',
        ]);
    }

    // ── ScheduleAppointmentTool ─────────────────────────────────────────────────

    public function test_schedule_valid_appointment(): void
    {
        $tool   = new ScheduleAppointmentTool();
        $result = $tool->execute($this->lead, '2026-06-15 10:00', 'installation', 'Llevar equipo');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('appointment_id', $result);
        $this->assertStringContainsString('15', $result['confirmation_text']);

        $this->assertDatabaseHas('marketing_lead_activities', [
            'lead_id' => $this->lead->id,
            'type'    => 'meeting',
        ]);
    }

    public function test_schedule_appointment_invalid_type(): void
    {
        $tool   = new ScheduleAppointmentTool();
        $result = $tool->execute($this->lead, '2026-06-15 10:00', 'picnic');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('inválido', $result['error']);
    }

    public function test_schedule_appointment_invalid_datetime(): void
    {
        $tool   = new ScheduleAppointmentTool();
        $result = $tool->execute($this->lead, 'not-a-date', 'call');

        $this->assertFalse($result['success']);
    }

    // ── CheckCoverageTool ───────────────────────────────────────────────────────

    public function test_check_coverage_with_no_zones_configured(): void
    {
        // Ensure no coverage_zones setting
        Setting::where('key', 'coverage_zones')->delete();

        $tool   = new CheckCoverageTool();
        $result = $tool->execute('Calle Principal 123', 'Centro');

        $this->assertNull($result['has_coverage']);
        $this->assertStringContainsString('no configurado', $result['message']);
    }

    public function test_check_coverage_finds_match(): void
    {
        Setting::updateOrCreate(
            ['key' => 'coverage_zones', 'company_id' => 1],
            ['group' => 'general', 'value' => '["Centro","Jardines","Las Flores"]', 'encrypted' => false]
        );

        $tool   = new CheckCoverageTool();
        $result = $tool->execute('Calle 5', 'Centro', 'Querétaro');

        $this->assertTrue($result['has_coverage']);
        $this->assertEquals('Centro', $result['zone']);
    }

    public function test_check_coverage_degrades_to_confirm_with_team_when_no_exact_match(): void
    {
        Setting::updateOrCreate(
            ['key' => 'coverage_zones', 'company_id' => 1],
            ['group' => 'general', 'value' => '["Zona Norte","Zona Sur"]', 'encrypted' => false]
        );

        $tool   = new CheckCoverageTool();
        $result = $tool->execute('Calle Desconocida 99', 'Colonia Lejana', 'Otro Estado');

        $this->assertNull($result['has_coverage']);
        $this->assertStringContainsString('confirmar', $result['message']);
    }

    public function test_check_coverage_does_not_match_by_partial_substring(): void
    {
        Setting::updateOrCreate(
            ['key' => 'coverage_zones', 'company_id' => 1],
            ['group' => 'general', 'value' => '["Roma Norte"]', 'encrypted' => false]
        );

        $tool   = new CheckCoverageTool();
        $result = $tool->execute('Calle 5', 'Roma', 'CDMX');

        $this->assertNull($result['has_coverage']);
    }

    // ── QueryPlansTool ──────────────────────────────────────────────────────────

    public function test_query_plans_returns_graceful_error_when_table_missing(): void
    {
        $tool   = new QueryPlansTool();
        $result = $tool->execute();

        // Either table exists or graceful error
        if (!$result['success']) {
            $this->assertStringContainsString('no configurado', $result['error']);
        } else {
            $this->assertIsArray($result['plans']);
        }
    }
}
