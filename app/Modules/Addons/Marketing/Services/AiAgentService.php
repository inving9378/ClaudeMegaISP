<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Models\Marketing\AiAgentConfig;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Lead;
use App\Models\Marketing\Message;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Services\AgentTools\AssignToHumanTool;
use App\Modules\Addons\Marketing\Services\AgentTools\CheckCoverageTool;
use App\Modules\Addons\Marketing\Services\AgentTools\QueryPlansTool;
use App\Modules\Addons\Marketing\Services\AgentTools\ScheduleAppointmentTool;
use App\Modules\Addons\Marketing\Services\AgentTools\UpdateLeadTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AiAgentService
{
    public function __construct(
        protected ClaudeApiClient       $claude,
        protected PromptBuilder         $promptBuilder,
        protected UpdateLeadTool        $updateLeadTool,
        protected AssignToHumanTool     $assignTool,
        protected ScheduleAppointmentTool $scheduleTool,
        protected QueryPlansTool        $plansTool,
        protected CheckCoverageTool     $coverageTool,
    ) {}

    public function respondTo(Conversation $conversation, Message $incomingMessage): ?string
    {
        // Load config for whatsapp channel
        $config = AiAgentConfig::where('channel_id', $conversation->channel_id)
            ->where('active', true)
            ->first();

        if (!$config) {
            Log::channel('claude')->error("No AiAgentConfig for channel_id={$conversation->channel_id}");
            $this->assignTool->execute($conversation, 'Sin configuración de agente IA');
            return null;
        }

        if (!$conversation->ai_handled) {
            return null;
        }

        $lead        = $conversation->lead;
        $systemPrompt = $this->promptBuilder->buildSystemPrompt($config, $lead, $conversation);
        $messages    = $this->promptBuilder->buildMessages($conversation);

        if (empty($messages)) {
            return null;
        }

        $maxTurns     = (int) (Setting::get('agent_max_turns_safety', 1) ?? 20);
        $totalCost    = 0.0;
        $budget       = (float) (Setting::get('agent_monthly_budget_usd', 1) ?? 50.0);
        $enabledTools = $config->tools_enabled ?? array_keys($this->allTools());

        $model = $config->model ?? 'claude-opus-4-7';
        $params = [
            'model'      => $model,
            'max_tokens' => (int) ($config->max_tokens ?? 1024),
            'system'     => $systemPrompt,
            'tools'      => $this->getToolsSchema($enabledTools),
            'messages'   => $messages,
        ];
        // temperature is deprecated in Claude 4+ models
        if (!str_starts_with($model, 'claude-opus-4') && !str_starts_with($model, 'claude-sonnet-4') && !str_starts_with($model, 'claude-haiku-4')) {
            $params['temperature'] = (float) ($config->temperature ?? 0.7);
        }

        try {
            $turnsUsed = 0;
            $finalText = null;

            while ($turnsUsed < $maxTurns) {
                $response = $this->claude->messages($params);
                $usage    = $response['usage'] ?? [];
                $cost     = $this->claude->calculateCost($usage, $params['model']);
                $totalCost += $cost;

                $this->recordCost($conversation, $usage, $cost, $params['model'], $turnsUsed);
                $this->checkBudget($totalCost, $budget, $conversation);

                $stopReason = $response['stop_reason'] ?? 'end_turn';
                $content    = $response['content'] ?? [];

                if ($stopReason === 'end_turn') {
                    $finalText = $this->extractText($content);
                    break;
                }

                if ($stopReason === 'tool_use') {
                    $toolResults = [];
                    foreach ($content as $block) {
                        if (($block['type'] ?? '') !== 'tool_use') {
                            continue;
                        }
                        $toolResult = $this->executeTool(
                            $block['name'],
                            $block['input'] ?? [],
                            $lead,
                            $conversation
                        );
                        $toolResults[] = [
                            'type'       => 'tool_result',
                            'tool_use_id'=> $block['id'],
                            'content'    => json_encode($toolResult),
                        ];
                    }

                    // Ensure tool_use inputs are JSON objects not arrays (PHP decodes {} as [])
                    $assistantContent = array_map(function ($block) {
                        if (($block['type'] ?? '') === 'tool_use') {
                            $block['input'] = empty($block['input']) ? new \stdClass() : $block['input'];
                        }
                        return $block;
                    }, $content);

                    $params['messages'][] = ['role' => 'assistant', 'content' => $assistantContent];
                    $params['messages'][] = ['role' => 'user',      'content' => $toolResults];
                    $turnsUsed++;
                    continue;
                }

                // Unexpected stop reason
                $finalText = $this->extractText($content);
                break;
            }

            if ($turnsUsed >= $maxTurns) {
                Log::channel('claude')->warning("Agent exceeded max turns ({$maxTurns}) for conversation {$conversation->id}");
                $this->assignTool->execute($conversation, 'Límite de turnos de IA alcanzado — revisión manual requerida');
                return null;
            }

            return $finalText;

        } catch (\Throwable $e) {
            Log::channel('claude')->error("AiAgent error: {$e->getMessage()}", ['conversation_id' => $conversation->id]);
            if (Setting::get('agent_handoff_on_failure') === 'true') {
                $this->assignTool->execute($conversation, 'Error del agente IA: ' . $e->getMessage());
            }
            return null;
        }
    }

    // ── Tools ───────────────────────────────────────────────────────────────────

    private function allTools(): array
    {
        return [
            'update_lead'          => fn ($input, $lead, $conv) => $this->updateLeadTool->execute($lead, $input['fields'] ?? []),
            'assign_to_human'      => fn ($input, $lead, $conv) => $this->assignTool->execute($conv, $input['reason'] ?? 'Sin razón', $input['user_id'] ?? null),
            'schedule_appointment' => fn ($input, $lead, $conv) => $this->scheduleTool->execute($lead, $input['datetime'] ?? '', $input['type'] ?? 'call', $input['notes'] ?? null),
            'query_plans'          => fn ($input, $lead, $conv) => $this->plansTool->execute($input['zone'] ?? null, $input['max_price'] ?? null, $input['min_speed'] ?? null),
            'check_coverage'       => fn ($input, $lead, $conv) => $this->coverageTool->execute($input['address'] ?? '', $input['neighborhood'] ?? null, $input['city'] ?? null),
        ];
    }

    private function executeTool(string $name, array $input, Lead $lead, Conversation $conversation): array
    {
        $tools = $this->allTools();
        if (!isset($tools[$name])) {
            return ['error' => "Tool desconocida: {$name}"];
        }
        try {
            return $tools[$name]($input, $lead, $conversation);
        } catch (\Throwable $e) {
            Log::channel('claude')->error("Tool {$name} error: {$e->getMessage()}");
            return ['error' => "Error ejecutando {$name}: " . $e->getMessage()];
        }
    }

    private function getToolsSchema(array $enabled = []): array
    {
        $all = [
            UpdateLeadTool::schema(),
            AssignToHumanTool::schema(),
            ScheduleAppointmentTool::schema(),
            QueryPlansTool::schema(),
            CheckCoverageTool::schema(),
        ];

        if (empty($enabled)) {
            return $all;
        }

        return array_values(array_filter($all, fn ($t) => in_array($t['name'], $enabled)));
    }

    private function extractText(array $content): string
    {
        return implode('', array_map(
            fn ($block) => ($block['type'] === 'text') ? ($block['text'] ?? '') : '',
            $content
        ));
    }

    private function recordCost(Conversation $conv, array $usage, float $cost, string $model, int $turn): void
    {
        GeneratedContent::create([
            'company_id'          => $conv->company_id ?? 1,
            'type'                => 'ai_response',
            'generation_engine'   => $model,
            'generation_cost_usd' => $cost,
            'generation_metadata' => [
                'conversation_id' => $conv->id,
                'turn'            => $turn,
                'input_tokens'    => $usage['input_tokens'] ?? 0,
                'output_tokens'   => $usage['output_tokens'] ?? 0,
            ],
            'status'              => 'completed',
        ]);
    }

    private function checkBudget(float $totalCost, float $budget, Conversation $conversation): void
    {
        // Check monthly spend
        $monthlySpend = GeneratedContent::where('type', 'ai_response')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('generation_cost_usd');

        if ($monthlySpend > $budget) {
            Log::channel('claude')->warning("Monthly budget exceeded: \${$monthlySpend} > \${$budget}");
            $this->assignTool->execute($conversation, 'Presupuesto mensual de IA agotado — revisión manual');
            throw new \RuntimeException('Budget exceeded');
        }
    }
}
