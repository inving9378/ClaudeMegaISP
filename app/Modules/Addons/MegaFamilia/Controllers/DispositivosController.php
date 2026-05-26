<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use App\Modules\Addons\MegaFamilia\Models\ParentalLocation;
use App\Modules\Addons\MegaFamilia\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispositivosController extends Controller
{
    public function __construct(private FcmService $fcm)
    {
    }

    public function index()
    {
        return view('addon-megafamilia::dispositivos.index');
    }

    public function data(Request $request): JsonResponse
    {
        $now      = Carbon::now();
        $sevenAgo = $now->copy()->subDays(7);

        $kpis = [
            'total'       => ParentalDevice::count(),
            'online'      => ParentalDevice::where('status', 'online')->count(),
            'stale_7d'    => ParentalDevice::where(function ($q) use ($sevenAgo) {
                                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $sevenAgo);
                            })->count(),
        ];

        $q = ParentalDevice::query()
            ->with(['profile:id,name,profile_type', 'account:id,user_id'])
            ->when($request->profile_id, fn ($qq, $v) => $qq->where('profile_id', $v))
            ->when($request->os, fn ($qq, $v) => $qq->where('os', $v))
            ->when($request->status, fn ($qq, $v) => $qq->where('status', $v))
            ->orderByDesc('last_seen_at');

        $list = $q->paginate((int) $request->input('per_page', 24));

        return response()->json(['kpis' => $kpis, 'list' => $list]);
    }

    public function show(int $id): JsonResponse
    {
        $device = ParentalDevice::with([
            'profile',
            'account.user:id,name,email',
        ])->findOrFail($id);

        $latestLocation = ParentalLocation::where('device_id', $id)
            ->orderByDesc('recorded_at')
            ->first();

        $events = ParentalEvent::where('device_id', $id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return response()->json([
            'device'   => $device,
            'location' => $latestLocation,
            'events'   => $events,
        ]);
    }

    public function unlink(int $id): JsonResponse
    {
        $device = ParentalDevice::with('account.user')->findOrFail($id);
        $device->update([
            'link_token' => null,
            'status'     => 'unlinked',
            'fcm_token'  => null,
        ]);

        // Revocar tokens Sanctum del usuario del account dueño del dispositivo
        $user = $device->account?->user;
        if ($user && method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        ParentalEvent::create([
            'account_id' => $device->account_id,
            'profile_id' => $device->profile_id,
            'device_id'  => $device->id,
            'action'     => 'device.unlink',
            'detail'     => 'Admin desvinculó el dispositivo y revocó tokens',
        ]);

        return response()->json(['success' => true]);
    }

    public function ping(int $id): JsonResponse
    {
        $device = ParentalDevice::findOrFail($id);
        if (!$device->fcm_token) {
            return response()->json([
                'success' => false,
                'message' => 'El dispositivo no tiene fcm_token registrado.',
            ], 422);
        }

        $result = $this->fcm->send([$device->fcm_token], 'Ping', 'Solicitud de presencia desde el panel.', [
            'type'      => 'ping',
            'device_id' => (string) $device->id,
        ]);

        return response()->json(['success' => (bool) $result['success'], 'result' => $result]);
    }

    public function forceLogout(int $id): JsonResponse
    {
        $device = ParentalDevice::with('account.user')->findOrFail($id);
        $user   = $device->account?->user;
        if (!$user || !method_exists($user, 'tokens')) {
            return response()->json(['success' => false, 'message' => 'Usuario sin tokens revocables.'], 422);
        }

        $count = $user->tokens()->count();
        $user->tokens()->delete();

        ParentalEvent::create([
            'account_id' => $device->account_id,
            'profile_id' => $device->profile_id,
            'device_id'  => $device->id,
            'action'     => 'device.force_logout',
            'detail'     => "Admin revocó {$count} tokens Sanctum del usuario",
        ]);

        return response()->json(['success' => true, 'tokens_revoked' => $count]);
    }
}
