<?php

namespace App\Modules\Addons\WarRoom\Services;

use App\Modules\Addons\WarRoom\Models\InsightsCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InsightsService
{
    public function generate(string $viewKey, string $period): InsightsCache
    {
        $kpis    = $this->fetchKpis($viewKey, $period);
        $context = $this->buildContext($viewKey, $period, $kpis);

        try {
            $insights = $this->generateWithAi($context);
            $source   = 'ai';
        } catch (\Throwable $e) {
            Log::warning('InsightsService: Claude API falló, usando reglas', ['err' => $e->getMessage()]);
            $insights = $this->generateWithRules($viewKey, $kpis);
            $source   = 'rules';
        }

        return InsightsCache::updateOrCreate(
            ['view_key' => $viewKey, 'period' => $period],
            ['insights' => $insights, 'source' => $source, 'generated_at' => now()]
        );
    }

    private function fetchKpis(string $viewKey, string $period): array
    {
        $previous = Carbon::createFromFormat('Y-m', $period)->subMonth()->format('Y-m');
        [$cy, $cm] = explode('-', $period);
        [$py, $pm] = explode('-', $previous);

        return match ($viewKey) {
            'finanzas' => [
                'ingresos_actual'   => $this->ingresosDelPeriodo($period),
                'ingresos_anterior' => $this->ingresosDelPeriodo($previous),
                'clientes_nuevos'   => DB::table('clients')->whereYear('created_at', $cy)->whereMonth('created_at', $cm)->whereNull('deleted_at')->count(),
                'facturas_vencidas' => DB::table('client_invoices')->whereIn('estado', ['Atrasado', 'impagado'])->count(),
            ],
            'operaciones' => [
                'tickets_actual'      => DB::table('tasks')->whereYear('created_at', $cy)->whereMonth('created_at', $cm)->whereNull('deleted_at')->count(),
                'tickets_anterior'    => DB::table('tasks')->whereYear('created_at', $py)->whereMonth('created_at', $pm)->whereNull('deleted_at')->count(),
                'tickets_pendientes'  => DB::table('tasks')->whereNull('deleted_at')->whereIn('status', ['ToDo', 'InProgress'])->count(),
                'tickets_cerrados'    => DB::table('tasks')->whereYear('updated_at', $cy)->whereMonth('updated_at', $cm)->whereNull('deleted_at')->where('status', 'Done')->count(),
            ],
            'red' => [
                'onus_total'   => DB::table('olt_onus')->count(),
                'onus_online'  => DB::table('olt_onus')->where('status', 'Online')->count(),
                'onus_offline' => DB::table('olt_onus')->whereIn('status', ['Offline', 'Power fail', 'LOS'])->count(),
                'olts_activas' => DB::table('olts')->where('status', 'active')->count(),
            ],
            'ventas' => [
                'clientes_nuevos'   => DB::table('clients')->whereYear('created_at', $cy)->whereMonth('created_at', $cm)->whereNull('deleted_at')->count(),
                'clientes_nuevos_p' => DB::table('clients')->whereYear('created_at', $py)->whereMonth('created_at', $pm)->whereNull('deleted_at')->count(),
            ],
            'marketing' => [
                'publicaciones'    => DB::table('marketing_publications')->where('status', 'published')->whereYear('created_at', $cy)->whereMonth('created_at', $cm)->count(),
                'campanias_activas'=> DB::table('marketing_campaigns')->where('status', 'active')->count(),
                'leads_captados'   => DB::table('marketing_leads')->whereYear('created_at', $cy)->whereMonth('created_at', $cm)->count(),
                'leads_ganados'    => DB::table('marketing_leads')->whereYear('created_at', $cy)->whereMonth('created_at', $cm)->where('status', 'won')->count(),
            ],
            default => [],
        };
    }

    private function buildContext(string $viewKey, string $period, array $kpis): string
    {
        $kpisJson = json_encode($kpis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return "Eres analista de operaciones de un ISP en México (MegaISP). "
             . "Analiza los KPIs de la sección '{$viewKey}' del periodo {$period}:\n\n"
             . $kpisJson . "\n\n"
             . "Genera exactamente 3 insights breves y accionables en español neutro.\n"
             . "Responde SOLO con un array JSON válido, sin explicaciones adicionales:\n"
             . '['
             . '{"type":"positivo","text":"..."},'
             . '{"type":"atencion","text":"..."},'
             . '{"type":"oportunidad","text":"..."}'
             . "]\n"
             . "Tipos permitidos: positivo, atencion, oportunidad. "
             . "Máximo 25 palabras por insight. No uses markdown ni texto fuera del JSON.";
    }

    private function generateWithAi(string $prompt): array
    {
        $apiKey = config('services.claude.api_key', env('CLAUDE_API_KEY'));
        $model  = config('services.claude.model', env('CLAUDE_MODEL', 'claude-sonnet-4-6'));

        if (! $apiKey) {
            throw new \RuntimeException('Sin CLAUDE_API_KEY');
        }

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $model,
            'max_tokens' => 400,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Claude API error: ' . $response->status());
        }

        $text = trim($response->json('content.0.text', ''));

        // Extraer el array JSON de la respuesta
        if (preg_match('/\[.*\]/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded) && count($decoded) > 0) {
                return $decoded;
            }
        }

        throw new \RuntimeException('Respuesta de Claude no es JSON válido: ' . substr($text, 0, 200));
    }

    private function generateWithRules(string $viewKey, array $kpis): array
    {
        return match ($viewKey) {
            'finanzas' => $this->finanzasRules($kpis),
            'operaciones' => $this->operacionesRules($kpis),
            'red' => $this->redRules($kpis),
            'ventas' => $this->ventasRules($kpis),
            'marketing' => $this->marketingRules($kpis),
            default => [
                ['type' => 'atencion', 'text' => 'No hay datos suficientes para generar insights automáticos en este periodo.'],
            ],
        };
    }

    private function finanzasRules(array $k): array
    {
        $diff    = ($k['ingresos_anterior'] ?? 0) > 0
            ? round((($k['ingresos_actual'] - $k['ingresos_anterior']) / $k['ingresos_anterior']) * 100, 1)
            : 0;
        $tipo    = $diff >= 0 ? 'positivo' : 'atencion';
        $insight = $diff >= 0
            ? "Ingresos {$diff}% superiores al mes anterior."
            : "Ingresos {$diff}% por debajo del mes anterior. Revisar causas.";

        $vencidas = $k['facturas_vencidas'] ?? 0;
        $cobro    = $vencidas > 0
            ? ['type' => 'atencion', 'text' => "{$vencidas} facturas vencidas o impagas. Priorizar recuperación de cartera."]
            : ['type' => 'positivo', 'text' => 'Sin facturas vencidas este periodo. Excelente gestión de cobranza.'];

        return [
            ['type' => $tipo, 'text' => $insight],
            $cobro,
            ['type' => 'oportunidad', 'text' => "Se captaron {$k['clientes_nuevos']} clientes nuevos. Asegurar facturación correcta desde el inicio."],
        ];
    }

    private function operacionesRules(array $k): array
    {
        $pendientes = $k['tickets_pendientes'] ?? 0;
        $tipo       = $pendientes > 20 ? 'atencion' : 'positivo';
        return [
            ['type' => $tipo, 'text' => "{$pendientes} tickets pendientes (ToDo/InProgress). " . ($pendientes > 20 ? 'Carga alta, revisar asignación.' : 'Carga dentro de parámetros.')],
            ['type' => 'positivo', 'text' => "{$k['tickets_cerrados']} tickets cerrados este periodo. Buen ritmo de resolución."],
            ['type' => 'oportunidad', 'text' => 'Revisar tickets sin asignar y priorizar por impacto al cliente.'],
        ];
    }

    private function redRules(array $k): array
    {
        $pct = ($k['onus_total'] ?? 0) > 0
            ? round(($k['onus_online'] / $k['onus_total']) * 100, 1) : 0;
        $tipo = $pct >= 95 ? 'positivo' : ($pct >= 85 ? 'atencion' : 'atencion');
        return [
            ['type' => $tipo, 'text' => "{$pct}% de ONUs en línea ({$k['onus_online']}/{$k['onus_total']}). " . ($pct >= 95 ? 'Red estable.' : 'Investigar ONUs caídas.')],
            ['type' => 'atencion', 'text' => "{$k['onus_offline']} ONUs offline. Revisar Power fail y LOS para intervención preventiva."],
            ['type' => 'oportunidad', 'text' => "Con {$k['olts_activas']} OLTs activas, evaluar capacidad para nuevas conexiones."],
        ];
    }

    private function ventasRules(array $k): array
    {
        $diff = ($k['clientes_nuevos_p'] ?? 0) > 0
            ? round((($k['clientes_nuevos'] - $k['clientes_nuevos_p']) / $k['clientes_nuevos_p']) * 100, 1) : 0;
        $tipo = $diff >= 0 ? 'positivo' : 'atencion';
        return [
            ['type' => $tipo, 'text' => "{$k['clientes_nuevos']} clientes nuevos. " . ($diff >= 0 ? "Crecimiento del {$diff}% vs mes anterior." : "Caída del " . abs($diff) . "% vs mes anterior.")],
            ['type' => 'oportunidad', 'text' => 'Verificar que clientes captados estén correctamente instalados y facturados.'],
            ['type' => 'oportunidad', 'text' => 'Revisar leads perdidos para identificar patrones de churn temprano.'],
        ];
    }

    private function marketingRules(array $k): array
    {
        return [
            ['type' => 'positivo',     'text' => "{$k['publicaciones']} publicaciones enviadas este periodo."],
            ['type' => 'atencion',     'text' => "{$k['leads_captados']} leads captados, {$k['leads_ganados']} convertidos. Revisar tasa de conversión."],
            ['type' => 'oportunidad',  'text' => "{$k['campanias_activas']} campañas activas. Optimizar segmentación para aumentar leads ganados."],
        ];
    }

    private function ingresosDelPeriodo(string $period): float
    {
        [$y, $m] = explode('-', $period);
        return (float) DB::table('client_invoices')
            ->where('estado', 'Pagado')
            ->whereYear('payment_date', $y)
            ->whereMonth('payment_date', $m)
            ->sum('total');
    }
}
