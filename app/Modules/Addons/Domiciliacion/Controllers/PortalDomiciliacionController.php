<?php

namespace App\Modules\Addons\Domiciliacion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Domiciliacion\Models\ClientRecurringCard;
use App\Modules\Addons\Domiciliacion\Services\DomiciliacionEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalDomiciliacionController extends Controller
{
    public function __construct(private DomiciliacionEnrollmentService $enrollment) {}

    public function index()
    {
        $clientId = Auth::guard('cliente')->user()->client_id;
        $card     = ClientRecurringCard::forClient($clientId)->active()->first();

        return view('addon-domiciliacion::portal_domiciliacion', [
            'card'       => $card,
            'openpayId'  => config('openpay.id'),
            'openpayKey' => config('openpay.public_key'),
            'sandbox'    => config('openpay.sandbox', true),
        ]);
    }

    public function enrolar(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|max:100']);

        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        try {
            $this->enrollment->enrolar(
                clientId:     $clientId,
                token:        $data['token'],
                channel:      'portal',
                createdBy:    0,
                customerData: [
                    'name'         => $cmi->name ?? 'Cliente',
                    'last_name'    => $cmi->father_last_name ?? '',
                    'email'        => $cmi->email ?? '',
                    'phone_number' => $cmi->phone ?? '',
                ]
            );

            return redirect()->route('portal.domiciliacion.tarjeta')
                ->with('success', '¡Tarjeta registrada! Tu cobro automático quedó activado.');
        } catch (\Throwable $e) {
            return redirect()->route('portal.domiciliacion.tarjeta')
                ->with('error', 'No pudimos registrar tu tarjeta: ' . $e->getMessage());
        }
    }

    public function cancelar(int $cardId)
    {
        $clientId = Auth::guard('cliente')->user()->client_id;

        try {
            $this->enrollment->cancelar($cardId, $clientId);
            return redirect()->route('portal.domiciliacion.tarjeta')
                ->with('success', 'Domiciliación cancelada correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('portal.domiciliacion.tarjeta')
                ->with('error', 'No se pudo cancelar: ' . $e->getMessage());
        }
    }
}
