<?php

namespace App\Modules\Addons\PortalPago\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Modules\Addons\PortalPago\Models\PortalPagoAccount;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentLink;
use App\Modules\Addons\PortalPago\Services\PortalPagoLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ligas de pago: generación + historial.
 */
class LinksController extends Controller
{
    public function __construct(private PortalPagoLinkService $links)
    {
    }

    public function index()
    {
        abort_unless(auth()->user()?->can('pagos.links.manage'), 403);

        return view('addon-portal-pago::admin.links', [
            'cuentas' => PortalPagoAccount::activas()->orderBy('nombre')->get(['id', 'nombre', 'banco', 'clabe']),
        ]);
    }

    public function list(Request $request)
    {
        abort_unless(auth()->user()?->can('pagos.links.manage'), 403);

        $query = PortalPagoPaymentLink::query()
            ->with(['client.client_main_information', 'account'])
            ->orderByDesc('id');

        if ($estado = $request->input('estado')) {
            $query->where('estado', $estado);
        }
        if ($clientId = $request->input('client_id')) {
            $query->where('client_id', $clientId);
        }

        $links = $query->paginate((int) $request->input('per_page', 15));
        $links->getCollection()->transform(fn ($l) => $this->presentar($l));

        return response()->json($links);
    }

    /**
     * Búsqueda de clientes por nombre o id (para el selector de generación).
     */
    public function buscarClientes(Request $request)
    {
        abort_unless(auth()->user()?->can('pagos.links.manage'), 403);

        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $rows = DB::table('client_main_information')
            ->select('client_id', 'name', 'father_last_name', 'mother_last_name', 'phone')
            ->where(function ($w) use ($q) {
                $w->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('father_last_name', 'LIKE', "%{$q}%")
                    ->orWhere('mother_last_name', 'LIKE', "%{$q}%")
                    ->orWhere('client_id', $q);
            })
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'client_id' => $r->client_id,
                'nombre'    => trim(implode(' ', array_filter([$r->name, $r->father_last_name, $r->mother_last_name]))) ?: ('Cliente #' . $r->client_id),
                'phone'     => $r->phone,
            ]);

        return response()->json($rows);
    }

    /**
     * Facturas pendientes (Pagar%) de un cliente.
     */
    public function facturasCliente(int $clientId)
    {
        abort_unless(auth()->user()?->can('pagos.links.manage'), 403);

        $facturas = ClientInvoice::query()
            ->where('client_id', $clientId)
            ->where('estado', 'LIKE', 'Pagar%')
            ->orderBy('id')
            ->get(['id', 'number', 'total', 'document_date', 'estado'])
            ->map(fn ($f) => [
                'id'            => $f->id,
                'number'        => $f->number,
                'total'         => (float) $f->total,
                'document_date' => $f->document_date,
                'estado'        => $f->estado,
            ]);

        return response()->json($facturas);
    }

    public function generar(Request $request)
    {
        abort_unless(auth()->user()?->can('pagos.links.manage'), 403);

        $data = $request->validate([
            'client_id'   => ['required', 'integer'],
            'document_id' => ['required', 'integer'],
            'account_id'  => ['required', 'integer', 'exists:portal_pago_accounts,id'],
        ]);

        $invoice = ClientInvoice::where('id', $data['document_id'])
            ->where('client_id', $data['client_id'])
            ->where('estado', 'LIKE', 'Pagar%')
            ->first();

        if (! $invoice) {
            return response()->json(['ok' => false, 'message' => 'La factura no existe, no es del cliente o ya está pagada.'], 422);
        }

        $account = PortalPagoAccount::activas()->find($data['account_id']);
        if (! $account) {
            return response()->json(['ok' => false, 'message' => 'La cuenta de cobro no está activa.'], 422);
        }

        $link = $this->links->generate($invoice, $account->id, (float) $invoice->total);

        $cmi   = DB::table('client_main_information')->where('client_id', $data['client_id'])->first();
        $phone = $cmi->phone ?? null;

        return response()->json([
            'ok'      => true,
            'message' => 'Liga generada.',
            'link'    => [
                'token'            => $link->token,
                'url'              => url('/f/' . $link->token),
                'referencia'       => $link->referencia_unica,
                'monto'            => (float) $link->monto_esperado,
                'expira_at'        => optional($link->expira_at)->format('d/m/Y'),
                'cliente_telefono' => preg_replace('/\D+/', '', (string) $phone),
            ],
        ]);
    }

    private function presentar(PortalPagoPaymentLink $l): array
    {
        $cmi = $l->client?->client_main_information;
        $nombre = $cmi
            ? trim(implode(' ', array_filter([$cmi->name ?? null, $cmi->father_last_name ?? null, $cmi->mother_last_name ?? null])))
            : null;

        return [
            'id'          => $l->id,
            'token'       => $l->token,
            'url'         => url('/f/' . $l->token),
            'cliente'     => $nombre ?: ('Cliente #' . $l->client_id),
            'document_id' => $l->document_id,
            'monto'       => (float) $l->monto_esperado,
            'referencia'  => $l->referencia_unica,
            'estado'      => $l->estado,
            'cuenta'      => $l->account ? ($l->account->banco . ' · ' . $l->account->clabe) : null,
            'telefono'    => preg_replace('/\D+/', '', (string) ($cmi->phone ?? '')),
            'creado'      => optional($l->created_at)->format('d/m/Y H:i'),
            'expira_at'   => optional($l->expira_at)->format('d/m/Y'),
        ];
    }
}
