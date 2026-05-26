<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalRequest;
use App\Modules\Addons\MegaFamilia\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolicitudesController extends Controller
{
    public function __construct(private FcmService $fcm)
    {
    }

    public function index()
    {
        return view('addon-megafamilia::solicitudes.index');
    }

    public function data(Request $request): JsonResponse
    {
        $q = ParentalRequest::query()
            ->with([
                'profile:id,name,profile_type,age,photo',
                'device:id,name,fcm_token,os',
            ])
            ->when($request->status, fn ($qq, $v) => $qq->where('status', $v))
            ->when($request->type, fn ($qq, $v) => $qq->where('type', $v))
            ->when($request->profile_id, fn ($qq, $v) => $qq->where('profile_id', $v))
            ->orderByDesc('id');

        $list   = $q->paginate((int) $request->input('per_page', 30));
        $counts = [
            'pending'  => ParentalRequest::where('status', 'pending')->count(),
            'approved' => ParentalRequest::where('status', 'approved')->count(),
            'rejected' => ParentalRequest::where('status', 'rejected')->count(),
        ];

        return response()->json(['list' => $list, 'counts' => $counts]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $r = ParentalRequest::with('device')->findOrFail($id);

        $data = $request->validate([
            'minutes'  => 'sometimes|nullable|integer|min:1|max:1440',
            'duration' => 'sometimes|nullable|integer|min:1|max:1440', // minutos para *_unlock
        ]);

        $expiresAt = null;
        if ($r->type === 'time_extra' && !empty($data['minutes'])) {
            $expiresAt = Carbon::now()->addMinutes((int) $data['minutes']);
        } elseif (in_array($r->type, ['app_unlock', 'web_unlock'], true) && !empty($data['duration'])) {
            $expiresAt = Carbon::now()->addMinutes((int) $data['duration']);
        }

        $r->update([
            'status'       => 'approved',
            'responded_at' => Carbon::now(),
            'expires_at'   => $expiresAt,
        ]);

        $this->pushToDevice($r, 'Solicitud aprobada', $this->approvedBody($r, $data));

        return response()->json(['success' => true, 'request' => $r]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:500']);

        $r = ParentalRequest::with('device')->findOrFail($id);
        $r->update([
            'status'       => 'rejected',
            'responded_at' => Carbon::now(),
            'notes'        => $data['reason'] ?? null,
        ]);

        $body = 'Tu solicitud fue rechazada' . (!empty($data['reason']) ? ': ' . $data['reason'] : '.');
        $this->pushToDevice($r, 'Solicitud rechazada', $body);

        return response()->json(['success' => true, 'request' => $r]);
    }

    public function bulkRead(): JsonResponse
    {
        $count = ParentalRequest::whereNull('responded_at')
            ->where('status', 'pending')
            ->update(['responded_at' => null]); // soft-marker
        return response()->json(['success' => true, 'updated' => $count]);
    }

    private function pushToDevice(ParentalRequest $r, string $title, string $body): void
    {
        $token = $r->device?->fcm_token;
        if (!$token) return;

        $this->fcm->send([$token], $title, $body, [
            'type'        => 'solicitud_respondida',
            'request_id'  => (string) $r->id,
            'status'      => $r->status,
            'request_type'=> $r->type,
            'expires_at'  => optional($r->expires_at)->toIso8601String() ?? '',
        ]);
    }

    private function approvedBody(ParentalRequest $r, array $data): string
    {
        if ($r->type === 'time_extra' && !empty($data['minutes'])) {
            return "Tu padre te concedió {$data['minutes']} minutos extra.";
        }
        if (in_array($r->type, ['app_unlock', 'web_unlock'], true) && !empty($data['duration'])) {
            return "Acceso temporal concedido por {$data['duration']} minutos.";
        }
        return 'Tu solicitud fue aprobada.';
    }
}
