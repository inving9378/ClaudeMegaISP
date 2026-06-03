<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetDocumentController extends FleetBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('fleet.view');

        $q = FleetDocument::with('vehicle')
            ->forClient($this->clientId())
            ->orderBy('expiration_date');

        if ($request->filled('vehicle_id')) {
            $q->where('vehicle_id', $request->vehicle_id);
        }

        return response()->json([
            'documents' => $q->get()->map(fn($d) => array_merge($d->toArray(), [
                'status'                => $d->status,
                'days_until_expiration' => $d->days_until_expiration,
            ])),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('fleet.documents.manage');

        $data = $request->validate([
            'vehicle_id'      => 'required|integer',
            'document_type'   => 'required|in:circulation_card,insurance_policy,tenencia,verification,operator_license,special_permit,other',
            'folio_number'    => 'nullable|string|max:100',
            'issued_by'       => 'nullable|string|max:200',
            'issue_date'      => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'cost'            => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'alert_30_days'   => 'boolean',
            'alert_7_days'    => 'boolean',
            'alert_1_day'     => 'boolean',
            'alert_same_day'  => 'boolean',
            'alert_channels'  => 'nullable|array',
            'alert_channels.*'=> 'in:email,whatsapp,push,sms',
        ]);

        $this->vehicleForClient($data['vehicle_id']);

        $doc = FleetDocument::create($data);

        return response()->json(['document' => array_merge($doc->toArray(), [
            'status'                => $doc->status,
            'days_until_expiration' => $doc->days_until_expiration,
        ])], 201);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize('fleet.view');

        $doc = FleetDocument::with('vehicle')
            ->forClient($this->clientId())
            ->findOrFail($id);

        return response()->json(['document' => array_merge($doc->toArray(), [
            'status'                => $doc->status,
            'days_until_expiration' => $doc->days_until_expiration,
        ])]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.documents.manage');

        $doc = FleetDocument::forClient($this->clientId())->findOrFail($id);
        $doc->update($request->except(['vehicle_id']));

        return response()->json(['document' => $doc->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.documents.manage');

        FleetDocument::forClient($this->clientId())->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function proximos(): JsonResponse
    {
        $this->authorize('fleet.documents.manage');

        $docs = FleetDocument::with('vehicle')
            ->forClient($this->clientId())
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', now()->addDays(30))
            ->orderBy('expiration_date')
            ->get()
            ->map(fn($d) => array_merge($d->toArray(), [
                'status'                => $d->status,
                'days_until_expiration' => $d->days_until_expiration,
            ]));

        return response()->json(['documents' => $docs]);
    }
}
