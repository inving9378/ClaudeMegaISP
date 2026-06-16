<?php

namespace App\Modules\Addons\Domiciliacion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Domiciliacion\Models\ClientRecurringCard;
use App\Modules\Addons\Domiciliacion\Services\DomiciliacionEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DomiciliacionController extends Controller
{
    public function __construct(private DomiciliacionEnrollmentService $enrollment) {}

    // ── Portal (cliente autenticado con guard 'cliente') ──────────────────────

    public function portalShow(): JsonResponse
    {
        $clientId = Auth::guard('cliente')->user()->client_id;
        $card     = ClientRecurringCard::forClient($clientId)->active()->first();

        return response()->json(['card' => $card ? $this->formatCard($card) : null]);
    }

    public function portalStore(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:100']);

        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        $card = $this->enrollment->enrolar(
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

        return response()->json(['success' => true, 'card' => $this->formatCard($card)]);
    }

    public function portalDestroy(int $cardId): JsonResponse
    {
        $clientId = Auth::guard('cliente')->user()->client_id;
        $this->enrollment->cancelar($cardId, $clientId);

        return response()->json(['success' => true]);
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    public function adminShow(int $clientId): JsonResponse
    {
        $card = ClientRecurringCard::forClient($clientId)->active()->first();

        return response()->json(['card' => $card ? $this->formatCard($card) : null]);
    }

    public function adminStore(Request $request, int $clientId): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:100']);

        $cmi = DB::table('client_main_information')->where('client_id', $clientId)->first();

        $card = $this->enrollment->enrolar(
            clientId:     $clientId,
            token:        $data['token'],
            channel:      'admin',
            createdBy:    Auth::id() ?? 0,
            customerData: [
                'name'         => $cmi->name ?? 'Cliente',
                'last_name'    => $cmi->father_last_name ?? '',
                'email'        => $cmi->email ?? '',
                'phone_number' => $cmi->phone ?? '',
            ]
        );

        return response()->json(['success' => true, 'card' => $this->formatCard($card)]);
    }

    public function adminDestroy(int $clientId, int $cardId): JsonResponse
    {
        $this->enrollment->cancelar($cardId, $clientId);

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function formatCard(ClientRecurringCard $card): array
    {
        return [
            'id'         => $card->id,
            'brand'      => $card->card_brand,
            'last4'      => $card->card_last4,
            'exp'        => $card->card_exp,
            'cardholder' => $card->cardholder,
            'status'     => $card->status,
            'consent_at' => $card->consent_at?->toIso8601String(),
            'channel'    => $card->consent_channel,
        ];
    }
}
