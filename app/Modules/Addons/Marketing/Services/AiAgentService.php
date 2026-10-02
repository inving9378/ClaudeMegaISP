<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Models\Marketing\AiAgentConfig;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Lead;
use App\Models\Marketing\Message;
use App\Models\Marketing\Setting;
use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Addons\IA\Services\IAPricingService;
use App\Modules\Addons\Marketing\Services\AgentTools\AssignToHumanTool;
use App\Modules\Addons\Marketing\Services\AgentTools\CheckCoverageTool;
use App\Modules\Addons\Marketing\Services\AgentTools\QueryPlansTool;
use App\Modules\Addons\Marketing\Services\AgentTools\ScheduleAppointmentTool;
use App\Modules\Addons\Marketing\Services\AgentTools\UpdateLeadTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Agente de WhatsApp (Evolution) con herramientas. La IA (integración + modelo) se
 * decide en Integraciones → Módulos IA (clave marketing.agente_whatsapp); habla en el
 * formato NEUTRO de herramientas de IA::conversar(), así funciona con Claude, OpenAI
 * o Gemini. AiAgentConfig sigue mandando prompt, herramientas activas, max_tokens y
 * temperatura; su campo `model` ya no se usa (el modelo es el de la asignación).
 */
class AiAgentService
{
    private const CLAVE_IA = 'marketing.agente_whatsapp';

    public function __construct(
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
            Log::error("No AiAgentConfig for channel_id={$conversation->channel_id}");
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

        try {
            $proveedor = IA::proveedorPara(self::CLAVE_IA);
        } catch (IANoConfigurada $e) {
            Log::warning("AiAgent sin IA asignada: {$e->getMessage()}", ['conversation_id' => $conversation->id]);
            $this->assignTool->execute($conversation, 'Sin IA asignada al agente de WhatsApp (Integraciones → Módulos IA)');
            return null;
        }

        $opciones = ['max_tokens' => (int) ($config->max_tokens ?? 1024)];
        // Los modelos Claude 4 no aceptan temperature junto con su configuración por defecto
        // (mismo criterio que antes, ahora sobre el modelo asignado).
        if (!preg_match('/^claude-(opus|sonnet|haiku)-4/', (string) $proveedor->modelo_default)) {
            $opciones['temperatura'] = (float) ($config->temperature ?? 0.7);
        }

        // Historial del PromptBuilder ({role, content}) → formato neutro ({rol, contenido}).
        $mensajes = array_map(fn ($m) => ['rol' => $m['role'], 'contenido' => (string) $m['content']], $messages);
        $herramientas = $this->getToolsSchema($enabledTools);

        try {
            $turnsUsed = 0;
            $finalText = null;

            while ($turnsUsed < $maxTurns) {
                $r    = IA::conversar(self::CLAVE_IA, $mensajes, $systemPrompt, $herramientas, $opciones);
                $cost = app(IAPricingService::class)->calcularCosto((string) $r['modelo'], (int) ($r['tokens_input'] ?? 0), (int) ($r['tokens_output'] ?? 0));
                $totalCost += $cost;

                $this->recordCost($conversation, $r, $cost, $turnsUsed);
                $this->checkBudget($totalCost, $budget, $conversation);

                if ($r['fin'] !== 'herramientas') {
                    $finalText = $r['texto'];
                    break;
                }

                // La IA pidió herramientas: se ejecutan y se le devuelven los resultados.
                $mensajes[] = ['rol' => 'assistant', 'contenido' => $r['texto'], 'llamadas' => $r['llamadas']];
                foreach ($r['llamadas'] as $llamada) {
                    $resultado = $this->executeTool($llamada['nombre'], $llamada['argumentos'], $lead, $conversation);
                    $mensajes[] = [
                        'rol'       => 'herramienta',
                        'id'        => $llamada['id'],
                        'nombre'    => $llamada['nombre'],
                        'resultado' => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                    ];
                }
                $turnsUsed++;
            }

            if ($turnsUsed >= $maxTurns) {
                Log::warning("Agent exceeded max turns ({$maxTurns}) for conversation {$conversation->id}");
                $this->assignTool->execute($conversation, 'Límite de turnos de IA alcanzado — revisión manual requerida');
                return null;
            }

            return $finalText;

        } catch (\Throwable $e) {
            Log::error("AiAgent error: {$e->getMessage()}", ['conversation_id' => $conversation->id]);
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
            Log::error("Tool {$name} error: {$e->getMessage()}");
            return ['error' => "Error ejecutando {$name}: " . $e->getMessage()];
        }
    }

    /** Herramientas en formato neutro (los schema() de cada tool usan name/description/input_schema). */
    private function getToolsSchema(array $enabled = []): array
    {
        $all = [
            UpdateLeadTool::schema(),
            AssignToHumanTool::schema(),
            ScheduleAppointmentTool::schema(),
            QueryPlansTool::schema(),
            CheckCoverageTool::schema(),
        ];

        if (!empty($enabled)) {
            $all = array_filter($all, fn ($t) => in_array($t['name'], $enabled));
        }

        return array_values(array_map(fn ($t) => [
            'nombre'      => $t['name'],
            'descripcion' => $t['description'] ?? '',
            'parametros'  => $t['input_schema'] ?? [],
        ], $all));
    }

    private function recordCost(Conversation $conv, array $r, float $cost, int $turn): void
    {
        GeneratedContent::create([
            'company_id'          => $conv->company_id ?? 1,
            'type'                => 'ai_response',
            'generation_engine'   => (string) $r['modelo'],
            'generation_cost_usd' => $cost,
            'generation_metadata' => [
                'conversation_id' => $conv->id,
                'turn'            => $turn,
                'provider'        => $r['proveedor'] ?? null,
                'input_tokens'    => $r['tokens_input'] ?? 0,
                'output_tokens'   => $r['tokens_output'] ?? 0,
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
            Log::warning("Monthly budget exceeded: \${$monthlySpend} > \${$budget}");
            $this->assignTool->execute($conversation, 'Presupuesto mensual de IA agotado — revisión manual');
            throw new \RuntimeException('Budget exceeded');
        }
    }
}
