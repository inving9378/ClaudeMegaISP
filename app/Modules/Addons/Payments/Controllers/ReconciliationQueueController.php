<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Payments\Models\ReportedPayment;
use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Services\Conciliation\PaymentFromSessionService;
use App\Modules\Addons\Payments\Services\Identification\SubscriberSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * FASE 6 — Cola de conciliación de Tere (revisión humana).
 *
 * UNA cola unificada con 3 pestañas:
 *   - PROPUESTOS: la IA identificó al cliente (proposed) o hay multi-servicio →
 *     Tere confirma (aplica vía Fase 4) o rechaza. Trabajo rápido.
 *   - ESCALADOS: la IA NO identificó → Tere identifica manual y aplica, o rechaza.
 *   - VERIFICACIÓN: pagos ya aplicados pendientes de cruce bancario
 *     (reported_payments.pendiente_verificar) — solo lectura por ahora.
 *
 * SEGURIDAD: CONFIRMAR llama al motor de Fase 4 (applyConfirmed) que respeta el
 * anti-duplicado (add_by=MEGAISP, confirmed_by=Tere). El envío por WhatsApp sigue
 * apagado — confirmar NO notifica al cliente. Gateada por permiso conciliacion.manage.
 */
class ReconciliationQueueController extends Controller
{
    private const MEDIA_PREFIX = 'private/payments/whatsapp/comprobantes/';

    public function __construct(private PaymentFromSessionService $applier) {}

    public function index()
    {
        return view('addon-payments::conciliacion-cola', [
            'counts' => [
                'propuesto'    => Session::proposedQueue()->count(),
                'escalado'     => Session::escalatedQueue()->count(),
                'verificacion' => ReportedPayment::where('conciliation_status', ReportedPayment::ESTADO_PENDIENTE)->count(),
            ],
        ]);
    }

    /**
     * Conteo LIGERO de pendientes (propuestos + escalados) para la campana de la
     * topbar. Solo COUNT, no trae datos. Fuente única = las sesiones → cuando
     * alguien concilia/rechaza, el conteo baja para todos en su próxima consulta.
     */
    public function pendingCount()
    {
        return response()->json([
            'count' => Session::proposedQueue()->count() + Session::escalatedQueue()->count(),
        ]);
    }

    /** Lista por tipo. */
    public function list(Request $request)
    {
        $type = $request->input('type', 'propuesto');

        if ($type === 'verificacion') {
            $rows = ReportedPayment::where('conciliation_status', ReportedPayment::ESTADO_PENDIENTE)
                ->latest('id')->limit(100)->get()
                ->map(fn ($r) => [
                    'reported_payment_id' => $r->id,
                    'client_id'           => $r->client_id,
                    'amount'              => $r->amount,
                    'clave_rastreo'       => $r->clave_rastreo,
                    'fecha_pago'          => $r->fecha_pago,
                ]);
            return response()->json(['type' => $type, 'rows' => $rows, 'readonly' => true]);
        }

        // HISTORIAL — aprobados Y rechazados de este flujo (solo lectura).
        // estado = todos | aprobados | rechazados. from/to = rango de fecha.
        if ($type === 'historial') {
            $estado = $request->input('estado', 'todos');
            $from   = $request->input('from');
            $to     = $request->input('to');
            $rows   = collect();

            // ── Aprobados (reported_payments de este flujo) ──
            if ($estado !== 'rechazados') {
                $q = ReportedPayment::whereNotNull('identification_session_id');
                if ($from) { $q->whereDate('created_at', '>=', $from); }
                if ($to)   { $q->whereDate('created_at', '<=', $to); }
                $reported = $q->latest('id')->limit(300)->get();
                $fechas = DB::table('whatsapp_identification_sessions as s')
                    ->join('whatsapp_payment_extractions as e', 'e.id', '=', 's.extraction_id')
                    ->whereIn('s.id', $reported->pluck('identification_session_id')->filter())
                    ->pluck('e.fecha_pago', 's.id');
                foreach ($reported as $r) {
                    $rows->push([
                        'kind'          => 'aprobado',
                        'client'        => $this->clientInfo((int) $r->client_id),
                        'amount'        => $r->amount,
                        'fecha_pago'    => $fechas[$r->identification_session_id] ?? (string) $r->fecha_pago,
                        'clave_rastreo' => $r->clave_rastreo,
                        'auto'          => empty($r->confirmed_by_user_id),
                        'identified_by' => $this->userName($r->identified_by_user_id),
                        'confirmed_by'  => $r->confirmed_by_user_id ? $this->userName($r->confirmed_by_user_id) : null,
                        'when'          => optional($r->created_at)->format('Y-m-d H:i'),
                        'when_ts'       => optional($r->created_at)->timestamp ?? 0,
                    ]);
                }
            }

            // ── Rechazados (sesiones con rejected_at) ──
            if ($estado !== 'aprobados') {
                $q = Session::where('is_simulation', false)->whereNotNull('rejected_at');
                if ($from) { $q->whereDate('rejected_at', '>=', $from); }
                if ($to)   { $q->whereDate('rejected_at', '<=', $to); }
                $rej = $q->latest('rejected_at')->limit(300)->get();
                $ext = DB::table('whatsapp_payment_extractions')
                    ->whereIn('id', $rej->pluck('extraction_id')->filter())->get()->keyBy('id');
                foreach ($rej as $s) {
                    $e = $ext->get($s->extraction_id);
                    $f = $e && $e->fields ? json_decode($e->fields, true) : [];
                    $rows->push([
                        'kind'          => 'rechazado',
                        'client'        => $s->resolved_client_id ? $this->clientInfo((int) $s->resolved_client_id) : null,
                        'amount'        => $f['monto']['value'] ?? null,
                        'fecha_pago'    => $f['fecha_pago']['value'] ?? null,
                        'clave_rastreo' => $f['clave_rastreo']['value'] ?? null,
                        'reject_reason' => $s->reject_reason,
                        'rejected_by'   => $this->userName($s->rejected_by),
                        'when'          => optional($s->rejected_at)->format('Y-m-d H:i'),
                        'when_ts'       => optional($s->rejected_at)->timestamp ?? 0,
                    ]);
                }
            }

            return response()->json([
                'type'     => 'historial',
                'rows'     => $rows->sortByDesc('when_ts')->values(),
                'readonly' => true,
            ]);
        }

        $sessions = ($type === 'escalado' ? Session::escalatedQueue() : Session::proposedQueue())
            ->latest('id')->limit(100)->get();

        return response()->json([
            'type' => $type,
            'rows' => $sessions->map(fn ($s) => $this->rowSummary($s)),
        ]);
    }

