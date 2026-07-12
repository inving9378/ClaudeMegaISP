<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoCredential;
use App\Modules\Addons\Talento\Models\TalentoFund;
use App\Modules\Addons\Talento\Services\FundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TalentoCredentialController extends Controller
{
    public function __construct(private FundService $fundService) {}

    // ── Web view ───────────────────────────────────────────────────────────

    public function index()
    {
        return view('addon-talento::talento.credenciales');
    }

    // ── Credentials ────────────────────────────────────────────────────────

    public function listForColaborador(int $colaboradorId)
    {
        $creds = TalentoCredential::where('colaborador_id', $colaboradorId)
            ->orderByDesc('expires_at')
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), [
                'days_until_expiry'  => $c->daysUntilExpiry(),
                'weeks_until_expiry' => $c->weeksUntilExpiry(),
            ]));
        return response()->json($creds);
    }

    public function allExpiring(Request $request)
    {
        $q = TalentoCredential::with('colaborador.user')
            ->whereIn('status', ['expiring', 'expired', 'missing'])
            ->orderBy('expires_at');
        if ($request->filled('status')) $q->where('status', $request->status);
        return response()->json($q->get()->map(fn($c) => array_merge($c->toArray(), [
            'days_until_expiry'  => $c->daysUntilExpiry(),
            'collaborator_name'  => $c->colaborador?->user?->name,
        ])));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'colaborador_id'     => 'required|integer|exists:talento_colaboradores,id',
            'type'               => 'required|in:driver_license,other',
            'document_number'    => 'nullable|string|max:60',
            'issued_at'          => 'nullable|date',
            'expires_at'         => 'nullable|date|after_or_equal:today',
            'alert_weeks_before' => 'integer|min:1|max:52',
            'notes'              => 'nullable|string|max:500',
        ]);

        // Photo/scan — cifrada con Crypt (setter en el modelo)
        $filePath = null;
        if ($request->hasFile('document_file')) {
            $request->validate(['document_file' => 'file|mimes:jpg,jpeg,png,pdf|max:8192']);
            $filePath = $request->file('document_file')->store(
                'talento/credentials/' . now()->format('Y/m'), 'local'
            );
        }

        $status = $this->computeStatus($data['expires_at'] ?? null, $data['alert_weeks_before'] ?? 8);

        // updateOrCreate — un colaborador sólo tiene una credencial por tipo
        $cred = TalentoCredential::updateOrCreate(
            ['colaborador_id' => $data['colaborador_id'], 'type' => $data['type']],
            array_merge($data, [
                'file_path'  => $filePath, // el setter cifra
                'status'     => $status,
                'created_by' => auth()->id(),
            ])
        );

        // Si no tiene fondo de licencia y está 'expiring', crear fondo pendiente de autorización
        if ($status === 'expiring' && $cred->type === 'driver_license') {
            $this->ensureRenewalFund($cred);
        }

        return response()->json(array_merge($cred->toArray(), [
            'days_until_expiry' => $cred->daysUntilExpiry(),
        ]), 201);
    }

    public function update(Request $request, int $id)
    {
        $cred = TalentoCredential::findOrFail($id);
        $data = $request->validate([
            'document_number'    => 'nullable|string|max:60',
            'issued_at'          => 'nullable|date',
            'expires_at'         => 'nullable|date',
            'alert_weeks_before' => 'integer|min:1|max:52',
            'notes'              => 'nullable|string|max:500',
        ]);

        if ($request->hasFile('document_file')) {
            $request->validate(['document_file' => 'file|mimes:jpg,jpeg,png,pdf|max:8192']);
            $data['file_path'] = $request->file('document_file')->store(
                'talento/credentials/' . now()->format('Y/m'), 'local'
            );
        }

        $data['status'] = $this->computeStatus(
            $data['expires_at'] ?? $cred->expires_at?->toDateString(),
            $data['alert_weeks_before'] ?? $cred->alert_weeks_before
        );

        $cred->update($data);
        return response()->json(array_merge($cred->fresh()->toArray(), [
            'days_until_expiry' => $cred->fresh()->daysUntilExpiry(),
        ]));
    }

    public function serveDocument(int $id)
    {
        $this->authorize('talento.credentials.view');
        $cred = TalentoCredential::findOrFail($id);
        $path = $cred->getDecryptedPath();
        if (!$path || !Storage::disk('local')->exists($path)) abort(404);
        return response()->file(Storage::disk('local')->path($path));
    }

    // ── Funds ──────────────────────────────────────────────────────────────

    public function listFunds(int $colaboradorId)
    {
        $funds = TalentoFund::where('colaborador_id', $colaboradorId)
            ->with('credential')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($f) => array_merge($f->toArray(), [
                'progress_pct'    => $f->progressPct(),
                'weeks_remaining' => $f->weeksRemaining(),
            ]));
        return response()->json($funds);
    }

    public function storeFund(Request $request)
    {
        $data = $request->validate([
            'colaborador_id'   => 'required|integer|exists:talento_colaboradores,id',
            'purpose'          => 'required|in:license,other',
            'target_amount'    => 'required|numeric|min:1',
            'weekly_deduction' => 'required|numeric|min:1',
            'credential_id'    => 'nullable|integer|exists:talento_credentials,id',
            'notes'            => 'nullable|string|max:500',
        ]);

        $fund = TalentoFund::create(array_merge($data, [
            'accumulated' => 0,
            'status'      => 'accumulating',
            'authorized'  => false,
            'created_by'  => auth()->id(),
        ]));

        return response()->json(array_merge($fund->toArray(), [
            'progress_pct'    => $fund->progressPct(),
            'weeks_remaining' => $fund->weeksRemaining(),
        ]), 201);
    }

    public function authorizeFund(Request $request, int $fundId)
    {
        $fund = TalentoFund::findOrFail($fundId);
        $resolved = $this->fundService->authorize($fund, auth()->id());
        return response()->json(array_merge($resolved->toArray(), [
            'progress_pct'    => $resolved->progressPct(),
            'weeks_remaining' => $resolved->weeksRemaining(),
        ]));
    }

    public function markFundSpent(int $fundId)
    {
        $fund = TalentoFund::findOrFail($fundId);
        if ($fund->status !== 'ready') {
            return response()->json(['error' => 'Solo se puede marcar como spent un fondo en estado ready.'], 422);
        }
        $resolved = $this->fundService->markSpent($fund);
        return response()->json($resolved);
    }

    public function allFundsAlert()
    {
        $funds = TalentoFund::with(['colaborador.user', 'credential'])
            ->whereIn('status', ['accumulating', 'ready'])
            ->where('authorized', false)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($f) => array_merge($f->toArray(), [
                'progress_pct'       => $f->progressPct(),
                'collaborator_name'  => $f->colaborador?->user?->name,
            ]));
        return response()->json($funds);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function computeStatus(?string $expiresAt, int $alertWeeks): string
    {
        if (!$expiresAt) return 'missing';
        $weeks = now()->diffInWeeks(\Carbon\Carbon::parse($expiresAt), false);
        if ($weeks < 0)           return 'expired';
        if ($weeks <= $alertWeeks) return 'expiring';
        return 'valid';
    }

    private function ensureRenewalFund(TalentoCredential $cred): void
    {
        $existing = TalentoFund::where('colaborador_id', $cred->colaborador_id)
            ->where('credential_id', $cred->id)
            ->whereIn('status', ['accumulating', 'ready'])
            ->exists();
        if ($existing) return;

        $target  = (float)(setting('talento_license_renewal_cost') ?? 800);
        $weekly  = (float)(setting('talento_license_weekly_deduction') ?? 100);

        TalentoFund::create([
            'colaborador_id'   => $cred->colaborador_id,
            'purpose'          => 'license',
            'target_amount'    => $target,
            'accumulated'      => 0,
            'weekly_deduction' => $weekly,
            'status'           => 'accumulating',
            'authorized'       => false,
            'credential_id'    => $cred->id,
            'notes'            => "Fondo para renovación credencial #{$cred->id}",
            'created_by'       => auth()->id(),
        ]);
    }
}
