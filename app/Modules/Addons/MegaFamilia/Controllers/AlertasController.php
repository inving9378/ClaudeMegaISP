<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalAlert;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertasController extends Controller
{
    private const CRITICAL_TYPES = ['uninstall_attempt', 'sos'];

    public function __construct(private FcmService $fcm)
    {
    }

    public function index()
    {
        return view('addon-megafamilia::alertas.index');
    }

    public function data(Request $request): JsonResponse
    {
        $todayStart = Carbon::today();

        $kpis = [
            'today'     => ParentalAlert::where('created_at', '>=', $todayStart)->count(),
            'unread'    => ParentalAlert::whereNull('read_at')->count(),
            'critical'  => ParentalAlert::whereIn('type', self::CRITICAL_TYPES)
                                ->whereNull('read_at')->count(),
        ];

        $q = ParentalAlert::query()
            ->with([
                'profile:id,name,profile_type,photo',
                'device:id,name,os',
                'account:id,user_id',
            ])
            ->when($request->type, fn ($qq, $v) => $qq->where('type', $v))
            ->when($request->unread === 'true', fn ($qq) => $qq->whereNull('read_at'))
            ->when($request->profile_id, fn ($qq, $v) => $qq->where('profile_id', $v))
            ->when($request->fecha_desde, fn ($qq, $v) => $qq->whereDate('created_at', '>=', $v))
            ->when($request->fecha_hasta, fn ($qq, $v) => $qq->whereDate('created_at', '<=', $v))
            ->orderByDesc('id');

        $list = $q->paginate((int) $request->input('per_page', 30));

        return response()->json(['kpis' => $kpis, 'list' => $list]);
    }

    public function show(int $id): JsonResponse
    {
        $alert = ParentalAlert::with([
            'profile', 'device', 'account.user:id,name,email',
        ])->findOrFail($id);

        if (!$alert->read_at) {
            $alert->update(['read_at' => Carbon::now()]);
        }

        return response()->json($alert);
    }

    public function markRead(int $id): JsonResponse
    {
        ParentalAlert::findOrFail($id)->update(['read_at' => Carbon::now()]);
        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        $count = ParentalAlert::whereNull('read_at')->update(['read_at' => Carbon::now()]);
        return response()->json(['success' => true, 'updated' => $count]);
    }

    /**
     * Envía un push crítico a los dispositivos asociados a la cuenta del kid.
     * Nota: no existe un canal "padre" separado en el schema actual; el push
     * llega a todos los dispositivos de la cuenta. Si se incorpora un FCM
     * de parent específico (ej. columna users.fcm_token), reemplazar aquí.
     */
    public function notifyParent(int $id): JsonResponse
    {
        $alert = ParentalAlert::with(['profile', 'device'])->findOrFail($id);

        if (!in_array($alert->type, self::CRITICAL_TYPES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Solo alertas críticas (uninstall_attempt, sos) pueden notificar al padre.',
            ], 422);
        }

        $tokens = ParentalDevice::where('account_id', $alert->account_id)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->all();

        if (empty($tokens)) {
            return response()->json([
                'success' => false,
                'message' => 'No hay dispositivos con FCM token asociados a la cuenta.',
            ], 422);
        }

        $result = $this->fcm->send(
            $tokens,
            'Alerta crítica MegaFamilia',
            $this->describeAlert($alert),
            [
                'type'       => 'alerta_critica',
                'alert_id'   => (string) $alert->id,
                'alert_type' => $alert->type,
                'profile_id' => (string) ($alert->profile_id ?? ''),
                'device_id'  => (string) ($alert->device_id ?? ''),
            ],
        );

        return response()->json(['success' => (bool) $result['success'], 'result' => $result]);
    }

    private function describeAlert(ParentalAlert $a): string
    {
        $perfil = $a->profile?->name ?? 'un perfil';
        return match ($a->type) {
            'uninstall_attempt' => "Se intentó desinstalar la app de {$perfil}",
            'sos'               => "Botón SOS activado por {$perfil}",
            default             => "Alerta {$a->type} en {$perfil}",
        };
    }
}