    /** Detalle de un caso (comprobante + datos + cliente propuesto + servicios). */
    public function show(int $sessionId)
    {
        $s = Session::findOrFail($sessionId);
        $ext = $s->extraction_id ? DB::table('whatsapp_payment_extractions')->where('id', $s->extraction_id)->first() : null;
        $fields = $ext && $ext->fields ? json_decode($ext->fields, true) : [];

        $client   = $s->resolved_client_id ? $this->clientInfo($s->resolved_client_id) : null;
        $services = $s->resolved_client_id ? $this->clientServices($s->resolved_client_id) : [];

        return response()->json([
            'id'                 => $s->id,
            'state'              => $s->state,
            'method'             => $s->method,
            'certainty'          => $s->certainty,
            'multiple_services'  => (bool) $s->resolved_multiple_services,
            'fields'             => $fields,
            'client'             => $client,
            'services'           => $services,
            'has_media'          => $ext && $this->mediaPath($s),
            'media_ext'          => $this->mediaPath($s) ? strtolower(pathinfo($this->mediaPath($s), PATHINFO_EXTENSION)) : null,
        ]);
    }

    /** Sirve el comprobante (imagen/PDF) del caso. */
    public function media(int $sessionId)
    {
        $s = Session::findOrFail($sessionId);
        $path = $this->mediaPath($s);
        abort_unless($path && str_starts_with($path, self::MEDIA_PREFIX) && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, [
            'Content-Type'        => $this->mimeFor($path),
            'Content-Disposition' => 'inline',
        ]);
    }

    /** CONFIRMAR → aplica vía Fase 4 (confirmed_by = Tere). */
    public function confirm(Request $request, int $sessionId)
    {
        $data = $request->validate([
            'client_id'  => ['nullable', 'integer'],   // para ESCALADOS: Tere identifica manual
            'service_id' => ['nullable', 'integer'],   // para multi-servicio: Tere elige
        ]);

        $s = Session::findOrFail($sessionId);
        abort_if($s->applied_at || $s->rejected_at, 409, 'El caso ya no está pendiente.');

        // Escalado: Tere asigna el cliente manualmente antes de aplicar.
        if (!$s->resolved_client_id) {
            abort_unless(!empty($data['client_id']), 422, 'Identifica al cliente antes de confirmar.');
            $s->update([
                'resolved_client_id'         => (int) $data['client_id'],
                'method'                     => 'manual',
                'certainty'                  => Session::CERTAINTY_PROPOSED,
                'state'                      => Session::STATE_RESOLVED,
                'resolved_multiple_services' => $this->clientServiceCount((int) $data['client_id']) > 1,
            ]);
            $s->refresh();
        }

        // Saldo ANTES (para el antes→después del modal).
        $client     = \App\Modules\Core\Clientes\Models\Client::find($s->resolved_client_id);
        $saldoAntes = $client ? (float) optional($client->balance)->amount : null;

        $result = $this->applier->applyConfirmed($s->id, auth()->id());

        // Falló (anti-duplicado u otro): el modal muestra el error REAL, sin falso éxito.
        if (!($result['applied'] ?? false)) {
            return response()->json($result, 422);
        }

        // Registrar el servicio elegido por Tere (multi-servicio), para traza.
        if (!empty($data['service_id']) && !empty($result['reported_payment_id'])) {
            ReportedPayment::where('id', $result['reported_payment_id'])
                ->update(['conciliation_note' => 'Servicio elegido por Tere: #' . $data['service_id']]);
        }

        // Abono SÍNCRONO del saldo para mostrar el "después" real. El guard de
        // idempotencia de PaymentClientJob hace no-op la copia async del worker.
        $saldoNuevo = $saldoAntes;
        $payment    = \App\Models\Payment::find($result['payment_id'] ?? 0);
        if ($payment) {
            try {
                \App\Jobs\Client\Payment\PaymentClientJob::dispatchSync($payment, 'created');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::channel('evolution')->warning('Modal: abono síncrono falló: ' . $e->getMessage());
            }
            $saldoNuevo = $client ? (float) optional($client->fresh()->balance)->amount : $saldoAntes;
        }

        return response()->json(array_merge($result, [
            'cliente'     => $client ? ($this->clientInfo($client->id)['name'] ?? null) : null,
            'client_id'   => $client?->id,
            'monto'       => $payment?->amount,
            'saldo_antes' => $saldoAntes,
            'saldo_nuevo' => $saldoNuevo,
            'fecha_corte' => ($client && $client->fecha_corte)
                ? \Illuminate\Support\Carbon::parse($client->fecha_corte)->format('d/m/Y')
                : null,
            'ficha_url'   => $client ? url('/cliente/editar/' . $client->id) : null,
        ]), 200);
    }

