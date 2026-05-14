<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalLocation;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use App\Modules\Addons\MegaFamilia\Models\ParentalRequest;
use App\Modules\Addons\MegaFamilia\Models\ParentalRule;
use App\Modules\Addons\MegaFamilia\Models\ParentalTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * API mobile (padres + dispositivos hijos). Todas las rutas excepto login
 * usan `auth:sanctum`. El modelo User debe tener `Laravel\Sanctum\HasApiTokens`
 * para que `$user->createToken()` funcione — si aún no lo tiene, añadirlo
 * antes de exponer la app móvil al público.
 */
class ApiController extends Controller
{
    // ---- AUTH -------------------------------------------------------------

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'sometimes|string',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['success' => false, 'error' => 'Credenciales inválidas'], 401);
        }

        if (! method_exists($user, 'createToken')) {
            return response()->json([
                'success' => false,
                'error' => 'Sanctum no está habilitado en el modelo User (falta HasApiTokens trait).',
            ], 500);
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;
        return response()->json(['success' => true, 'token' => $token, 'user' => $user->only('id', 'name', 'email')]);
    }

    // ---- ACCOUNT / PROFILES ----------------------------------------------

    public function account(): JsonResponse
    {
        $account = $this->requireAccount();
        return response()->json($account->load(['plan', 'profiles', 'license']));
    }

    public function profiles(): JsonResponse
    {
        $account = $this->requireAccount();
        return response()->json($account->profiles()->with('devices')->get());
    }

    public function storeProfile(Request $request): JsonResponse
    {
        $account = $this->requireAccount();
        $data = $request->validate([
            'name' => 'required|string',
            'age' => 'nullable|integer|min:0|max:25',
            'school_level' => 'nullable|in:primaria,secundaria,preparatoria',
            'profile_type' => 'nullable|in:nino,preadolescente,adolescente',
        ]);
        $data['account_id'] = $account->id;
        return response()->json(ParentalProfile::create($data), 201);
    }

    // ---- DEVICES ---------------------------------------------------------

    public function profileDevices(int $id): JsonResponse
    {
        $profile = $this->requireProfile($id);
        return response()->json($profile->devices);
    }

    /**
     * Liga un dispositivo a un perfil usando un token de un solo uso
     * (generado al escanear el QR en el dispositivo hijo).
     */
    public function linkDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'link_token' => 'required|string|max:64',
            'name' => 'sometimes|string',
            'model' => 'sometimes|string',
            'os' => 'sometimes|in:android,ios',
            'os_version' => 'sometimes|string',
            'app_version' => 'sometimes|string',
            'fcm_token' => 'sometimes|string',
        ]);

        $device = ParentalDevice::where('link_token', $data['link_token'])->first();
        if (! $device) {
            return response()->json(['success' => false, 'error' => 'Token de vinculación inválido'], 404);
        }
        if ($device->linked_at) {
            return response()->json(['success' => false, 'error' => 'Token ya usado'], 409);
        }

        $device->update(array_merge($data, [
            'linked_at' => now(),
            'last_seen_at' => now(),
            'status' => 'online',
        ]));

        return response()->json(['success' => true, 'device' => $device]);
    }

    public function deviceRules(int $id): JsonResponse
    {
        $device = ParentalDevice::findOrFail($id);
        $rules = ParentalRule::firstOrCreate(['profile_id' => $device->profile_id]);
        return response()->json($rules);
    }

    public function updateDeviceRules(Request $request, int $id): JsonResponse
    {
        $device = ParentalDevice::findOrFail($id);
        $rules = ParentalRule::firstOrCreate(['profile_id' => $device->profile_id]);
        $rules->update($request->only([
            'daily_limit_minutes', 'weekend_limit_minutes',
            'bedtime_start', 'bedtime_end',
            'school_start', 'school_end',
            'internet_paused',
        ]));
        return response()->json(['success' => true, 'rules' => $rules]);
    }

    // ---- TASKS / REQUESTS ------------------------------------------------

    public function profileTasks(int $id): JsonResponse
    {
        $profile = $this->requireProfile($id);
        return response()->json($profile->tasks()->orderByDesc('id')->get());
    }

    public function completeTask(Request $request, int $id): JsonResponse
    {
        $task = ParentalTask::findOrFail($id);
        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
            'photo_proof' => $request->input('photo_proof'),
        ]);
        return response()->json(['success' => true]);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profile_id' => 'required|exists:parental_profiles,id',
            'device_id' => 'nullable|exists:parental_devices,id',
            'type' => 'required|in:time_extra,app_unlock,web_unlock',
            'detail' => 'nullable|string',
            'message' => 'nullable|string',
        ]);
        $data['status'] = 'pending';
        $data['expires_at'] = now()->addHours(2);
        return response()->json(ParentalRequest::create($data), 201);
    }

    public function pendingRequests(): JsonResponse
    {
        $account = $this->requireAccount();
        $rows = ParentalRequest::whereIn('profile_id', $account->profiles()->pluck('id'))
            ->where('status', 'pending')
            ->orderByDesc('id')->get();
        return response()->json($rows);
    }

    public function respondRequest(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected']);
        $r = ParentalRequest::findOrFail($id);
        $r->update(['status' => $data['status'], 'responded_at' => now()]);
        return response()->json(['success' => true]);
    }

    // ---- LOCATIONS -------------------------------------------------------

    public function reportLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => 'required|exists:parental_devices,id',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'accuracy' => 'nullable|integer',
            'battery' => 'nullable|integer|min:0|max:100',
        ]);
        $data['recorded_at'] = now();
        ParentalLocation::create($data);

        ParentalDevice::where('id', $data['device_id'])->update([
            'last_seen_at' => now(),
            'status' => 'online',
            'battery_level' => $data['battery'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    public function profileLocation(int $id): JsonResponse
    {
        $profile = $this->requireProfile($id);
        $deviceIds = $profile->devices()->pluck('id');
        $latest = ParentalLocation::whereIn('device_id', $deviceIds)
            ->orderByDesc('recorded_at')
            ->limit($deviceIds->count())
            ->get()
            ->unique('device_id');
        return response()->json($latest->values());
    }

    // ---- helpers ---------------------------------------------------------

    private function requireAccount(): ParentalAccount
    {
        $account = ParentalAccount::where('client_id', Auth::id())->first();
        abort_if(! $account, 404, 'No hay cuenta MegaFamilia asociada al usuario');
        return $account;
    }

    private function requireProfile(int $id): ParentalProfile
    {
        $account = $this->requireAccount();
        $profile = ParentalProfile::where('account_id', $account->id)->where('id', $id)->first();
        abort_unless($profile, 404);
        return $profile;
    }
}
