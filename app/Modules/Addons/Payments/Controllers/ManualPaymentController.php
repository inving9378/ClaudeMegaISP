<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MethodOfPayment;
use App\Modules\Addons\Payments\Models\ReportedPayment;
use App\Modules\Addons\Payments\Services\PaymentApplicationService;
use App\Modules\Addons\Payments\Services\ReconciliationService;
use App\Modules\Addons\PortalPago\Models\PortalPagoAccount;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Captura interna de "pago reportado" por mostrador (Paso 2 · FASE PAGOS).
 *
 * Digitaliza el flujo de Diana/Ariana: al guardar (a) APLICA el pago al cliente
 * reusando PaymentApplicationService (mismo flujo, observers y saldo que
 * cualquier otro pago) y (b) lo deja registrado en reported_payments con
 * comprobante + estado pendiente_verificar para el cruce de Tere contra banco.
 */
class ManualPaymentController extends Controller
{
    private const PERM = 'payments_capture_manage';

    public function create()
    {
        abort_unless(auth()->user()?->can(self::PERM), 403);

        return view('addon-payments::captura-pago', [
            'methods'         => MethodOfPayment::orderBy('id')->get(['id', 'type']),
            'accounts'        => PortalPagoAccount::activas()->get(['id', 'nombre', 'banco', 'clabe']),
            'defaultMethodId' => 2, // Transferencia Bancaria
        ]);
    }

    /**
     * Busca cliente por nombre o por referencia MEG (client_payment_references).
     */
    public function buscarCliente(Request $request)
    {
        abort_unless(auth()->user()?->can(self::PERM), 403);

        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }
        $like = '%' . $term . '%';

        $byRef  = DB::table('client_payment_references')->where('reference', 'like', $like)->pluck('client_id');
        $byName = DB::table('client_main_information')
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('father_last_name', 'like', $like)
                  ->orWhere('mother_last_name', 'like', $like);
            })
            ->pluck('client_id');

        $ids = $byRef->merge($byName)->unique()->take(20)->values();
        if ($ids->isEmpty()) {
            return response()->json([]);
        }

        $clients = Client::whereIn('id', $ids)->with('balance')->get();
        $refs    = DB::table('client_payment_references')->whereIn('client_id', $ids)->pluck('reference', 'client_id');

        return response()->json(
            $clients->map(fn ($c) => [
                'client_id'  => $c->id,
                'nombre'     => $c->clientFullName(),
                'referencia' => $refs[$c->id] ?? null,
                'balance'    => optional($c->balance)->amount ?? 0,
            ])->values()
        );
    }

    /**
     * Aplica el pago (primero) y luego registra la metadata (si falla el
     * registro, el pago YA quedó aplicado → se loguea, no se rompe).
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()?->can(self::PERM), 403);

        $data = $request->validate([
            'client_id'            => ['required', 'integer', 'exists:clients,id'],
            'amount'               => ['required', 'numeric', 'min:0.01'],
            'fecha_pago'           => ['required', 'date'],
            'method_of_payment_id' => ['required', 'integer', 'exists:method_of_payments,id'],
            'clave_rastreo'        => ['nullable', 'string', 'max:40'],
            'titular'              => ['nullable', 'string', 'max:255'],
            'banco_origen'         => ['nullable', 'string', 'max:255'],
            'receiver_account_id'  => ['nullable', 'integer', 'exists:portal_pago_accounts,id'],
            'comprobante'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        // 1) Comprobante (disco local, privado).
        $comprobantePath = null;
        if ($request->hasFile('comprobante')) {
            $comprobantePath = $request->file('comprobante')
                ->store('private/payments/mostrador/comprobantes', 'local');
        }

        // 2) APLICAR el pago PRIMERO (dinero correcto) — mismo flujo que el webhook.
        $payment = app(PaymentApplicationService::class)->applyPayment([
            'client_id'   => (int) $data['client_id'],
            'amount'      => (float) $data['amount'],
            'method_id'   => (int) $data['method_of_payment_id'],
            'add_by'      => auth()->id(),
            'date'        => $data['fecha_pago'],
            'external_id' => $data['clave_rastreo'] ?? null,
            'provider'    => 'mostrador',
            'comment'     => 'Pago capturado en mostrador'
                . (!empty($data['clave_rastreo']) ? " (clave: {$data['clave_rastreo']})" : ''),
        ]);

        // 3) Registrar metadata del pago reportado. Si falla, el pago ya está
        //    aplicado → log y seguimos (filosofía del webhook, no perder el pago).
        $report = null;
        try {
            $report = ReportedPayment::create([
                'payment_id'           => $payment->id,
                'client_id'            => (int) $data['client_id'],
                'receiver_account_id'  => $data['receiver_account_id'] ?? null,
                'method_of_payment_id' => (int) $data['method_of_payment_id'],
                'amount'               => $data['amount'],
                'fecha_pago'           => $data['fecha_pago'],
                'clave_rastreo'        => $data['clave_rastreo'] ?? null,
                'titular'              => $data['titular'] ?? null,
                'banco_origen'         => $data['banco_origen'] ?? null,
                'comprobante_path'     => $comprobantePath,
                'conciliation_status'  => ReportedPayment::ESTADO_PENDIENTE,
            ]);
        } catch (\Throwable $e) {
            Log::error('Captura mostrador: pago aplicado pero falló el registro reported_payment', [
                'payment_id' => $payment->id,
                'client_id'  => $data['client_id'],
                'error'      => $e->getMessage(),
            ]);
        }

        // 4) R1: raise() SOLO ante discrepancia real. Aquí: clave de rastreo
        //    duplicada (mismo SPEI capturado dos veces) → a la cola de Tere.
        if (!empty($data['clave_rastreo'])) {
            $dup = ReportedPayment::where('clave_rastreo', $data['clave_rastreo'])
                ->when($report, fn ($q) => $q->where('id', '!=', $report->id))
                ->exists();
            if ($dup) {
                ReconciliationService::raise(
                    'duplicate',
                    "Clave de rastreo {$data['clave_rastreo']} capturada más de una vez en mostrador.",
                    (float) $data['amount'],
                    (int) $data['client_id'],
                    $payment->id
                );
            }
        }

        return response()->json([
            'ok'                  => true,
            'payment_id'          => $payment->id,
            'reported_payment_id' => $report?->id,
            'message'             => 'Pago aplicado y registrado correctamente.',
        ]);
    }

    public function descargarComprobante(int $id)
    {
        abort_unless(auth()->user()?->can(self::PERM), 403);

        $report = ReportedPayment::findOrFail($id);
        abort_if(empty($report->comprobante_path), 404);
        abort_unless(Storage::disk('local')->exists($report->comprobante_path), 404);

        return Storage::disk('local')->download($report->comprobante_path);
    }
}