    /** RECHAZAR → marca rechazado, NO aplica. */
    public function reject(Request $request, int $sessionId)
    {
        // El motivo es OBLIGATORIO: un rechazo sin motivo se pierde sin rastro.
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $s = Session::findOrFail($sessionId);
        abort_if($s->applied_at || $s->rejected_at, 409, 'El caso ya no está pendiente.');

        $s->update([
            'rejected_at'   => now(),
            'rejected_by'   => auth()->id(),
            'reject_reason' => $data['reason'],
        ]);
        return response()->json(['ok' => true]);
    }

    /** Búsqueda de cliente para identificar manualmente un ESCALADO. */
    public function searchClients(Request $request, SubscriberSearchService $search)
    {
        $q = trim((string) $request->input('q', ''));
        // Si es numérico → intenta por ID de cliente.
        if (ctype_digit($q)) {
            $c = $search->findById((int) $q);
            return response()->json(['rows' => $c ? [$this->candidate($c)] : []]);
        }
        $rows = collect($search->search([$q]))->map(fn ($c) => $this->candidate($c));
        return response()->json(['rows' => $rows]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function rowSummary(Session $s): array
    {
        $ext = $s->extraction_id ? DB::table('whatsapp_payment_extractions')->where('id', $s->extraction_id)->first() : null;
        $f = $ext && $ext->fields ? json_decode($ext->fields, true) : [];
        return [
            'id'                => $s->id,
            'client'            => $s->resolved_client_id ? $this->clientInfo($s->resolved_client_id) : null,
            'method'            => $s->method,
            'certainty'         => $s->certainty,
            'multiple_services' => (bool) $s->resolved_multiple_services,
            'monto'             => $f['monto']['value'] ?? null,
            'clave_rastreo'     => $f['clave_rastreo']['value'] ?? null,
            'concepto'          => $f['concepto']['value'] ?? null,
            'banco'             => $f['banco_origen']['value'] ?? null,
            'created_at'        => optional($s->created_at)->format('Y-m-d H:i'),
        ];
    }

    private function clientInfo(int $clientId): ?array
    {
        $c = DB::table('client_main_information')->where('client_id', $clientId)->first();
        return $c ? [
            'id'   => $clientId,
            'name' => trim("{$c->name} {$c->father_last_name} {$c->mother_last_name}"),
        ] : ['id' => $clientId, 'name' => '(cliente #' . $clientId . ')'];
    }

    private function clientServices(int $clientId): array
    {
        $out = [];
        foreach (DB::table('client_bundle_services')->where('client_id', $clientId)->get(['id', 'description']) as $b) {
            $out[] = ['type' => 'bundle', 'id' => $b->id, 'description' => $b->description];
        }
        foreach (DB::table('client_custom_services')->where('client_id', $clientId)->get(['id', 'description']) as $s) {
            $out[] = ['type' => 'custom', 'id' => $s->id, 'description' => $s->description];
        }
        return $out;
    }

    private function userName(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }
        $u = DB::table('users')->where('id', $userId)->first(['name', 'father_last_name']);
        return $u ? trim("{$u->name} {$u->father_last_name}") : ('usuario #' . $userId);
    }

    private function clientServiceCount(int $clientId): int
    {
        return DB::table('client_bundle_services')->where('client_id', $clientId)->count()
            + DB::table('client_custom_services')->where('client_id', $clientId)->count();
    }

    private function candidate(array $c): array
    {
        return ['client_id' => $c['client_id'], 'name' => $c['full_name'] ?? null, 'colonia' => $c['colonia'] ?? null];
    }

    private function mediaPath(Session $s): ?string
    {
        if (!$s->extraction_id) {
            return null;
        }
        $msgId = DB::table('whatsapp_payment_extractions')->where('id', $s->extraction_id)->value('message_id');
        return $msgId ? DB::table('marketing_messages')->where('id', $msgId)->value('media_path') : null;
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
