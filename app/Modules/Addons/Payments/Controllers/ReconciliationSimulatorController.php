<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Models\WhatsappPaymentExtraction;
use App\Modules\Addons\Payments\Services\Identification\IdentificationFsm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FASE 3 (F3.4) — SIMULADOR de la conversación de identificación.
 *
 * Irving prueba los escenarios paso a paso SIN enviar mensajes reales: elige un
 * comprobante (o escribe un concepto/titular) y "juega" la conversación
 * respondiendo como si fuera el cliente. Corre el MISMO IdentificationFsm que
 * producción, pero:
 *   - todas las sesiones se marcan is_simulation=true (F4 las ignora);
 *   - NADA se manda por Evolution: el "outbound" se muestra en pantalla.
 *
 * Gateado por rol (super-administrator|DESARROLLADOR) en las rutas.
 * NO aplica pago, NO identifica en real, NO responde por WhatsApp.
 */
class ReconciliationSimulatorController extends Controller
{
    public function __construct(private IdentificationFsm $fsm) {}

    public function index()
    {
        // Comprobantes ya extraídos (para prellenar concepto/titular reales).
        $extractions = WhatsappPaymentExtraction::latest('id')->limit(30)->get()
            ->map(fn ($e) => [
                'id'       => $e->id,
                'concepto' => $e->concepto,
                'titular'  => $e->fields['titular_ordenante']['value'] ?? null,
                'label'    => "#{$e->id} · " . ($e->concepto ?: '(sin concepto)'),
            ]);

        return view('addon-payments::conciliacion-sim', ['extractions' => $extractions]);
    }

    /** Inicia una simulación. Crea la sesión (is_simulation) y corre start(). */
    public function start(Request $request)
    {
        $data = $request->validate([
            'concepto'      => ['nullable', 'string', 'max:255'],
            'titular'       => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'extraction_id' => ['nullable', 'integer'],
        ]);

        $concepto = trim((string) ($data['concepto'] ?? ''));
        $titular  = $data['titular'] ?? null;

        // Si eligió un comprobante y no escribió concepto, tómalo del extracto.
        if ($concepto === '' && !empty($data['extraction_id'])) {
            $ext = WhatsappPaymentExtraction::find($data['extraction_id']);
            if ($ext) {
                $concepto = (string) $ext->concepto;
                $titular  = $titular ?: ($ext->fields['titular_ordenante']['value'] ?? null);
            }
        }

        $hours = (int) (Setting::get('reconciliation_session_hours', 1) ?? 12);

        $session = Session::create([
            'is_simulation'   => true,
            'extraction_id'   => $data['extraction_id'] ?? 0,
            'conversation_id' => 0,
            'state'           => Session::STATE_DETECTING,
            'attempts'        => 0,
            'expires_at'      => now()->addHours($hours),
            'created_by'      => auth()->id(),
        ]);

        $step = $this->fsm->start($session, $concepto, $titular, $data['phone'] ?? null);

        return response()->json([
            'session_id' => $session->id,
            'input'      => ['concepto' => $concepto, 'titular' => $titular, 'phone' => $data['phone'] ?? null],
            'step'       => $this->enrich($step, $session),
        ]);
    }

    /** El "cliente" responde. Reinicia el reloj de silencio (recordatorios). */
    public function reply(Request $request)
    {
        [$session, $data] = $this->simSession($request, ['text' => ['required', 'string', 'max:500']]);
        // El cliente respondió → se reinician los recordatorios (silencio a 0).
        $session->update(['reminders_sent' => 0]);
        $step = $this->fsm->handleReply($session, $data['text'], null);
        return response()->json(['step' => $this->enrich($step, $session->fresh())]);
    }

    /**
     * Avanza el reloj de silencio a $minutes desde el último contacto (acelerable).
     * Dispara recordatorios por etapa (1h/5h) o el cierre por expiración.
     */
    public function advance(Request $request)
    {
        [$session, $data] = $this->simSession($request, ['minutes' => ['required', 'integer', 'min:1', 'max:100000']]);
        $step = $this->fsm->advanceTime($session, (int) $data['minutes']);
        return response()->json(['step' => $this->enrich($step, $session->fresh())]);
    }

    /** Borra la sesión de simulación. */
    public function reset(Request $request)
    {
        [$session] = $this->simSession($request);
        $session->delete();
        return response()->json(['ok' => true]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Carga la sesión de simulación (nunca una real) + valida input. */
    private function simSession(Request $request, array $rules = []): array
    {
        $data = $request->validate($rules + ['session_id' => ['required', 'integer']]);
        $session = Session::where('id', $data['session_id'])->where('is_simulation', true)->firstOrFail();
        return [$session, $data];
    }

    /** Enriquече el step con datos para mostrar (nombre del cliente resuelto, etc.). */
    private function enrich(array $step, Session $session): array
    {
        $step['attempts'] = $session->attempts;
        $step['session_state'] = $session->state;

        if (!empty($step['resolved_client_id'])) {
            $cmi = DB::table('client_main_information')->where('client_id', $step['resolved_client_id'])->first();
            $step['resolved_client_name'] = $cmi
                ? trim("{$cmi->name} {$cmi->father_last_name} {$cmi->mother_last_name}")
                : null;
        }
        return $step;
    }
}
