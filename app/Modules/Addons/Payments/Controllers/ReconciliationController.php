<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Payments\Models\ReconciliationTicket;
use App\Modules\Addons\Payments\Services\PaymentReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tablero de la cola de conciliación (motor de cobro nativo). Tablero NUEVO
 * dedicado, SIN conexión al helpdesk (tickets). Muestra reconciliation_tickets
 * y permite resolver/descartar. NO crea tickets: eso lo hará la lógica de
 * conciliación de fases posteriores vía ReconciliationService::raise().
 *
 * Gate por permiso: ver = reconciliation_view; resolver/descartar = reconciliation_resolve.
 */
class ReconciliationController extends Controller
{
    private const REASON_LABELS = [
        'unmatched_reference' => 'Referencia sin cliente',
        'amount_mismatch'     => 'Monto no coincide',
        'duplicate'           => 'Posible duplicado',
        'manual_review'       => 'Revisión manual',
    ];

    /** Vista Blade que monta el componente Vue de la cola. */
    public function index()
    {
        abort_unless(auth()->user()?->can('reconciliation_view'), 403);

        return view('addon-payments::conciliacion');
    }

    /** Listado JSON de tickets (por defecto los abiertos). */
    public function list(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('reconciliation_view'), 403);

        $status = $request->query('status', 'open');

        // Query builder directo (no el modelo) para poder aliasar la tabla sin
        // chocar con el scope de SoftDeletes, que cualifica reconciliation_tickets.deleted_at.
        $q = DB::table('reconciliation_tickets as rt')
            ->whereNull('rt.deleted_at')
            ->leftJoin('client_main_information as cmi', 'cmi.client_id', '=', 'rt.client_id')
            ->leftJoin('client_payment_references as cpr', 'cpr.client_id', '=', 'rt.client_id')
            ->leftJoin('users as u', 'u.id', '=', 'rt.resolved_by')
            ->select([
                'rt.id', 'rt.client_id', 'rt.payment_id', 'rt.reason', 'rt.detail',
                'rt.amount', 'rt.status', 'rt.created_at', 'rt.resolved_at', 'rt.resolution_note',
                DB::raw("TRIM(CONCAT(COALESCE(cmi.name,''),' ',COALESCE(cmi.father_last_name,''),' ',COALESCE(cmi.mother_last_name,''))) as client_name"),
                'cpr.reference as client_reference',
                DB::raw("TRIM(CONCAT(COALESCE(u.name,''),' ',COALESCE(u.father_last_name,''),' ',COALESCE(u.mother_last_name,''))) as resolved_by_name"),
            ]);

        if ($status !== 'all') {
            $q->where('rt.status', $status);
        }

        $rows = $q->orderByDesc('rt.created_at')->limit(500)->get()
            ->map(function ($r) {
                $r->reason_label = self::REASON_LABELS[$r->reason] ?? $r->reason;
                $r->client_name = $r->client_name ?: null;
                $r->resolved_by_name = $r->resolved_by_name ?: null;
                // Red de seguridad: al conciliar (mostrar/buscar por referencia),
                // si el cliente del ticket aún no tiene MEG, se genera al vuelo.
                if (!empty($r->client_id) && empty($r->client_reference)) {
                    $r->client_reference = PaymentReferenceService::ensureFor($r->client_id)->reference;
                }
                return $r;
            });

        return response()->json([
            'ok'    => true,
            'open'  => ReconciliationTicket::open()->count(),
            'items' => $rows,
        ]);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        return $this->closeTicket($request, $id, 'resolved', 'Ticket resuelto.');
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        return $this->closeTicket($request, $id, 'dismissed', 'Ticket descartado.');
    }

    private function closeTicket(Request $request, int $id, string $status, string $message): JsonResponse
    {
        abort_unless(auth()->user()?->can('reconciliation_resolve'), 403);

        // Nota de resolución obligatoria: deja rastro de cómo se resolvió/por qué se descartó.
        $data = $request->validate([
            'resolution_note' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'resolution_note.required' => 'Escribe una nota explicando cómo se resolvió.',
            'resolution_note.min'      => 'La nota es demasiado corta.',
        ]);

        $ticket = ReconciliationTicket::findOrFail($id);

        if ($ticket->status !== 'open') {
            return response()->json(['ok' => false, 'message' => 'El ticket ya no está abierto.'], 422);
        }

        $ticket->update([
            'status'          => $status,
            'resolved_by'     => auth()->id(),
            'resolved_at'     => now(),
            'resolution_note' => $data['resolution_note'],
        ]);

        return response()->json([
            'ok'      => true,
            'message' => $message,
            'open'    => ReconciliationTicket::open()->count(),
        ]);
    }
}
