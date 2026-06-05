<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FleetDocumentController extends FleetBaseController
{
    private const DISK = 'fleet_documents';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeDocumentView();

        $q = FleetDocument::with('vehicle')
            ->forClient($this->clientId())
            ->orderBy('expiration_date');

        if ($request->filled('vehicle_id')) {
            $q->where('vehicle_id', $request->vehicle_id);
        }

        return response()->json([
            'documents' => $q->get()->map(fn($d) => $this->decorate($d)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('fleet.documents.manage');

        // multipart/form-data envía todo como string; decodificar antes de validar
        $this->preprocessMultipart($request);

        $data = $request->validate([
            'vehicle_id'       => 'required|integer',
            'document_type'    => 'required|in:circulation_card,insurance_policy,tenencia,verification,operator_license,special_permit,other',
            'folio_number'     => 'nullable|string|max:100',
            'issued_by'        => 'nullable|string|max:200',
            'issue_date'       => 'nullable|date',
            'expiration_date'  => 'nullable|date',
            'cost'             => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string',
            'alert_30_days'    => 'boolean',
            'alert_7_days'     => 'boolean',
            'alert_1_day'      => 'boolean',
            'alert_same_day'   => 'boolean',
            'alert_channels'   => 'nullable|array',
            'alert_channels.*' => 'in:email,whatsapp,push,sms',
            'file'             => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $this->vehicleForClient($data['vehicle_id']);

        if ($request->hasFile('file')) {
            $data['file_path'] = $this->storeFile(
                $request->file('file'),
                $data['vehicle_id'],
                $data['document_type']
            );
        }

        $doc = FleetDocument::create($data);

        return response()->json(['document' => $this->decorate($doc)], 201);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorizeDocumentView();

        $doc = FleetDocument::with('vehicle')
            ->forClient($this->clientId())
            ->findOrFail($id);

        return response()->json(['document' => $this->decorate($doc)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.documents.manage');

        $this->preprocessMultipart($request);

        $request->validate([
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $doc = FleetDocument::forClient($this->clientId())->findOrFail($id);

        if ($request->hasFile('file')) {
            // Borrar archivo anterior si existía
            if ($doc->file_path) {
                Storage::disk(self::DISK)->delete($doc->file_path);
            }
            $doc->file_path = $this->storeFile(
                $request->file('file'),
                $doc->vehicle_id,
                $doc->document_type
            );
        }

        $doc->update($request->except(['vehicle_id', 'file']));

        return response()->json(['document' => $this->decorate($doc->fresh())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.documents.manage');

        $doc = FleetDocument::forClient($this->clientId())->findOrFail($id);

        if ($doc->file_path && Storage::disk(self::DISK)->exists($doc->file_path)) {
            Storage::disk(self::DISK)->delete($doc->file_path);
        }

        $doc->delete();

        return response()->json(['ok' => true]);
    }

    public function download(int $id): Response
    {
        $this->authorizeDocumentView();

        $doc = FleetDocument::forClient($this->clientId())->findOrFail($id);

        if (!$doc->file_path || !Storage::disk(self::DISK)->exists($doc->file_path)) {
            abort(404, 'Archivo no disponible.');
        }

        return Storage::disk(self::DISK)->download($doc->file_path);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizeDocumentView();

        $q = FleetDocument::with('vehicle:id,plate,model,brand,client_id')
            ->forClient($this->clientId())
            ->orderBy('expiration_date');

        if ($request->filled('type'))    $q->where('document_type', $request->type);
        if ($request->filled('vehicle')) $q->where('vehicle_id', $request->vehicle);
        if ($request->filled('search'))  $q->where('folio_number', 'like', '%' . $request->search . '%');

        $docs = $q->get()->map(fn($d) => $this->decorate($d));

        // Filtro por status (calculado, no en DB)
        if ($request->filled('status')) {
            $docs = $docs->filter(fn($d) => $d['status'] === $request->status)->values();
        }

        $alertasHoy = DB::table('fleet_document_alert_log')
            ->whereDate('created_at', today())
            ->where('status', 'sent')
            ->count();

        $kpis = [
            'total'       => $docs->count(),
            'vencidos'    => $docs->where('status', 'vencido')->count(),
            'por_vencer'  => $docs->where('status', 'por_vencer')->count(),
            'vigentes'    => $docs->where('status', 'vigente')->count(),
            'costo_year'  => $docs->filter(fn($d) => !empty($d['issue_date']) && substr($d['issue_date'], 0, 4) == now()->year)->sum('cost'),
            'alertas_hoy' => $alertasHoy,
        ];

        return response()->json(['documents' => $docs->values(), 'kpis' => $kpis]);
    }

    public function renew(int $id): JsonResponse
    {
        $this->authorize('fleet.documents.manage');

        $prev = FleetDocument::forClient($this->clientId())->findOrFail($id);

        return response()->json([
            'prefilled' => [
                'document_type'  => $prev->document_type,
                'issued_by'      => $prev->issued_by,
                'alert_30_days'  => $prev->alert_30_days,
                'alert_7_days'   => $prev->alert_7_days,
                'alert_1_day'    => $prev->alert_1_day,
                'alert_same_day' => $prev->alert_same_day,
                'alert_channels' => $prev->alert_channels ?? ['email'],
                'notes'          => '(Renovación)' . ($prev->notes ? ' ' . $prev->notes : ''),
                'vehicle_id'     => $prev->vehicle_id,
                'cost'           => null,
            ],
            'previous_document' => [
                'id'              => $prev->id,
                'folio_number'    => $prev->folio_number,
                'expiration_date' => $prev->expiration_date?->format('d/m/Y'),
                'document_type'   => $prev->document_type,
            ],
        ]);
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
            ->map(fn($d) => $this->decorate($d));

        return response()->json(['documents' => $docs]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function authorizeDocumentView(): void
    {
        if (!auth()->user()?->can('fleet.documents.view') && !auth()->user()?->can('fleet.documents.manage')) {
            abort(403);
        }
    }

    private function decorate(FleetDocument $d): array
    {
        return array_merge($d->toArray(), [
            'status'                => $d->status,
            'days_until_expiration' => $d->days_until_expiration,
            'has_file'              => (bool) $d->file_path,
        ]);
    }

    private function preprocessMultipart(Request $request): void
    {
        // multipart/form-data serializa todo a string; normalizar antes de validar.
        if (is_string($request->input('alert_channels'))) {
            $decoded = json_decode($request->input('alert_channels'), true);
            $request->merge(['alert_channels' => is_array($decoded) ? $decoded : []]);
        }

        foreach (['alert_30_days', 'alert_7_days', 'alert_1_day', 'alert_same_day'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    private function storeFile($file, int $vehicleId, string $docType): string
    {
        $ext      = $file->getClientOriginalExtension();
        $filename = $vehicleId . '_' . $docType . '_' . time() . '_' . Str::random(6) . '.' . $ext;
        Storage::disk(self::DISK)->putFileAs('', $file, $filename);
        return $filename;
    }
}
