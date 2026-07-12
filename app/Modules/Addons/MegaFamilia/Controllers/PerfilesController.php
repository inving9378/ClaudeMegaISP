<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PerfilesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::perfiles.index');
    }

    public function data(): JsonResponse
    {
        $account = $this->accountForCurrentUser();
        $profiles = $account
            ? $account->profiles()->withCount('devices')->get()
            : ParentalProfile::query()->withCount('devices')->orderBy('name')->get();
        return response()->json(['profiles' => $profiles, 'account' => $account]);
    }

    public function store(Request $request): JsonResponse
    {
        $account = $this->accountForCurrentUser();
        if (! $account) {
            return response()->json(['success' => false, 'error' => 'Sin cuenta parental asociada'], 403);
        }
        $data = $request->validate([
            'name' => 'required|string',
            'age' => 'nullable|integer|min:0|max:25',
            'school_level' => 'nullable|in:primaria,secundaria,preparatoria',
            'profile_type' => 'nullable|in:nino,preadolescente,adolescente',
        ]);
        $data['account_id'] = $account->id;
        $profile = ParentalProfile::create($data);
        return response()->json(['success' => true, 'profile' => $profile]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $profile = ParentalProfile::findOrFail($id);
        $this->guardOwnership($profile);
        $profile->update($request->only(['name', 'age', 'school_level', 'profile_type', 'photo', 'active']));
        return response()->json(['success' => true, 'profile' => $profile]);
    }

    public function destroy(int $id): JsonResponse
    {
        $profile = ParentalProfile::findOrFail($id);
        $this->guardOwnership($profile);
        $profile->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Detalle completo del perfil para los 5 tabs:
     * Info, Dispositivos, Reglas, Tareas, Alertas.
     */
    public function show(int $id): JsonResponse
    {
        $profile = ParentalProfile::with([
            'devices',
            'rules',
            'appBlocks',
            'webBlocks',
            'schedules',
            'tasks' => fn ($q) => $q->latest()->limit(50),
        ])->findOrFail($id);

        $alerts = \App\Modules\Addons\MegaFamilia\Models\ParentalAlert::query()
            ->where('profile_id', $id)
            ->with('device:id,name')
            ->latest()
            ->limit(30)
            ->get();

        return response()->json([
            'profile' => $profile,
            'alerts'  => $alerts,
        ]);
    }

    private function accountForCurrentUser(): ?ParentalAccount
    {
        $userId = Auth::id();
        if (! $userId) return null;
        return ParentalAccount::where('user_id', $userId)->first();
    }

    private function guardOwnership(ParentalProfile $profile): void
    {
        $account = $this->accountForCurrentUser();
        if (! $account) {
            // Sin cuenta parental propia = usuario staff, no cliente final.
            // Exigir megafamilia_admin en vez de dejar pasar sin verificación.
            abort_unless(Auth::user()->can('megafamilia_admin'), 403);
            return;
        }
        abort_unless($profile->account_id === $account->id, 403);
    }
}
