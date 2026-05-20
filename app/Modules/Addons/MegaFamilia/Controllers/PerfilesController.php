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

    private function accountForCurrentUser(): ?ParentalAccount
    {
        $userId = Auth::id();
        if (! $userId) return null;
        return ParentalAccount::where('client_id', $userId)->first();
    }

    private function guardOwnership(ParentalProfile $profile): void
    {
        $account = $this->accountForCurrentUser();
        if (! $account) return;  // admin/support con permission megafamilia_admin
        abort_unless($profile->account_id === $account->id, 403);
    }
}
