<?php

namespace App\Modules\Core\Release\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditPlanItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditController extends Controller
{
    // ── Plan items ───────────────────────────────────────────────────────────

    public function planIndex(): JsonResponse
    {
        $items = AuditPlanItem::orderBy('orden')->get();

        if ($items->isEmpty()) {
            $items = $this->seedDefaultPlan();
        }

        return response()->json($items);
    }

    public function planToggle(int $id): JsonResponse
    {
        $item = AuditPlanItem::findOrFail($id);
        $item->completado    = ! $item->completado;
        $item->completado_at = $item->completado ? now() : null;
        $item->save();

        return response()->json($item);
    }

    public function planNote(Request $request, int $id): JsonResponse
    {
        $request->validate(['notas' => 'nullable|string|max:500']);
        $item = AuditPlanItem::findOrFail($id);
        $item->notas = $request->notas;
        $item->save();

        return response()->json($item);
    }

    // ── Reporte en vivo ──────────────────────────────────────────────────────

    public function generate(): JsonResponse
    {
        $metricas = [];

        // 1. KPI War Room — ¿qué tabla usa? (mes en curso, no congelado)
        try {
            $periodo    = now();
            $mesLabel   = ucfirst($periodo->locale('es')->translatedFormat('F Y')); // ej. "Julio 2026"

            $invMes = (float) DB::table('invoices')
                ->where('status', 'paid')
                ->whereYear('payment_date', $periodo->year)->whereMonth('payment_date', $periodo->month)
                ->whereNull('deleted_at')->sum('total');

            $ciMes = (float) DB::table('client_invoices')
                ->where('estado', 'Pagado')
                ->whereYear('payment_date', $periodo->year)->whereMonth('payment_date', $periodo->month)
                ->sum('total');

            $metricas['kpi_warroom'] = [
                'estado'    => $invMes > 0 && $ciMes == 0 ? 'error' : 'ok',
                'titulo'    => 'KPI War Room — tabla de facturas',
                'valor'     => number_format($invMes, 0, '.', ','),
                'detalle'   => $invMes > 0 && $ciMes == 0
                    ? "Muestra \$0 en prod. invoices tiene \${$invMes} real en {$mesLabel}. Usar invoices con status='paid'."
                    : "OK — Ingresos {$mesLabel}: \${$invMes}",
                'subtitulo' => "invoices {$mesLabel}: \${$invMes} | client_invoices {$mesLabel}: \${$ciMes}",
            ];
        } catch (\Throwable $e) {
            $metricas['kpi_warroom'] = ['estado' => 'error', 'titulo' => 'KPI War Room', 'detalle' => $e->getMessage()];
        }

        // 2. Videos — worker
        try {
            $total     = DB::table('marketing_generated_content')->where('type', 'video')->count();
            $ready     = DB::table('marketing_generated_content')->where('type', 'video')->where('status', 'ready')->count();
            $atascados = DB::table('marketing_generated_content')->where('type', 'video')->whereIn('status', ['pending', 'generating'])->count();

            $workerExiste = file_exists('/etc/supervisor/conf.d/megaisp-video-render.conf');

            $metricas['videos'] = [
                'estado'    => ($atascados > 0 && ! $workerExiste) ? 'error' : ($atascados > 0 ? 'warning' : 'ok'),
                'titulo'    => 'Videos renderizados',
                'valor'     => "{$ready}/{$total}",
                'detalle'   => $workerExiste
                    ? "Worker video-render activo. {$ready} listos, {$atascados} pendientes."
                    : "Worker video-render NO existe en supervisor. {$atascados} videos atascados.",
                'subtitulo' => "Listos: {$ready} | Atascados: {$atascados} | Total: {$total}",
            ];
        } catch (\Throwable $e) {
            $metricas['videos'] = ['estado' => 'warning', 'titulo' => 'Videos', 'detalle' => $e->getMessage()];
        }

        // 3. Bot WhatsApp — actividad
        try {
            $lastIn  = DB::table('marketing_messages')->where('direction', 'inbound')->max('created_at');
            $lastOut = DB::table('marketing_messages')->where('direction', 'outbound')->max('created_at');
            $total7d = DB::table('marketing_messages')->where('created_at', '>=', now()->subDays(7))->count();

            $metricas['bot'] = [
                'estado'    => $lastOut ? 'ok' : 'warning',
                'titulo'    => 'Bot WhatsApp',
                'valor'     => $lastOut ? 'Respondiendo' : 'Solo inbound',
                'detalle'   => $lastOut
                    ? "Último saliente: {$lastOut}"
                    : "Sin respuestas automáticas. Último entrante: {$lastIn}",
                'subtitulo' => "Mensajes últimos 7 días: {$total7d}",
            ];
        } catch (\Throwable $e) {
            $metricas['bot'] = ['estado' => 'warning', 'titulo' => 'Bot WhatsApp', 'detalle' => $e->getMessage()];
        }

        // 4. Failed jobs
        try {
            $failed = DB::table('failed_jobs')->count();
            $pending = DB::table('jobs')->count();
            $metricas['queue'] = [
                'estado'    => $failed > 0 ? 'warning' : 'ok',
                'titulo'    => 'Cola de trabajos',
                'valor'     => $failed > 0 ? "{$failed} fallidos" : 'Sin errores',
                'detalle'   => "Failed jobs: {$failed} | Pendientes: {$pending}",
                'subtitulo' => $failed > 0 ? 'Ver failed_jobs para detalle' : '',
            ];
        } catch (\Throwable $e) {
            $metricas['queue'] = ['estado' => 'warning', 'titulo' => 'Queue', 'detalle' => $e->getMessage()];
        }

        // 5. Evolution API
        try {
            $apiUrl = env('WHATSAPP_API_URL', '');
            $apiKey = env('WHATSAPP_API_KEY', '');
            if ($apiUrl && $apiKey) {
                $ch = curl_init("{$apiUrl}/instance/fetchInstances");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => ["apikey: {$apiKey}"],
                    CURLOPT_TIMEOUT        => 5,
                ]);
                $body = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $state    = 'desconocido';
                $instName = env('WHATSAPP_DEFAULT_INSTANCE', '');
                if ($code === 200) {
                    $instances = json_decode($body, true) ?? [];
                    foreach ($instances as $inst) {
                        $n = $inst['name'] ?? $inst['instance']['instanceName'] ?? ($inst['instance']['instanceName'] ?? '');
                        $s = $inst['connectionStatus'] ?? $inst['instance']['state'] ?? $inst['state'] ?? '';
                        // Tomar la instancia que coincide, o la primera que esté open
                        if ($n === $instName || ($state === 'desconocido' && $s === 'open')) {
                            $state = $s;
                        }
                    }
                }

                $metricas['evolution'] = [
                    'estado'    => $state === 'open' ? 'ok' : 'error',
                    'titulo'    => 'Evolution API (WhatsApp)',
                    'valor'     => $state === 'open' ? 'Conectado' : strtoupper($state),
                    'detalle'   => "Instancia: {$instName} | Estado: {$state}",
                    'subtitulo' => "HTTP {$code}",
                ];
            } else {
                $metricas['evolution'] = ['estado' => 'warning', 'titulo' => 'Evolution API', 'detalle' => 'Sin config en .env'];
            }
        } catch (\Throwable $e) {
            $metricas['evolution'] = ['estado' => 'warning', 'titulo' => 'Evolution API', 'detalle' => $e->getMessage()];
        }

        // 6. Disco
        try {
            $total = disk_total_space('/var/www');
            $free  = disk_free_space('/var/www');
            $used  = $total - $free;
            $pct   = round($used / $total * 100);
            $metricas['disco'] = [
                'estado'    => $pct >= 90 ? 'error' : ($pct >= 75 ? 'warning' : 'ok'),
                'titulo'    => 'Espacio en disco',
                'valor'     => "{$pct}% usado",
                'detalle'   => round($used / 1024 / 1024 / 1024, 1) . 'G usados de ' . round($total / 1024 / 1024 / 1024, 1) . 'G',
                'subtitulo' => round($free / 1024 / 1024 / 1024, 1) . 'G libres',
            ];
        } catch (\Throwable $e) {
            $metricas['disco'] = ['estado' => 'warning', 'titulo' => 'Disco', 'detalle' => $e->getMessage()];
        }

        // 7. MegaFamilia password
        try {
            $plainText = file_exists(app_path('Modules/Addons/MegaFamilia/Controllers/ApiController.php'))
                && str_contains(
                    file_get_contents(app_path('Modules/Addons/MegaFamilia/Controllers/ApiController.php')),
                    '$data[\'password\'] !== $cmi->password'
                );

            $metricas['megafamilia'] = [
                'estado'    => $plainText ? 'error' : 'ok',
                'titulo'    => 'MegaFamilia — Seguridad de contraseñas',
                'valor'     => $plainText ? 'Plain-text activo' : 'Hash::check',
                'detalle'   => $plainText
                    ? 'ApiController compara passwords en plain-text. Riesgo LFPDPPP con 4,752 usuarios.'
                    : 'Contraseñas con Hash::check correctamente.',
                'subtitulo' => $plainText ? 'Requiere Hash::check' : '',
            ];
        } catch (\Throwable $e) {
            $metricas['megafamilia'] = ['estado' => 'warning', 'titulo' => 'MegaFamilia', 'detalle' => $e->getMessage()];
        }

        // 8. Meta credentials
        try {
            $metaReady = DB::table('api_integrations')
                ->where('provider', 'meta')
                ->where('active', true)
                ->whereNotNull('encrypted_value')
                ->exists();

            $metricas['meta'] = [
                'estado'    => $metaReady ? 'ok' : 'warning',
                'titulo'    => 'Meta (Facebook/Instagram)',
                'valor'     => $metaReady ? 'Configurado' : 'Sin credenciales',
                'detalle'   => $metaReady
                    ? 'Credenciales Meta presentes. Fase 5 publicaciones disponible.'
                    : 'Meta inactivo sin key. Bloquea Fase 5 (FB/IG).',
                'subtitulo' => 'Integration Hub → meta',
            ];
        } catch (\Throwable $e) {
            $metricas['meta'] = ['estado' => 'warning', 'titulo' => 'Meta', 'detalle' => $e->getMessage()];
        }

        return response()->json([
            'generado_at' => now()->format('d/m/Y H:i:s'),
            'metricas'    => array_values($metricas),
        ]);
    }

    // ── Seeder del plan inicial ───────────────────────────────────────────────

    private function seedDefaultPlan(): \Illuminate\Database\Eloquent\Collection
    {
        $items = [
            [
                'titulo'      => 'Fix KpiController → usar tabla invoices',
                'descripcion' => 'El War Room muestra $0 en producción porque client_invoices.payment_date es varchar DD/MM/YYYY. Revertir a invoices con status="paid". Ingresos reales mayo 2026: $767,558.',
                'prioridad'   => 'urgente',
                'categoria'   => 'War Room',
                'orden'       => 1,
            ],
            [
                'titulo'      => 'Crear worker video-render en supervisor',
                'descripcion' => 'No existe megaisp-video-render.conf en /etc/supervisor/conf.d/. Hay 4 videos atascados desde hace 3+ días. Copiar megaisp-cobranza.conf y cambiar la queue.',
                'prioridad'   => 'urgente',
                'categoria'   => 'Infraestructura',
                'orden'       => 2,
            ],
            [
                'titulo'      => 'Configurar credenciales Meta (Facebook/Instagram)',
                'descripcion' => 'Meta inactivo sin key en Integration Hub. Bloquea completamente Fase 5. El código de PublishPostJob, MetaGraphApiService y drivers FB/IG ya está listo.',
                'prioridad'   => 'alta',
                'categoria'   => 'Marketing',
                'orden'       => 3,
            ],
            [
                'titulo'      => 'Verificar WHATSAPP_AUTO_REPLY — bot sin respuestas',
                'descripcion' => 'Evolution API conectada (state=open) pero 0 mensajes outbound. Revisar WHATSAPP_AUTO_REPLY, WHATSAPP_FAKE y confianza mínima en .env para activar auto-respuesta.',
                'prioridad'   => 'alta',
                'categoria'   => 'Marketing',
                'orden'       => 4,
            ],
            [
                'titulo'      => 'MegaFamilia — Hash::check en lugar de plain-text',
                'descripcion' => 'ApiController línea 113 compara $data["password"] !== $cmi->password directamente. 4,752 usuarios en prod con contraseñas base64. Riesgo LFPDPPP activo.',
                'prioridad'   => 'alta',
                'categoria'   => 'Seguridad',
                'orden'       => 5,
            ],
            [
                'titulo'      => 'Revisar y mergear branches sin mergear',
                'descripcion' => 'origin/ajustes-import-bd (2 commits) y origin/migrations-from-meganet (3 commits con módulos supplier y promociones) no están en main. Evaluar si son relevantes.',
                'prioridad'   => 'media',
                'categoria'   => 'Repo',
                'orden'       => 6,
            ],
        ];

        foreach ($items as $item) {
            AuditPlanItem::create($item);
        }

        return AuditPlanItem::orderBy('orden')->get();
    }
}
